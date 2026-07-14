<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ChangeRequest;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\StaleMemoArchive;
use Carbon\Carbon;

/**
 * Archives stale draft memos that still hold budget (system action; bypasses can_archive_memo).
 */
class StaleMemoArchiveService
{
    public function __construct(
        private readonly BudgetCommitmentSettings $settings = new BudgetCommitmentSettings(),
        private readonly StaleDraftMemosService $staleDrafts = new StaleDraftMemosService(),
        private readonly StaleDraftArchiveSchedule $schedule = new StaleDraftArchiveSchedule()
    ) {}

    /**
     * @return array{archived: int, skipped: int, errors: list<string>}
     */
    public function archiveAllStaleDrafts(string $trigger = 'manual', ?int $archivedByStaffId = null): array
    {
        $items = $this->staleDrafts->getAllStaleDrafts();
        $archived = 0;
        $skipped = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                if ($this->archiveMemoItem($item, $trigger, $archivedByStaffId)) {
                    $archived++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = ($item['type'] ?? 'memo') . ' #' . ($item['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return compact('archived', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function archiveMemoItem(array $item, string $trigger = 'scheduled', ?int $archivedByStaffId = null): bool
    {
        $type = (string) ($item['type'] ?? '');
        $id = (int) ($item['id'] ?? 0);
        if ($id <= 0 || $type === '') {
            return false;
        }

        $model = $this->resolveModel($type, $id);
        if ($model === null) {
            return false;
        }

        if ((string) ($model->overall_status ?? '') !== 'draft') {
            return false;
        }

        $cutoff = $this->settings->draftBudgetCutoff();
        $updatedAt = $model->updated_at instanceof Carbon
            ? $model->updated_at
            : ($model->updated_at ? Carbon::parse($model->updated_at) : null);

        if ($cutoff === null || $updatedAt === null || $updatedAt->gte($cutoff)) {
            return false;
        }

        if (! $this->staleDrafts->memoHoldsBudget($model)) {
            return false;
        }

        $previousStatus = (string) ($model->overall_status ?? 'draft');
        $model->previous_overall_status = $previousStatus;
        $model->overall_status = 'archived';
        $model->save();

        app(FundCodeWorkingBalanceService::class)->bustForArchiveStatusChange($model);

        StaleMemoArchive::query()->create([
            'memo_type' => $type,
            'memo_id' => $id,
            'document_number' => $model->document_number ?? ($item['document_number'] ?? null),
            'title' => (string) ($model->activity_title ?? $item['title'] ?? 'Untitled'),
            'staff_id' => (int) ($model->staff_id ?? $item['staff_id'] ?? 0) ?: null,
            'responsible_person_id' => (int) ($model->responsible_person_id ?? $item['responsible_person_id'] ?? 0) ?: null,
            'budget_total' => (float) ($item['budget_total'] ?? 0),
            'previous_status' => $previousStatus,
            'memo_updated_at' => $updatedAt,
            'archived_at' => now(),
            'trigger' => $trigger,
            'archived_by_staff_id' => $archivedByStaffId,
        ]);

        return true;
    }

    /**
     * Restore a previously archived stale draft (admin / system-configs).
     */
    public function unarchiveMemoItem(string $type, int $id, ?string $fallbackStatus = 'draft'): bool
    {
        $model = $this->resolveModel($type, $id);
        if ($model === null) {
            return false;
        }

        if ((string) ($model->overall_status ?? '') !== 'archived') {
            return false;
        }

        $restored = (string) ($model->previous_overall_status ?: $fallbackStatus ?: 'draft');
        if ($restored === 'archived') {
            $restored = 'draft';
        }

        $model->overall_status = $restored;
        $model->previous_overall_status = null;
        $model->save();

        app(FundCodeWorkingBalanceService::class)->bustForArchiveStatusChange($model);

        return true;
    }

    public function resolveModel(string $type, int $id): Activity|SpecialMemo|NonTravelMemo|ChangeRequest|null
    {
        return match ($type) {
            'activity', 'single_memo' => Activity::query()->find($id),
            'special_memo' => SpecialMemo::query()->find($id),
            'non_travel_memo' => NonTravelMemo::query()->find($id),
            'change_request' => ChangeRequest::query()->find($id),
            default => null,
        };
    }
}
