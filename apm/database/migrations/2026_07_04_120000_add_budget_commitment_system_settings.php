<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $settings = [
            [
                'key' => 'budget_draft_max_age_months',
                'value' => '2',
                'group' => 'budget',
                'type' => 'number',
            ],
            [
                'key' => 'budget_committed_activity_statuses',
                'value' => 'draft,pending,submitted,approved',
                'group' => 'budget',
                'type' => 'text',
            ],
            [
                'key' => 'budget_committed_memo_statuses',
                'value' => 'draft,pending,approved',
                'group' => 'budget',
                'type' => 'text',
            ],
            [
                'key' => 'budget_committed_change_request_statuses',
                'value' => 'draft,pending,submitted',
                'group' => 'budget',
                'type' => 'text',
            ],
            [
                'key' => 'budget_stale_draft_reminders_enabled',
                'value' => '1',
                'group' => 'budget',
                'type' => 'boolean',
            ],
        ];

        foreach ($settings as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $row['key']],
                array_merge($row, ['created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'budget_draft_max_age_months',
            'budget_committed_activity_statuses',
            'budget_committed_memo_statuses',
            'budget_committed_change_request_statuses',
            'budget_stale_draft_reminders_enabled',
        ])->delete();
    }
};
