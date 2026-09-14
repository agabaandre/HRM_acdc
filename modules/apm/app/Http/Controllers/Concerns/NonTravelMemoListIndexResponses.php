<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\NonTravelMemo;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait NonTravelMemoListIndexResponses
{
    public function getNonTravelMemosIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->nonTravelMemoStaffId();

            $paginator = $this->paginateNonTravelMemoIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (NonTravelMemo $memo, int $index) => $this->serializeNonTravelMemoIndexRow(
                    $memo,
                    $startIndex + $index + 1,
                    $currentStaffId,
                    $tab
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
                'counts' => $this->nonTravelMemoIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveNonTravelMemoYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Non-travel memos index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading non-travel memos.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildNonTravelMemosIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->nonTravelMemoStaffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('non_travel_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('non_travel_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $tab = (string) $request->get('tab', 'mySubmitted');
        if (! in_array($tab, ['mySubmitted', 'myDivision', 'allMemos'], true)) {
            $tab = 'mySubmitted';
        }

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveNonTravelMemoYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) ($request->get('status') ?: $request->get('overall_status', '')),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
            ],
            'counts' => $this->nonTravelMemoIndexTabCounts($request, $currentStaffId),
            'canViewAllMemos' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildNonTravelMemoYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildNonTravelMemoDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildNonTravelMemoStaffSelectOptions($staff),
            'statusOptions' => $this->buildNonTravelMemoStatusSelectOptions(),
            'fundTypeOptions' => $this->buildNonTravelMemoFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('non-travel.ajax'),
                'create' => route('non-travel.create'),
                'exportMySubmitted' => route('non-travel.export.my-submitted'),
                'exportAll' => route('non-travel.export.all'),
            ],
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateNonTravelMemoIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'mySubmitted' => $this->buildNonTravelMemoMySubmittedQuery($request, $currentStaffId),
            'myDivision' => $this->buildNonTravelMemoMyDivisionQuery($request),
            'allMemos' => in_array(87, user_session('permissions', []))
                ? $this->buildNonTravelMemoAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,my_division:int,all_memos:int}
     */
    protected function nonTravelMemoIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_submitted' => $this->paginateNonTravelMemoIndexTab($request, $currentStaffId, 'mySubmitted', 1, 1)->total(),
            'my_division' => $this->paginateNonTravelMemoIndexTab($request, $currentStaffId, 'myDivision', 1, 1)->total(),
            'all_memos' => $this->paginateNonTravelMemoIndexTab($request, $currentStaffId, 'allMemos', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeNonTravelMemoIndexRow(NonTravelMemo $memo, int $rowNum, int $currentStaffId, string $tab): array
    {
        $status = (string) ($memo->overall_status ?? 'draft');
        $isOwner = (int) $memo->staff_id === $currentStaffId;
        $statusMeta = in_array($status, ['pending', 'returned'], true) ? $memo->memoIndexStatusMeta() : null;

        $staffName = null;
        if ($memo->staff) {
            $staffName = trim(collect([$memo->staff->fname, $memo->staff->lname])->filter()->implode(' '));
        }

        $canEditDelete = $isOwner && in_array($status, ['draft', 'returned'], true);
        $canCopy = function_exists('can_copy_memo') && can_copy_memo($memo);

        return [
            'row_num' => $rowNum,
            'id' => $memo->id,
            'document_number' => $memo->document_number,
            'title' => strip_tags((string) $memo->activity_title),
            'category_name' => $memo->nonTravelMemoCategory->name ?? null,
            'staff_name' => $staffName,
            'division_name' => $memo->division->division_name ?? 'N/A',
            'fund_type_name' => $memo->fundType->name ?? 'N/A',
            'memo_date' => $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('M d, Y') : 'N/A',
            'overall_status' => $status,
            'status_level' => $statusMeta['level'] ?? null,
            'workflow_role' => $statusMeta['role'] ?? null,
            'current_actor_name' => ($statusMeta['actor_name'] ?? null) !== 'N/A' ? ($statusMeta['actor_name'] ?? null) : null,
            'show_url' => route('non-travel.show', $memo->id),
            'copy_url' => $canCopy ? route('non-travel.copy', $memo->id) : null,
            'edit_url' => $canEditDelete ? route('non-travel.edit', $memo->id) : null,
            'delete_url' => ($canEditDelete && in_array($tab, ['mySubmitted', 'allMemos'], true))
                ? route('non-travel.destroy', $memo->id)
                : null,
            'print_url' => $status === 'approved' ? route('non-travel.print', $memo->id) : null,
        ];
    }

    protected function buildNonTravelMemoMySubmittedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = NonTravelMemo::query()->with([
            'staff', 'division.divisionHead', 'nonTravelMemoCategory', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ])->where('staff_id', $currentStaffId);
        $this->applyNonTravelMemoIndexYearFilter($q, $request);
        $this->applyNonTravelMemoIndexFilters($q, $request);

        return $q;
    }

    protected function buildNonTravelMemoMyDivisionQuery(Request $request): Builder
    {
        $q = NonTravelMemo::query()->with([
            'staff', 'division.divisionHead', 'nonTravelMemoCategory', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId);
        } else {
            $q->whereRaw('1=0');
        }
        $q->where('overall_status', '!=', 'archived');
        $this->applyNonTravelMemoIndexYearFilter($q, $request);
        $this->applyNonTravelMemoIndexFilters($q, $request);

        return $q;
    }

    protected function buildNonTravelMemoAllQuery(Request $request): Builder
    {
        $q = NonTravelMemo::query()->with([
            'staff', 'division.divisionHead', 'nonTravelMemoCategory', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $this->applyNonTravelMemoIndexYearFilter($q, $request);
        $this->applyNonTravelMemoIndexFilters($q, $request);

        return $q;
    }

    protected function applyNonTravelMemoIndexYearFilter(Builder $query, Request $request): void
    {
        $year = $this->resolveNonTravelMemoYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('memo_date', (int) $year);
        }
    }

    protected function applyNonTravelMemoIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('category_id')) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->division_id);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', (int) $request->staff_id);
        }
        $status = $request->filled('status')
            ? (string) $request->status
            : ($request->filled('overall_status') ? (string) $request->overall_status : '');
        if ($status !== '') {
            $query->where('overall_status', $status);
        }
        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%'.$request->document_number.'%');
        }
        if ($request->filled('search')) {
            $query->where('activity_title', 'like', '%'.$request->search.'%');
        }
        MemoFundTypeFilter::apply($query, $request);
    }

    protected function resolveNonTravelMemoYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '' || (is_numeric($year) && (int) $year === 0)) {
            $year = (string) $currentYear;
        }

        return (string) $year;
    }

    protected function nonTravelMemoStaffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildNonTravelMemoYearSelectOptions(int $currentYear, int $minYear): array
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
    protected function buildNonTravelMemoDivisionSelectOptions(iterable $divisions): array
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
    protected function buildNonTravelMemoStaffSelectOptions(iterable $staff): array
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
    protected function buildNonTravelMemoStatusSelectOptions(): array
    {
        return [
            ['title' => 'All statuses', 'value' => ''],
            ['title' => 'Draft', 'value' => 'draft'],
            ['title' => 'Pending', 'value' => 'pending'],
            ['title' => 'Approved', 'value' => 'approved'],
            ['title' => 'Returned', 'value' => 'returned'],
            ['title' => 'Rejected', 'value' => 'rejected'],
            ['title' => 'Archived', 'value' => 'archived'],
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildNonTravelMemoFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }
}
