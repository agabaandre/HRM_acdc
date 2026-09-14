<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ChangeRequest;
use App\Models\Division;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait ChangeRequestListIndexResponses
{
    public function getChangeRequestsIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'myChangeRequests');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->changeRequestStaffId();

            $paginator = $this->paginateChangeRequestIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (ChangeRequest $cr, int $index) => $this->serializeChangeRequestIndexRow(
                    $cr,
                    $startIndex + $index + 1,
                    $currentStaffId
                ));

            return response()->json([
                'data' => $items,
                'pagination' => [
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem() ?? 0,
                    'to' => $paginator->lastItem() ?? 0,
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                ],
                'counts' => $this->changeRequestIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveChangeRequestYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Change requests index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading change requests.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildChangeRequestsIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->changeRequestStaffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('change_requests_index_staff', 60 * 60, fn () => Staff::orderBy('fname')->orderBy('lname')->get());
        $divisions = Cache::remember('change_requests_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $tab = (string) $request->get('tab', 'myChangeRequests');
        if (! in_array($tab, ['myChangeRequests', 'myDivisionChangeRequests', 'sharedChangeRequests', 'allChangeRequests'], true)) {
            $tab = 'myChangeRequests';
        }

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveChangeRequestYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) ($request->get('status') ?: 'all'),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'memo_type' => (string) $request->get('memo_type', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
            ],
            'counts' => $this->changeRequestIndexTabCounts($request, $currentStaffId),
            'canViewAllChangeRequests' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildChangeRequestYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildChangeRequestDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildChangeRequestStaffSelectOptions($staff),
            'statusOptions' => $this->buildChangeRequestStatusSelectOptions(),
            'memoTypeOptions' => $this->buildChangeRequestMemoTypeSelectOptions(),
            'fundTypeOptions' => $this->buildChangeRequestFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('change-requests.ajax'),
                'pendingApprovals' => route('change-requests.pending-approvals'),
            ],
            'pendingApprovalCount' => function_exists('get_pending_change_request_count')
                ? get_pending_change_request_count((int) $currentStaffId)
                : 0,
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateChangeRequestIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'myChangeRequests' => $this->buildChangeRequestMyQuery($request, $currentStaffId),
            'myDivisionChangeRequests' => $this->buildChangeRequestMyDivisionQuery($request, $currentStaffId),
            'sharedChangeRequests' => $this->buildChangeRequestSharedQuery($request, $currentStaffId),
            'allChangeRequests' => in_array(87, user_session('permissions', []))
                ? $this->buildChangeRequestAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_change_requests:int,my_division:int,shared:int,all:int}
     */
    protected function changeRequestIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_change_requests' => $this->paginateChangeRequestIndexTab($request, $currentStaffId, 'myChangeRequests', 1, 1)->total(),
            'my_division' => $this->paginateChangeRequestIndexTab($request, $currentStaffId, 'myDivisionChangeRequests', 1, 1)->total(),
            'shared' => $this->paginateChangeRequestIndexTab($request, $currentStaffId, 'sharedChangeRequests', 1, 1)->total(),
            'all' => $this->paginateChangeRequestIndexTab($request, $currentStaffId, 'allChangeRequests', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeChangeRequestIndexRow(ChangeRequest $cr, int $rowNum, int $currentStaffId): array
    {
        $status = (string) ($cr->overall_status ?? 'draft');
        $statusMeta = in_array($status, ['submitted', 'pending', 'returned'], true) ? $cr->memoIndexStatusMeta() : null;

        $isNonTravel = $cr->parent_memo_model === 'App\Models\NonTravelMemo';
        $dateRange = null;
        if ($isNonTravel && $cr->memo_date) {
            $dateRange = \Carbon\Carbon::parse($cr->memo_date)->format('M d, Y');
        } elseif ($cr->date_from && $cr->date_to) {
            $dateRange = \Carbon\Carbon::parse($cr->date_from)->format('M d').' – '.\Carbon\Carbon::parse($cr->date_to)->format('M d, Y');
        }

        $canEdit = $currentStaffId > 0
            && $cr->workflowAllowsSubmitterParentMemoEdit()
            && $cr->isOwnedResponsibleOrEffectiveDivisionHeadByStaffId($currentStaffId);
        $canDelete = $currentStaffId > 0
            && in_array($status, ['draft', 'rejected'], true)
            && ((int) $cr->staff_id === $currentStaffId || (int) $cr->responsible_person_id === $currentStaffId);

        return [
            'row_num' => $rowNum,
            'id' => $cr->id,
            'document_number' => $cr->document_number ?: 'Pending',
            'division_name' => $cr->division->division_name ?? null,
            'title' => strip_tags((string) $cr->activity_title),
            'supporting_reasons_preview' => $cr->supporting_reasons
                ? Str::limit(strip_tags((string) $cr->supporting_reasons), 120)
                : null,
            'parent_memo_model' => $cr->parent_memo_model ? class_basename($cr->parent_memo_model) : null,
            'parent_memo_document_number' => $cr->parent_memo_document_number,
            'parent_memo_url' => $cr->parent_memo_url,
            'date_range' => $dateRange,
            'change_labels' => $cr->changes_summary ?? [],
            'overall_status' => $status,
            'status_level' => $statusMeta['level'] ?? null,
            'workflow_role' => $statusMeta['role'] ?? null,
            'current_actor_name' => ($statusMeta['actor_name'] ?? null) !== 'N/A' ? ($statusMeta['actor_name'] ?? null) : null,
            'show_url' => route('change-requests.show', $cr->id),
            'edit_url' => $canEdit ? route('change-requests.edit', $cr->id) : null,
            'delete_url' => $canDelete ? route('change-requests.destroy', $cr->id) : null,
        ];
    }

    protected function buildChangeRequestBaseQuery(Request $request): Builder
    {
        $q = ChangeRequest::query()->with([
            'staff', 'responsiblePerson', 'division.divisionHead', 'requestType', 'fundType', 'parentMemo',
        ]);

        $this->applyChangeRequestIndexFilters($q, $request);

        return $q;
    }

    protected function buildChangeRequestMyQuery(Request $request, int $currentStaffId): Builder
    {
        $q = $this->buildChangeRequestBaseQuery($request);
        if ($currentStaffId > 0) {
            $q->where('staff_id', $currentStaffId);
        }

        return $q;
    }

    protected function buildChangeRequestMyDivisionQuery(Request $request, int $currentStaffId): Builder
    {
        $q = $this->buildChangeRequestBaseQuery($request);
        if ($currentStaffId <= 0) {
            return $q;
        }
        if ($request->filled('division_id') && (int) $request->division_id > 0) {
            return $q;
        }
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId);
        } else {
            $q->whereRaw('1=0');
        }

        return $q;
    }

    protected function buildChangeRequestSharedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = $this->buildChangeRequestBaseQuery($request);
        if ($currentStaffId > 0) {
            $q->where('responsible_person_id', $currentStaffId);
        }

        return $q;
    }

    protected function buildChangeRequestAllQuery(Request $request): Builder
    {
        return $this->buildChangeRequestBaseQuery($request);
    }

    protected function applyChangeRequestIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%'.$request->document_number.'%');
        }
        if ($request->filled('staff_id')) {
            $staffId = (int) $request->staff_id;
            $query->where(function (Builder $q) use ($staffId) {
                $q->where('staff_id', $staffId)->orWhere('responsible_person_id', $staffId);
            });
        }
        $year = $this->resolveChangeRequestYearString($request);
        if ($year !== 'all' && $year !== '' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }
        $status = (string) ($request->get('status') ?: 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('overall_status', $status);
        }
        if ($request->filled('memo_type')) {
            $query->where('parent_memo_model', (string) $request->memo_type);
        }
        if ($request->filled('division_id') && (int) $request->division_id > 0 && user_session('staff_id')) {
            $query->where('division_id', (int) $request->division_id);
        }
        if ($request->filled('search')) {
            $query->where('activity_title', 'like', '%'.$request->search.'%');
        }
        MemoFundTypeFilter::apply($query, $request);
    }

    protected function resolveChangeRequestYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '' || (is_numeric($year) && (int) $year === 0)) {
            $year = (string) $currentYear;
        }

        return (string) $year;
    }

    protected function changeRequestStaffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestYearSelectOptions(int $currentYear, int $minYear): array
    {
        $options = [['title' => 'All years', 'value' => 'all']];
        foreach (range($currentYear, $minYear) as $year) {
            $options[] = ['title' => (string) $year, 'value' => (string) $year];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestDivisionSelectOptions(iterable $divisions): array
    {
        $options = [['title' => 'All divisions', 'value' => '']];
        foreach ($divisions as $division) {
            $divisionId = $division->id ?? $division->division_id ?? null;
            if ($divisionId === null) {
                continue;
            }
            $options[] = ['title' => $division->division_name ?? '', 'value' => (string) $divisionId];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestStaffSelectOptions(iterable $staff): array
    {
        $options = [['title' => 'All staff', 'value' => '']];
        foreach ($staff as $member) {
            $staffId = $member->staff_id ?? $member->id ?? null;
            if ($staffId === null) {
                continue;
            }
            $label = trim(collect([$member->title, $member->fname, $member->lname])->filter()->implode(' '));
            $options[] = ['title' => $label, 'value' => (string) $staffId];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestStatusSelectOptions(): array
    {
        return [
            ['title' => 'All statuses', 'value' => 'all'],
            ['title' => 'Draft', 'value' => 'draft'],
            ['title' => 'Submitted', 'value' => 'submitted'],
            ['title' => 'Pending', 'value' => 'pending'],
            ['title' => 'Approved', 'value' => 'approved'],
            ['title' => 'Returned', 'value' => 'returned'],
            ['title' => 'Rejected', 'value' => 'rejected'],
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestMemoTypeSelectOptions(): array
    {
        return [
            ['title' => 'All memo types', 'value' => ''],
            ['title' => 'Activity', 'value' => 'App\Models\Activity'],
            ['title' => 'Special memo', 'value' => 'App\Models\SpecialMemo'],
            ['title' => 'Non-travel memo', 'value' => 'App\Models\NonTravelMemo'],
            ['title' => 'Request ARF', 'value' => 'App\Models\RequestArf'],
            ['title' => 'Service request', 'value' => 'App\Models\ServiceRequest'],
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildChangeRequestFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }
}
