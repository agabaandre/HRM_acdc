<?php

namespace App\Console\Commands;

use App\Jobs\PollBusinessUnitMailboxesJob;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

/**
 * Post-deploy checks so production has schema, schedule, and queue wiring for new features.
 */
class VerifyProductionReadinessCommand extends Command
{
    protected $signature = 'helpdesk:verify-production
                            {--strict : Exit 1 when any check fails (default: warn-only for optional items)}';

    protected $description = 'Verify HelpDesk production readiness (migrations, Protocol BU, queues, mailbox intake schedule)';

    public function handle(): int
    {
        $strict = (bool) $this->option('strict');
        $failed = 0;
        $warned = 0;

        $fail = function (string $msg) use (&$failed): void {
            $this->error('FAIL  '.$msg);
            $failed++;
        };
        $warn = function (string $msg) use (&$warned): void {
            $this->warn('WARN  '.$msg);
            $warned++;
        };
        $ok = function (string $msg): void {
            $this->info('OK    '.$msg);
        };

        $this->line('HelpDesk production readiness');
        $this->newLine();

        // --- Schema / feature migrations ---
        $requiredTables = [
            'helpdesk_business_units',
            'helpdesk_categories',
            'helpdesk_email_messages',
            'helpdesk_it_asset_brands',
            'helpdesk_it_assets',
            'helpdesk_queue_jobs',
        ];
        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $ok("Table {$table}");
            } else {
                $fail("Missing table {$table} — run: php artisan migrate --force");
            }
        }

        $columnChecks = [
            ['helpdesk_profiles', 'is_agent_disabled'],
            ['helpdesk_business_units', 'description'],
            ['helpdesk_business_units', 'support_mailbox'],
            ['helpdesk_business_units', 'email_intake_enabled'],
            ['helpdesk_business_units', 'allows_asset_link_on_resolve'],
            ['helpdesk_tickets', 'business_unit_id'],
            ['helpdesk_tickets', 'linked_it_asset_id'],
        ];
        foreach ($columnChecks as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, $column)) {
                $ok("Column {$table}.{$column}");
            } else {
                $fail("Missing column {$table}.{$column} — run: php artisan migrate --force");
            }
        }

        // --- Protocol BU ---
        if (Schema::hasTable('helpdesk_business_units')) {
            $protocol = HelpdeskBusinessUnit::query()->where('slug', 'protocol')->first();
            if ($protocol) {
                $catCount = $protocol->categories()->count();
                if ($catCount >= 3) {
                    $ok("Protocol business unit (#{$protocol->id}) with {$catCount} categories");
                } else {
                    $warn("Protocol BU exists but only {$catCount} categories — run: php artisan db:seed --class=HelpdeskCategorySeeder --force");
                }
            } else {
                $fail('Protocol business unit missing — run migrate (2026_07_22_180000) or HelpdeskCategorySeeder');
            }
        }

        // --- Branding ---
        $brand = (string) config('helpdesk.mail_brand_name', '');
        if (stripos($brand, 'IT Service Desk') !== false || stripos($brand, 'IT Help Desk') !== false) {
            $warn("Mail brand still uses an IT-only name ({$brand}) — set HELPDESK_MAIL_BRAND_NAME=\"Africa CDC HelpDesk\" and config:cache");
        } elseif (stripos($brand, 'Service Desk') !== false || stripos($brand, 'Help Desk') !== false) {
            $warn("Mail brand still uses legacy Service Desk naming ({$brand}) — set HELPDESK_MAIL_BRAND_NAME=\"Africa CDC HelpDesk\" and config:cache");
        } elseif ($brand !== '') {
            $ok("Mail brand: {$brand}");
        }

        $appName = (string) config('app.name', '');
        if (stripos($appName, 'IT Service Desk') !== false || stripos($appName, 'IT Help Desk') !== false || stripos($appName, 'Service Desk') !== false) {
            $warn("APP_NAME still references Service Desk ({$appName})");
        }

        // --- Schedule includes mailbox poll ---
        $hasPoll = false;
        foreach (Schedule::events() as $event) {
            $parts = [];
            if (method_exists($event, 'description')) {
                try {
                    $parts[] = (string) $event->description();
                } catch (\Throwable) {
                    // ignore
                }
            }
            if (isset($event->description) && is_string($event->description)) {
                $parts[] = $event->description;
            }
            if (isset($event->command) && is_string($event->command)) {
                $parts[] = $event->command;
            }
            // Job events store the job class on the event.
            try {
                $ref = new \ReflectionObject($event);
                if ($ref->hasProperty('job')) {
                    $prop = $ref->getProperty('job');
                    $prop->setAccessible(true);
                    $job = $prop->getValue($event);
                    if (is_object($job)) {
                        $parts[] = $job::class;
                    } elseif (is_string($job)) {
                        $parts[] = $job;
                    }
                }
            } catch (\Throwable) {
                // ignore
            }
            $blob = implode(' ', $parts);
            if (str_contains($blob, PollBusinessUnitMailboxesJob::class)
                || str_contains($blob, 'PollBusinessUnitMailboxesJob')) {
                $hasPoll = true;
                break;
            }
        }
        if ($hasPoll) {
            $ok('Schedule registers PollBusinessUnitMailboxesJob (every minute)');
        } else {
            $fail('PollBusinessUnitMailboxesJob not found in schedule — check bootstrap/app.php withSchedule()');
        }

        // --- Queue connection / tables ---
        $queueConn = (string) config('queue.default');
        $queueTable = (string) config('queue.connections.database.table', '');
        $ok("Queue connection: {$queueConn}".($queueTable !== '' ? " (table {$queueTable})" : ''));
        if ($queueConn === 'sync') {
            $warn('QUEUE_CONNECTION=sync — background jobs (AI categorize, mailbox poll) will not run async; use database or redis in production');
        }

        // --- Email intake gates (informational) ---
        $master = HelpdeskSetting::emailTicketIntakeEnabled();
        if ($master) {
            $units = HelpdeskBusinessUnit::query()
                ->where('is_active', true)
                ->where('email_intake_enabled', true)
                ->whereNotNull('support_mailbox')
                ->where('support_mailbox', '!=', '')
                ->count();
            $ok("Email ticket intake MASTER ON; {$units} BU(s) with intake enabled");
            if ($units < 1) {
                $warn('Master intake is on but no BU has mailbox + intake — enable under Settings → Business units');
            }
            $tenant = (string) config('exchange-email.tenant_id');
            $clientId = (string) config('exchange-email.client_id');
            $clientSecret = (string) config('exchange-email.client_secret');
            if ($tenant === '' || $clientId === '' || $clientSecret === '') {
                $fail('Email intake is ON but EXCHANGE_TENANT_ID / CLIENT_ID / CLIENT_SECRET are incomplete');
            } else {
                $ok('EXCHANGE_* credentials present for Graph mail');
            }
        } else {
            $ok('Email ticket intake master switch OFF (safe default) — enable in Settings → General when Graph Mail.ReadWrite is ready');
        }

        // --- Pending migrations ---
        try {
            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(fn ($p) => basename((string) $p, '.php'))
                ->all();
            $pending = array_values(array_diff($files, $ran));
            if ($pending === []) {
                $ok('No pending migrations');
            } else {
                $fail('Pending migrations: '.implode(', ', array_slice($pending, 0, 8)).(count($pending) > 8 ? '…' : ''));
            }
        } catch (\Throwable $e) {
            $warn('Could not inspect migrations table: '.$e->getMessage());
        }

        $this->newLine();
        $this->line('Runtime (ops) checklist — confirm on the server:');
        $this->line('  • systemctl is-active helpdesk-queue.service helpdesk-scheduler.timer');
        $this->line('  • scheduler runs mailbox intake inline; queue worker still needed for helpdesk-ai');
        $this->line('  • Staff cbp_modules.system_name = "HelpDesk" for helpdesk_itsm');
        $this->line('  • Graph app has Mail.ReadWrite (or read+move) on each intake mailbox');
        $this->newLine();

        if ($failed > 0) {
            $this->error("{$failed} failure(s), {$warned} warning(s).");

            return self::FAILURE;
        }

        $this->info($warned > 0 ? "All required checks passed ({$warned} warning(s))." : 'All checks passed.');

        return self::SUCCESS;
    }
}
