<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Reads budget-commitment rules from system settings (App settings → Budget).
 */
class BudgetCommitmentSettings
{
    /** @var list<string> */
    public const DEFAULT_ACTIVITY_STATUSES = ['draft', 'pending', 'submitted', 'approved'];

    /** @var list<string> */
    public const DEFAULT_MEMO_STATUSES = ['draft', 'pending', 'approved'];

    /** @var list<string> */
    public const DEFAULT_CHANGE_REQUEST_STATUSES = ['draft', 'pending', 'submitted'];

    /**
     * @return list<string>
     */
    public function committedActivityStatuses(): array
    {
        return $this->parseStatusList(
            SystemSetting::get('budget_committed_activity_statuses'),
            self::DEFAULT_ACTIVITY_STATUSES
        );
    }

    /**
     * @return list<string>
     */
    public function committedMemoStatuses(): array
    {
        return $this->parseStatusList(
            SystemSetting::get('budget_committed_memo_statuses'),
            self::DEFAULT_MEMO_STATUSES
        );
    }

    /**
     * @return list<string>
     */
    public function committedChangeRequestStatuses(): array
    {
        return $this->parseStatusList(
            SystemSetting::get('budget_committed_change_request_statuses'),
            self::DEFAULT_CHANGE_REQUEST_STATUSES
        );
    }

    /**
     * Draft memos last updated before this moment no longer commit budget (null = no age limit).
     */
    public function draftBudgetCutoff(): ?Carbon
    {
        $months = (int) SystemSetting::get('budget_draft_max_age_months', '2');
        if ($months <= 0) {
            return null;
        }

        return now()->subMonths($months);
    }

    public function draftMaxAgeMonths(): int
    {
        return max(0, (int) SystemSetting::get('budget_draft_max_age_months', '2'));
    }

    public function staleDraftRemindersEnabled(): bool
    {
        $value = SystemSetting::get('budget_stale_draft_reminders_enabled', '1');

        return ! in_array(strtolower((string) $value), ['0', 'false', 'no', 'off'], true);
    }

    /**
     * Cache-bust token when commitment settings change.
     */
    public function cacheToken(): string
    {
        return md5(implode('|', [
            (string) SystemSetting::get('budget_draft_max_age_months', '2'),
            (string) SystemSetting::get('budget_committed_activity_statuses', ''),
            (string) SystemSetting::get('budget_committed_memo_statuses', ''),
            (string) SystemSetting::get('budget_committed_change_request_statuses', ''),
        ]));
    }

    /**
     * Exclude abandoned draft documents from budget commitment when age limit is configured.
     *
     * @param  Builder<mixed>|QueryBuilder  $query
     */
    public function applyDraftAgeFilter(Builder|QueryBuilder $query, string $table, array $committedStatuses): void
    {
        if (! in_array('draft', $committedStatuses, true)) {
            return;
        }

        $cutoff = $this->draftBudgetCutoff();
        if ($cutoff === null) {
            return;
        }

        $query->where(function ($q) use ($table, $cutoff) {
            $q->where("{$table}.overall_status", '!=', 'draft')
                ->orWhere("{$table}.updated_at", '>=', $cutoff);
        });
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private function parseStatusList(?string $raw, array $default): array
    {
        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        $items = array_values(array_unique(array_filter(array_map(
            static fn (string $s): string => strtolower(trim($s)),
            explode(',', $raw)
        ))));

        return $items !== [] ? $items : $default;
    }
}
