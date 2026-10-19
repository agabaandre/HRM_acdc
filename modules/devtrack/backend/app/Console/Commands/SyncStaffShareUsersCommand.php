<?php

namespace App\Console\Commands;

use App\Models\StaffShareSetting;
use App\Services\StaffShare\StaffShareUserSyncService;
use Illuminate\Console\Command;

class SyncStaffShareUsersCommand extends Command
{
    protected $signature = 'devtrack:sync-staff-share
                            {--username= : Staff portal work_email for HTTP Basic (optional if token set)}
                            {--password= : Staff portal password for HTTP Basic}';

    protected $description = 'Import Staff Share API directory into the staff table (and mirror into users)';

    public function handle(StaffShareUserSyncService $sync): int
    {
        $settings = StaffShareSetting::current();
        $settings->applyToConfig();

        if (! config('services.staff_share.enabled')) {
            $this->error('Staff Share sync is disabled (UI setting or STAFF_SHARE_ENABLED).');

            return self::FAILURE;
        }

        $username = $this->option('username') ?: config('services.staff_share.username');
        $password = $this->option('password') ?: config('services.staff_share.password');

        if (filled($username) && $password !== null && $password !== '') {
            config([
                'services.staff_share.username' => $username,
                'services.staff_share.password' => $password,
            ]);
        }

        $token = $settings->resolvedToken();
        $hasBasic = filled(config('services.staff_share.username'))
            && filled(config('services.staff_share.password'));

        if (! filled($token) && ! $hasBasic) {
            $this->error('Save a Bearer token in the Staff Share UI, or set STAFF_SHARE_API_TOKEN / --username/--password.');

            return self::FAILURE;
        }

        try {
            $stats = $sync->syncDirectory($token);
            $settings->forceFill([
                'last_synced_at' => now(),
                'last_sync_total' => $stats['staff']['total'] ?? null,
                'last_sync_message' => sprintf(
                    'Synced %d staff (%d created, %d updated).',
                    $stats['staff']['total'] ?? 0,
                    $stats['staff']['created'] ?? 0,
                    $stats['staff']['updated'] ?? 0,
                ),
            ])->save();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            [
                ['Staff rows fetched', $stats['staff']['total']],
                ['Staff created', $stats['staff']['created']],
                ['Staff updated', $stats['staff']['updated']],
                ['Staff skipped', $stats['staff']['skipped']],
                ['Users created', $stats['users']['created'] ?? 0],
                ['Users updated', $stats['users']['updated'] ?? 0],
                ['Users skipped (no platform account)', $stats['users']['skipped'] ?? 0],
            ],
        );

        $this->info('Staff Share sync complete → `staff` table.');

        return self::SUCCESS;
    }
}
