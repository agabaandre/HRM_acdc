<?php

namespace App\Console\Commands;

use App\Models\MemoTypeDefinition;
use Illuminate\Console\Command;

class DebugOtherMemoCcCommand extends Command
{
    protected $signature = 'apm:debug-other-memo-cc {slug? : Memo type slug to inspect (default: cash-approval)}';

    protected $description = 'Diagnose other-memo CC visibility (DB flags, API payload, create-page map)';

    public function handle(): int
    {
        $slug = (string) ($this->argument('slug') ?: 'cash-approval');

        $this->info('Active memo types with CC on create:');
        $ccTypes = MemoTypeDefinition::query()
            ->where('is_active', true)
            ->where('cc_on_approval_enabled', true)
            ->orderBy('name')
            ->get(['slug', 'name', 'cc_on_approval_enabled']);

        if ($ccTypes->isEmpty()) {
            $this->warn('  (none) — enable "Show CC option on memo create" on an active type in memo-type-definitions.');
        } else {
            foreach ($ccTypes as $t) {
                $this->line("  • {$t->slug} — {$t->name}");
            }
        }

        $this->newLine();
        $this->info("Inspecting slug: {$slug}");

        $type = MemoTypeDefinition::query()->where('slug', $slug)->first();
        if (! $type) {
            $this->error("Memo type not found: {$slug}");

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['name', $type->name],
                ['is_active', $type->is_active ? 'yes' : 'no'],
                ['cc_on_approval_enabled (DB)', $type->cc_on_approval_enabled ? 'yes' : 'no'],
                ['visible on create form', ($type->is_active && $type->cc_on_approval_enabled) ? 'YES' : 'NO'],
            ]
        );

        $api = $type->toApiArray();
        $this->newLine();
        $this->info('API field cc_on_approval_enabled: '.json_encode($api['cc_on_approval_enabled']));

        $map = MemoTypeDefinition::query()
            ->where('is_active', true)
            ->get(['slug', 'cc_on_approval_enabled'])
            ->mapWithKeys(fn (MemoTypeDefinition $m) => [$m->slug => (bool) $m->cc_on_approval_enabled])
            ->all();

        $this->newLine();
        $this->info('data-memo-type-cc-by-slug for create page (true only):');
        foreach ($map as $s => $on) {
            if ($on) {
                $this->line("  {$s} => true");
            }
        }

        if (! ($type->is_active && $type->cc_on_approval_enabled)) {
            $this->newLine();
            $this->warn('CC card will stay hidden until is_active and cc_on_approval_enabled are both true.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Backend OK. On create, select this type in the dropdown; CC card should appear.');
        $this->line('If not, hard-refresh and check DevTools → Console for JS errors.');

        return self::SUCCESS;
    }
}
