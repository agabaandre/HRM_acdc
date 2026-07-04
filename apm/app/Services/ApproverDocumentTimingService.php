<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ApprovalTrail;
use App\Models\ApproverDocumentTimingRecord;
use App\Models\ChangeRequest;
use App\Models\Matrix;
use App\Models\NonTravelMemo;
use App\Models\OtherMemo;
use App\Models\OtherMemoApprovalTrail;
use App\Models\RequestARF;
use App\Models\ServiceRequest;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\WorkflowDefinition;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ApproverDocumentTimingService
{
    public function __construct(
        protected ApprovalReceiptTimeCalculator $receiptCalculator
    ) {}

    public function recordFromApprovalTrailId(int $approvalTrailId): void
    {
        $trail = ApprovalTrail::query()->find($approvalTrailId);
        if (! $trail || $trail->is_archived) {
            return;
        }
        if (! in_array($trail->action, ['approved', 'rejected'], true)) {
            return;
        }
        if (! $trail->forward_workflow_id || ! $trail->approval_order) {
            return;
        }

        if (ApproverDocumentTimingRecord::query()->where('approval_trail_id', $trail->id)->exists()) {
            return;
        }

        $received = $this->receiptCalculator->receivedAtForApprovalTrail($trail);
        $acted = Carbon::parse($trail->updated_at);
        if (! $received || $acted->lt($received)) {
            return;
        }

        $hours = max(0, ($acted->getTimestamp() - $received->getTimestamp()) / 3600.0);

        $meta = $this->resolveDocumentMeta($trail->model_type, (int) $trail->model_id);
        $staff = Staff::query()->where('staff_id', $trail->staff_id)->first();
        $staffName = $staff ? trim(($staff->title ? $staff->title.' ' : '').$staff->fname.' '.$staff->lname) : null;

        ApproverDocumentTimingRecord::query()->create([
            'approval_trail_id' => $trail->id,
            'staff_id' => (int) $trail->staff_id,
            'staff_name_snapshot' => $staffName,
            'model_type' => $trail->model_type,
            'model_id' => (int) $trail->model_id,
            'forward_workflow_id' => (int) $trail->forward_workflow_id,
            'approval_order' => (int) $trail->approval_order,
            'action' => $trail->action,
            'received_at' => $received,
            'acted_at' => $acted,
            'hours_elapsed' => round($hours, 4),
            'document_type_label' => $meta['document_type_label'],
            'document_title' => $meta['document_title'],
            'document_number_snapshot' => $meta['document_number'],
            'division_id' => $meta['division_id'],
            'division_name_snapshot' => $meta['division_name'],
            'workflow_name_snapshot' => $this->workflowNameSnapshot((int) $trail->forward_workflow_id),
            'workflow_role_snapshot' => $this->workflowRoleSnapshot((int) $trail->forward_workflow_id, (int) $trail->approval_order),
        ]);
    }

    public function recordFromOtherMemoTrailId(int $otherMemoTrailId): void
    {
        $trail = OtherMemoApprovalTrail::query()->find($otherMemoTrailId);
        if (! $trail || $trail->action !== 'approved') {
            return;
        }

        if (ApproverDocumentTimingRecord::query()->where('other_memo_approval_trail_id', $trail->id)->exists()) {
            return;
        }

        $received = $this->receiptCalculator->receivedAtForOtherMemoTrail($trail);
        $acted = Carbon::parse($trail->updated_at ?? $trail->created_at);
        if (! $received || $acted->lt($received)) {
            return;
        }

        $hours = max(0, ($acted->getTimestamp() - $received->getTimestamp()) / 3600.0);

        $memo = OtherMemo::query()->with('division')->find($trail->other_memo_id);
        $staff = Staff::query()->where('staff_id', $trail->staff_id)->first();
        $staffName = $staff ? trim(($staff->title ? $staff->title.' ' : '').$staff->fname.' '.$staff->lname) : null;

        $title = $memo
            ? (($memo->memo_type_slug ? strtoupper(str_replace('-', ' ', $memo->memo_type_slug)).' · ' : '').($memo->document_number ?? 'Other memo #'.$memo->id))
            : 'Other memo';

        ApproverDocumentTimingRecord::query()->create([
            'other_memo_approval_trail_id' => $trail->id,
            'staff_id' => (int) $trail->staff_id,
            'staff_name_snapshot' => $staffName,
            'model_type' => OtherMemo::class,
            'model_id' => (int) $trail->other_memo_id,
            'forward_workflow_id' => null,
            'approval_order' => (int) $trail->approval_order,
            'action' => $trail->action,
            'received_at' => $received,
            'acted_at' => $acted,
            'hours_elapsed' => round($hours, 4),
            'document_type_label' => 'Other Memo',
            'document_title' => $title,
            'document_number_snapshot' => $memo->document_number ?? null,
            'division_id' => $memo->division_id ?? null,
            'division_name_snapshot' => $memo && $memo->division ? $memo->division->division_name : null,
            'workflow_name_snapshot' => null,
            'workflow_role_snapshot' => $trail->approver_role_name ?? null,
        ]);
    }

    /**
     * @return array{document_type_label: string|null, document_title: string|null, document_number: string|null, division_id: int|null, division_name: string|null}
     */
    public function resolveDocumentMeta(string $modelType, int $modelId): array
    {
        $empty = [
            'document_type_label' => class_basename($modelType),
            'document_title' => null,
            'document_number' => null,
            'division_id' => null,
            'division_name' => null,
        ];

        try {
            /** @var Model|null $model */
            $model = match ($modelType) {
                Matrix::class => Matrix::query()->with('division')->find($modelId),
                Activity::class => Activity::query()->with(['division', 'matrix.division'])->find($modelId),
                SpecialMemo::class => SpecialMemo::query()->with('division')->find($modelId),
                NonTravelMemo::class => NonTravelMemo::query()->with('division')->find($modelId),
                ServiceRequest::class => ServiceRequest::query()->with('division')->find($modelId),
                RequestARF::class => RequestARF::query()->with('division')->find($modelId),
                ChangeRequest::class => ChangeRequest::query()->with('division')->find($modelId),
                OtherMemo::class => OtherMemo::query()->with('division')->find($modelId),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::warning('ApproverDocumentTimingService: resolve meta failed', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }

        if (! $model) {
            return $empty;
        }

        return match ($modelType) {
            Matrix::class => [
                'document_type_label' => 'Quarterly Matrix',
                'document_title' => 'Matrix — '.($model->quarter ?? '').' '.($model->year ?? ''),
                'document_number' => null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            Activity::class => [
                'document_type_label' => ($model->is_single_memo ?? false) ? 'Single Memo' : 'Activity',
                'document_title' => $model->activity_title ?? ('Activity #'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id ?? $model->matrix->division_id ?? null,
                'division_name' => ($model->division ?? $model->matrix->division ?? null)?->division_name,
            ],
            SpecialMemo::class => [
                'document_type_label' => 'Special Memo',
                'document_title' => $model->activity_title ?? ('Special memo #'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            NonTravelMemo::class => [
                'document_type_label' => 'Non-Travel Memo',
                'document_title' => $model->activity_title ?? ('Non-travel memo #'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            ServiceRequest::class => [
                'document_type_label' => 'Service Request',
                'document_title' => $model->title ?? ('Service request #'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            RequestARF::class => [
                'document_type_label' => 'ARF',
                'document_title' => $model->activity_title ?? ($model->document_number ?? ('ARF #'.$model->id)),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            ChangeRequest::class => [
                'document_type_label' => 'Change Request',
                'document_title' => $model->activity_title ?? ('Change request #'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            OtherMemo::class => [
                'document_type_label' => 'Other Memo',
                'document_title' => ($model->memo_type_slug ?? '').' · '.($model->document_number ?? '#'.$model->id),
                'document_number' => $model->document_number ?? null,
                'division_id' => $model->division_id,
                'division_name' => $model->division->division_name ?? null,
            ],
            default => $empty,
        };
    }

    public function resolveDocumentUrl(string $modelType, int $modelId): ?string
    {
        try {
            return match ($modelType) {
                Matrix::class => route('matrices.show', ['matrix' => $modelId], false),
                Activity::class => $this->activityShowPath($modelId),
                SpecialMemo::class => route('special-memo.show', ['special_memo' => $modelId], false),
                NonTravelMemo::class => route('non-travel.show', ['non_travel' => $modelId], false),
                ServiceRequest::class => route('service-requests.show', ['service_request' => $modelId], false),
                RequestARF::class => route('request-arf.show', ['request_arf' => $modelId], false),
                ChangeRequest::class => route('change-requests.show', ['change_request' => $modelId], false),
                OtherMemo::class => route('other-memos.show', ['other_memo' => $modelId], false),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    protected function activityShowPath(int $activityId): ?string
    {
        $activity = Activity::query()->find($activityId);
        if (! $activity || ! $activity->matrix_id) {
            return null;
        }

        try {
            return route('matrices.activities.show', ['matrix' => $activity->matrix_id, 'activity' => $activityId], false);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function workflowNameSnapshot(int $workflowId): ?string
    {
        return Workflow::query()->whereKey($workflowId)->value('workflow_name');
    }

    protected function workflowRoleSnapshot(int $workflowId, int $approvalOrder): ?string
    {
        return WorkflowDefinition::query()
            ->where('workflow_id', $workflowId)
            ->where('approval_order', $approvalOrder)
            ->where('is_enabled', 1)
            ->orderBy('id')
            ->value('role');
    }

    /**
     * @param  array{
     *   staff_id?: int|null,
     *   division_id?: int|null,
     *   document_type?: string|null,
     *   model_type?: string|null,
     *   year?: int|null,
     *   month?: int|null,
     *   q?: string|null,
     * }  $filters
     * @return list<array{label: string, period_key: string, avg_hours: float|null, count: int, is_current?: bool}>
     */
    public function averageHoursTrend(string $granularity, array $filters = []): array
    {
        $granularity = $granularity === 'weekly' ? 'weekly' : 'monthly';
        $query = ApproverDocumentTimingRecord::query();
        $this->applyTrendFilters($query, $filters);

        if ($granularity === 'weekly') {
            $rows = $query
                ->selectRaw('YEARWEEK(acted_at, 3) as period_key, AVG(hours_elapsed) as avg_hours, COUNT(*) as action_count')
                ->groupByRaw('YEARWEEK(acted_at, 3)')
                ->orderBy('period_key')
                ->get();
        } else {
            $rows = $query
                ->selectRaw('YEAR(acted_at) as period_year, MONTH(acted_at) as period_month, AVG(hours_elapsed) as avg_hours, COUNT(*) as action_count')
                ->groupByRaw('YEAR(acted_at), MONTH(acted_at)')
                ->orderByRaw('YEAR(acted_at), MONTH(acted_at)')
                ->get();
        }

        $points = [];
        foreach ($rows as $row) {
            if ($granularity === 'weekly') {
                $key = (string) (int) $row->period_key;
                $year = (int) floor((int) $row->period_key / 100);
                $week = (int) ((int) $row->period_key % 100);
                $label = 'W'.str_pad((string) $week, 2, '0', STR_PAD_LEFT).' '.$year;
            } else {
                $y = (int) $row->period_year;
                $m = (int) $row->period_month;
                $key = sprintf('%04d-%02d', $y, $m);
                $label = Carbon::create($y, $m, 1)->format('M Y');
            }

            $points[] = [
                'label' => $label,
                'period_key' => $key,
                'avg_hours' => round((float) $row->avg_hours, 2),
                'count' => (int) $row->action_count,
            ];
        }

        if (! isset($filters['year']) || $filters['year'] === null) {
            $limit = $granularity === 'weekly' ? 26 : 18;
            if (count($points) > $limit) {
                $points = array_slice($points, -$limit);
            }
        }

        return $this->markTrendCurrentPeriod($points, $granularity, $filters);
    }

    /**
     * @param  list<array{label: string, period_key: string, avg_hours: float|null, count: int, is_current?: bool}>  $points
     * @param  array<string, mixed>  $filters
     * @return list<array{label: string, period_key: string, avg_hours: float|null, count: int, is_current: bool}>
     */
    public function markTrendCurrentPeriod(array $points, string $granularity, array $filters = []): array
    {
        $granularity = $granularity === 'weekly' ? 'weekly' : 'monthly';
        $currentKey = $this->currentTrendPeriodKey($granularity);

        if ($this->shouldIncludeCurrentTrendPeriod($granularity, $filters)) {
            $hasCurrent = false;
            foreach ($points as $point) {
                if (($point['period_key'] ?? '') === $currentKey) {
                    $hasCurrent = true;
                    break;
                }
            }

            if (! $hasCurrent) {
                $now = Carbon::now();
                if ($granularity === 'weekly') {
                    $label = 'W'.str_pad((string) $now->isoWeek(), 2, '0', STR_PAD_LEFT).' '.$now->isoWeekYear();
                } else {
                    $label = $now->format('M Y');
                }

                $points[] = [
                    'label' => $label,
                    'period_key' => $currentKey,
                    'avg_hours' => null,
                    'count' => 0,
                ];
            }
        }

        usort($points, fn (array $a, array $b): int => strcmp((string) ($a['period_key'] ?? ''), (string) ($b['period_key'] ?? '')));

        return array_map(function (array $point) use ($currentKey): array {
            $point['is_current'] = ($point['period_key'] ?? '') === $currentKey;

            return $point;
        }, $points);
    }

    public function currentTrendPeriodKey(string $granularity): string
    {
        $now = Carbon::now();

        if ($granularity === 'weekly') {
            return sprintf('%d%02d', $now->isoWeekYear(), $now->isoWeek());
        }

        return $now->format('Y-m');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function shouldIncludeCurrentTrendPeriod(string $granularity, array $filters): bool
    {
        $now = Carbon::now();

        if (isset($filters['year']) && (int) $filters['year'] > 0 && (int) $filters['year'] !== (int) $now->year) {
            return false;
        }

        if ($granularity === 'monthly'
            && isset($filters['month'])
            && (int) $filters['month'] > 0
            && (int) $filters['month'] !== (int) $now->month) {
            return false;
        }

        return true;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ApproverDocumentTimingRecord>  $query
     * @param  array<string, mixed>  $filters
     */
    public function applyTrendFilters($query, array $filters): void
    {
        $staffId = isset($filters['staff_id']) && (int) $filters['staff_id'] > 0
            ? (int) $filters['staff_id']
            : null;
        $divisionId = isset($filters['division_id']) && (int) $filters['division_id'] > 0
            ? (int) $filters['division_id']
            : null;
        $documentType = ! empty($filters['document_type']) ? (string) $filters['document_type'] : null;
        $modelType = ! empty($filters['model_type']) ? (string) $filters['model_type'] : null;
        $year = isset($filters['year']) && (int) $filters['year'] > 0 ? (int) $filters['year'] : null;
        $month = isset($filters['month']) && (int) $filters['month'] > 0 ? (int) $filters['month'] : null;
        $search = ! empty($filters['q']) ? trim((string) $filters['q']) : null;

        $query
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
            ->when($documentType, fn ($q) => $q->where('document_type_label', $documentType))
            ->when($modelType, fn ($q) => $q->where('model_type', $modelType))
            ->when($year, fn ($q) => $q->whereYear('acted_at', $year))
            ->when($month, fn ($q) => $q->whereMonth('acted_at', $month))
            ->when($search, function ($q) use ($search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(function ($q2) use ($like): void {
                    $q2->where('document_title', 'like', $like)
                        ->orWhere('document_number_snapshot', 'like', $like)
                        ->orWhere('staff_name_snapshot', 'like', $like)
                        ->orWhere('workflow_role_snapshot', 'like', $like);
                });
            });
    }
}
