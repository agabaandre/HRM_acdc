<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Division;
use App\Models\FundCode;
use App\Models\ServiceRequest;

final class IntramuralSapBudgetExecutionService
{
    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(?int $year = null, ?int $divisionId = null, ?string $search = null): array
    {
        $year = $year ?: (int) date('Y');

        $query = FundCode::query()
            ->with(['division:id,division_name,division_short_name'])
            ->where('year', $year)
            ->where('is_active', 1)
            ->whereNotNull('code')
            ->where('code', '!=', '');

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', $term)
                    ->orWhere('activity', 'like', $term);
            });
        }

        $items = $query->orderBy('code')->get()->map(function (FundCode $fc) {
            $approved = $this->money($fc->approved_budget);
            $balance = $this->money($fc->budget_balance);

            return [
                'id' => (int) $fc->id,
                'code' => (string) $fc->code,
                'activity' => $fc->activity,
                'division_id' => $fc->division_id ? (int) $fc->division_id : null,
                'division_name' => $fc->division?->division_name,
                'approved_budget' => $approved,
                'budget_balance' => $balance,
                'execution_rate' => $approved > 0 ? (($approved - $balance) / $approved) : null,
                'uploaded_budget' => $this->money($fc->uploaded_budget),
            ];
        })->values()->all();

        $divisions = Division::query()
            ->orderBy('division_name')
            ->get(['id', 'division_name', 'division_short_name'])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'name' => $d->division_name,
                'short_name' => $d->division_short_name,
            ])
            ->values()
            ->all();

        return [
            'items' => $items,
            'meta' => [
                'year' => $year,
                'count' => count($items),
                'divisions' => $divisions,
            ],
        ];
    }

    /**
     * @return array{activities: list<array<string, mixed>>, service_requests: list<array<string, mixed>>}
     */
    public function documents(int $fundCodeId): array
    {
        $activities = Activity::query()
            ->with(['matrix:id,overall_status,year,quarter'])
            ->where('overall_status', 'approved')
            ->where(function ($q) use ($fundCodeId) {
                $q->whereJsonContains('budget_id', $fundCodeId)
                    ->orWhereJsonContains('budget_id', (string) $fundCodeId);
            })
            ->whereHas('matrix', fn ($m) => $m->where('overall_status', 'approved'))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => (int) $a->id,
                'type' => 'activity',
                'title' => $a->activity_title ?? $a->title ?? ('Activity #'.$a->id),
                'overall_status' => $a->overall_status,
                'matrix_id' => $a->matrix_id,
                'url' => $a->matrix_id
                    ? url('/matrices/'.$a->matrix_id)
                    : url('/single-memos/'.$a->id.'/status'),
            ])
            ->values()
            ->all();

        $serviceRequests = ServiceRequest::query()
            ->where('overall_status', 'approved')
            ->where(function ($q) use ($fundCodeId) {
                $q->whereJsonContains('budget_id', $fundCodeId)
                    ->orWhereJsonContains('budget_id', (string) $fundCodeId);
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ServiceRequest $sr) => [
                'id' => (int) $sr->id,
                'type' => 'service_request',
                'title' => $sr->activity_title ?? $sr->title ?? ('Service request #'.$sr->id),
                'overall_status' => $sr->overall_status,
                'url' => url('/service-requests/'.$sr->id),
            ])
            ->values()
            ->all();

        return [
            'activities' => $activities,
            'service_requests' => $serviceRequests,
        ];
    }

    private function money(mixed $value): float
    {
        $clean = str_replace([',', ' '], '', (string) ($value ?? '0'));

        return is_numeric($clean) ? (float) $clean : 0.0;
    }
}
