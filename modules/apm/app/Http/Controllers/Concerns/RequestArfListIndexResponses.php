<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\RequestARF;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait RequestArfListIndexResponses
{
    public function getRequestArfIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->requestArfStaffId();

            $paginator = $this->paginateRequestArfIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (RequestARF $arf, int $index) => $this->serializeRequestArfIndexRow(
                    $arf,
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
                'counts' => $this->requestArfIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveRequestArfYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Request ARF index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading ARF requests.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestArfIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->requestArfStaffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('request_arf_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('request_arf_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $tab = (string) $request->get('tab', 'mySubmitted');
        if (! in_array($tab, ['mySubmitted', 'myDivision', 'allArfs'], true)) {
            $tab = 'mySubmitted';
        }

        $statusDefault = (string) ($request->get('status') ?: $request->get('overall_status', ''));

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveRequestArfYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => $statusDefault,
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
            ],
            'counts' => $this->requestArfIndexTabCounts($request, $currentStaffId),
            'canViewAllArfs' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildRequestArfYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildRequestArfDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildRequestArfStaffSelectOptions($staff),
            'statusOptions' => $this->buildRequestArfStatusSelectOptions(),
            'fundTypeOptions' => $this->buildRequestArfFundTypeSelectOptions(),
            'routes' => [
                'ajax' => route('request-arf.ajax'),
                'exportMySubmitted' => route('request-arf.export.my-submitted'),
                'exportAll' => route('request-arf.export.all'),
            ],
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateRequestArfIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'mySubmitted' => $this->buildRequestArfMySubmittedQuery($request, $currentStaffId),
            'myDivision' => $this->buildRequestArfMyDivisionQuery($request),
            'allArfs' => in_array(87, user_session('permissions', []))
                ? $this->buildRequestArfAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,my_division:int,all_arfs:int}
     */
    protected function requestArfIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_submitted' => $this->paginateRequestArfIndexTab($request, $currentStaffId, 'mySubmitted', 1, 1)->total(),
            'my_division' => $this->paginateRequestArfIndexTab($request, $currentStaffId, 'myDivision', 1, 1)->total(),
            'all_arfs' => $this->paginateRequestArfIndexTab($request, $currentStaffId, 'allArfs', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeRequestArfIndexRow(RequestARF $arf, int $rowNum, int $currentStaffId): array
    {
        $status = (string) ($arf->overall_status ?? 'draft');
        $isOwner = (int) $arf->staff_id === $currentStaffId;

        $workflowRole = $arf->workflow_definition ? ($arf->workflow_definition->role ?? null) : null;
        $actorName = null;
        if ($arf->current_actor) {
            $actorName = trim(collect([
                $arf->current_actor->fname,
                $arf->current_actor->lname,
            ])->filter()->implode(' '));
        }

        return [
            'row_num' => $rowNum,
            'id' => $arf->id,
            'document_number' => $arf->document_number ?? $arf->arf_number,
            'title' => $arf->display_title,
            'staff_name' => $arf->staff->name ?? 'N/A',
            'division_name' => $arf->division->division_name ?? 'N/A',
            'created_at' => $arf->created_at?->format('M d, Y'),
            'overall_status' => $status,
            'approval_level' => $arf->approval_level,
            'workflow_role' => $workflowRole,
            'current_actor_name' => $actorName,
            'show_url' => route('request-arf.show', $arf->id),
            'edit_url' => ($isOwner && in_array($status, ['draft', 'returned'], true))
                ? route('request-arf.edit', $arf->id)
                : null,
            'delete_url' => ($isOwner && in_array($status, ['draft', 'returned'], true))
                ? route('request-arf.destroy', $arf->id)
                : null,
        ];
    }

    protected function buildRequestArfMySubmittedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = RequestARF::query()
            ->with([
                'staff',
                'division',
                'forwardWorkflow.workflowDefinitions.approvers.staff',
            ])
            ->where('staff_id', $currentStaffId);
        $this->applyRequestArfIndexFilters($q, $request);

        return $q;
    }

    protected function buildRequestArfMyDivisionQuery(Request $request): Builder
    {
        $q = RequestARF::query()->with([
            'staff',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId);
        } else {
            $q->whereRaw('1=0');
        }
        $q->where('overall_status', '!=', 'archived');
        $this->applyRequestArfIndexFilters($q, $request);

        return $q;
    }

    protected function buildRequestArfAllQuery(Request $request): Builder
    {
        $q = RequestARF::query()->with([
            'staff',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
        ]);
        $this->applyRequestArfIndexFilters($q, $request);

        return $q;
    }

    protected function applyRequestArfIndexFilters(Builder $query, Request $request): void
    {
        $year = $this->resolveRequestArfYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }

        if ($request->filled('document_number')) {
            $docNum = (string) $request->document_number;
            $query->where(function (Builder $q) use ($docNum) {
                $q->where('document_number', 'like', '%'.$docNum.'%')
                    ->orWhere('arf_number', 'like', '%'.$docNum.'%');
            });
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

        if ($request->filled('search')) {
            $term = trim((string) $request->search);
            if ($term !== '') {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where('activity_title', 'like', $like);
            }
        }

        MemoFundTypeFilter::apply($query, $request);
    }

    protected function resolveRequestArfYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '' || (is_numeric($year) && (int) $year === 0)) {
            $year = (string) $currentYear;
        }

        return (string) $year;
    }

    protected function requestArfStaffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildRequestArfYearSelectOptions(int $currentYear, int $minYear): array
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
    protected function buildRequestArfDivisionSelectOptions(iterable $divisions): array
    {
        $options = [['title' => 'All divisions', 'value' => '']];
        foreach ($divisions as $division) {
            $divisionId = $division->id ?? $division->division_id ?? null;
            if ($divisionId === null) {
                continue;
            }
            $options[] = [
                'title' => $division->division_name ?? '',
                'value' => (string) $divisionId,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildRequestArfStaffSelectOptions(iterable $staff): array
    {
        $options = [['title' => 'All staff', 'value' => '']];
        foreach ($staff as $member) {
            $staffId = $member->staff_id ?? $member->id ?? null;
            if ($staffId === null) {
                continue;
            }
            $label = trim(collect([
                $member->title,
                $member->fname,
                $member->lname,
            ])->filter()->implode(' '));
            $options[] = ['title' => $label, 'value' => (string) $staffId];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildRequestArfStatusSelectOptions(): array
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
    protected function buildRequestArfFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }
}
