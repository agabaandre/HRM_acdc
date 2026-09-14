<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\RequestType;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait SpecialMemoListIndexResponses
{
    public function getSpecialMemosIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->specialMemoStaffId();

            $paginator = $this->paginateSpecialMemoIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (SpecialMemo $memo, int $index) => $this->serializeSpecialMemoIndexRow(
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
                'counts' => $this->specialMemoIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveSpecialMemoYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Special memos index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading special memos.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSpecialMemosIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->specialMemoStaffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('special_memo_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('special_memo_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());
        $requestTypes = Cache::remember('special_memo_index_request_types', 60 * 60, fn () => RequestType::query()->orderBy('name')->get());

        $tab = (string) $request->get('tab', 'mySubmitted');
        if (! in_array($tab, ['mySubmitted', 'myDivision', 'sharedMemos', 'allMemos'], true)) {
            $tab = 'mySubmitted';
        }

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveSpecialMemoYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) ($request->get('status') ?: $request->get('overall_status', '')),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'request_type_id' => (string) $request->get('request_type_id', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
            ],
            'counts' => $this->specialMemoIndexTabCounts($request, $currentStaffId),
            'canViewAllMemos' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildSpecialMemoYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildSpecialMemoDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildSpecialMemoStaffSelectOptions($staff),
            'statusOptions' => $this->buildSpecialMemoStatusSelectOptions(),
            'requestTypeOptions' => $this->buildSpecialMemoRequestTypeSelectOptions($requestTypes),
            'fundTypeOptions' => $this->buildSpecialMemoFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('special-memo.ajax'),
                'create' => route('special-memo.create'),
                'pendingApprovals' => route('special-memo.pending-approvals'),
                'exportMySubmitted' => route('special-memo.export.my-submitted'),
                'exportAll' => route('special-memo.export.all'),
                'exportShared' => route('special-memo.export.shared'),
            ],
            'pendingApprovalCount' => function_exists('get_staff_pending_action_count')
                ? get_staff_pending_action_count('special-memo')
                : 0,
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateSpecialMemoIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'mySubmitted' => $this->buildSpecialMemoMySubmittedQuery($request, $currentStaffId),
            'myDivision' => $this->buildSpecialMemoMyDivisionQuery($request),
            'sharedMemos' => $this->buildSpecialMemoSharedQuery($request, $currentStaffId),
            'allMemos' => in_array(87, user_session('permissions', []))
                ? $this->buildSpecialMemoAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,my_division:int,all_memos:int,shared_memos:int}
     */
    protected function specialMemoIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_submitted' => $this->paginateSpecialMemoIndexTab($request, $currentStaffId, 'mySubmitted', 1, 1)->total(),
            'my_division' => $this->paginateSpecialMemoIndexTab($request, $currentStaffId, 'myDivision', 1, 1)->total(),
            'all_memos' => $this->paginateSpecialMemoIndexTab($request, $currentStaffId, 'allMemos', 1, 1)->total(),
            'shared_memos' => $this->paginateSpecialMemoIndexTab($request, $currentStaffId, 'sharedMemos', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeSpecialMemoIndexRow(SpecialMemo $memo, int $rowNum, int $currentStaffId, string $tab): array
    {
        $status = (string) ($memo->overall_status ?? 'draft');
        $statusMeta = in_array($status, ['pending', 'returned'], true) ? $memo->memoIndexStatusMeta() : null;

        $fundCodes = $memo->fundCodes ?? collect();
        $budgetCodeLabels = $fundCodes->isNotEmpty()
            ? $fundCodes->pluck('code')->filter()->unique()->values()->all()
            : [];

        $responsibleName = null;
        if ($memo->responsiblePerson) {
            $responsibleName = trim(collect([$memo->responsiblePerson->fname, $memo->responsiblePerson->lname])->filter()->implode(' '));
        } elseif ($memo->staff) {
            $responsibleName = trim(collect([$memo->staff->fname, $memo->staff->lname])->filter()->implode(' '));
        }

        $canCopy = function_exists('can_copy_memo') && can_copy_memo($memo);
        $editUrl = null;
        $deleteUrl = null;

        if ($tab === 'mySubmitted' && (int) $memo->staff_id === $currentStaffId && in_array($status, ['draft', 'returned'], true)) {
            $editUrl = route('special-memo.edit', $memo->id);
            $deleteUrl = route('special-memo.destroy', $memo->id);
        } elseif (in_array($tab, ['myDivision', 'allMemos'], true)
            && (int) $memo->responsible_person_id === $currentStaffId
            && in_array($status, ['draft', 'returned'], true)) {
            $editUrl = route('special-memo.edit', $memo->id);
            $deleteUrl = route('special-memo.destroy', $memo->id);
        }

        $dateRange = null;
        if ($memo->date_from && $memo->date_to) {
            $from = \Carbon\Carbon::parse($memo->date_from)->format('M d, Y');
            $to = \Carbon\Carbon::parse($memo->date_to)->format('M d, Y');
            $dateRange = $from.' – '.$to;
        } elseif ($memo->date_from) {
            $dateRange = \Carbon\Carbon::parse($memo->date_from)->format('M d, Y');
        }

        return [
            'row_num' => $rowNum,
            'id' => $memo->id,
            'document_number' => $memo->document_number,
            'title' => strip_tags((string) $memo->activity_title),
            'request_type_name' => $memo->requestType->name ?? 'N/A',
            'responsible_person_name' => $responsibleName,
            'division_name' => $memo->division->division_name ?? 'N/A',
            'fund_type_name' => $memo->fundType->name ?? 'N/A',
            'fund_code_labels' => $budgetCodeLabels,
            'date_range' => $dateRange,
            'overall_status' => $status,
            'status_level' => $statusMeta['level'] ?? null,
            'workflow_role' => $statusMeta['role'] ?? null,
            'current_actor_name' => ($statusMeta['actor_name'] ?? null) !== 'N/A' ? ($statusMeta['actor_name'] ?? null) : null,
            'show_url' => route('special-memo.show', $memo->id),
            'copy_url' => $canCopy ? route('special-memo.copy', $memo->id) : null,
            'edit_url' => $editUrl,
            'delete_url' => $deleteUrl,
            'print_url' => $status === 'approved' ? route('special-memo.print', $memo->id) : null,
        ];
    }

    protected function buildSpecialMemoMySubmittedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = SpecialMemo::query()->with([
            'staff', 'responsiblePerson', 'division.divisionHead', 'requestType', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ])->where(function (Builder $query) use ($currentStaffId) {
            $query->where('staff_id', $currentStaffId)
                ->orWhere('responsible_person_id', $currentStaffId);
        });
        $this->applySpecialMemoIndexYearFilter($q, $request);
        $this->applySpecialMemoIndexFilters($q, $request);

        return $q;
    }

    protected function buildSpecialMemoMyDivisionQuery(Request $request): Builder
    {
        $q = SpecialMemo::query()->with([
            'staff', 'responsiblePerson', 'division.divisionHead', 'requestType', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId);
        } else {
            $q->whereRaw('1=0');
        }
        $q->where('overall_status', '!=', 'archived');
        $this->applySpecialMemoIndexYearFilter($q, $request);
        $this->applySpecialMemoIndexFilters($q, $request);

        return $q;
    }

    protected function buildSpecialMemoSharedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = SpecialMemo::query()->with([
            'staff', 'responsiblePerson', 'division.divisionHead', 'requestType', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ])
            ->where('staff_id', '!=', $currentStaffId)
            ->whereJsonContains('internal_participants', $currentStaffId)
            ->where('overall_status', '!=', 'archived');
        $this->applySpecialMemoIndexYearFilter($q, $request);
        $this->applySpecialMemoIndexFilters($q, $request);

        return $q;
    }

    protected function buildSpecialMemoAllQuery(Request $request): Builder
    {
        $q = SpecialMemo::query()->with([
            'staff', 'responsiblePerson', 'division.divisionHead', 'requestType', 'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $this->applySpecialMemoIndexYearFilter($q, $request);
        $this->applySpecialMemoIndexFilters($q, $request);

        return $q;
    }

    protected function applySpecialMemoIndexYearFilter(Builder $query, Request $request): void
    {
        $year = $this->resolveSpecialMemoYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }
    }

    protected function applySpecialMemoIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('request_type_id')) {
            $query->where('request_type_id', (int) $request->request_type_id);
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

    protected function resolveSpecialMemoYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '' || (is_numeric($year) && (int) $year === 0)) {
            $year = (string) $currentYear;
        }

        return (string) $year;
    }

    protected function specialMemoStaffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildSpecialMemoYearSelectOptions(int $currentYear, int $minYear): array
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
    protected function buildSpecialMemoDivisionSelectOptions(iterable $divisions): array
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
    protected function buildSpecialMemoStaffSelectOptions(iterable $staff): array
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
    protected function buildSpecialMemoStatusSelectOptions(): array
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
    protected function buildSpecialMemoRequestTypeSelectOptions(iterable $requestTypes): array
    {
        $options = [['title' => 'All request types', 'value' => '']];
        foreach ($requestTypes as $type) {
            $options[] = ['title' => $type->name ?? '', 'value' => (string) ($type->id ?? '')];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildSpecialMemoFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }
}
