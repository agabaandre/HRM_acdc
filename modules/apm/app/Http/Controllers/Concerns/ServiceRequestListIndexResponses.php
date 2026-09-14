<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\ServiceRequest;
use App\Models\Staff;
use App\Support\MemoFundTypeFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait ServiceRequestListIndexResponses
{
    public function getServiceRequestsIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->serviceRequestStaffId();

            $paginator = $this->paginateServiceRequestIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (ServiceRequest $sr, int $index) => $this->serializeServiceRequestIndexRow(
                    $sr,
                    $startIndex + $index + 1
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
                'counts' => $this->serviceRequestIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveServiceRequestYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Service requests index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading service requests.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildServiceRequestsIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->serviceRequestStaffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('service_requests_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('service_requests_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $tab = (string) $request->get('tab', 'mySubmitted');
        if (! in_array($tab, ['mySubmitted', 'myDivision', 'allRequests'], true)) {
            $tab = 'mySubmitted';
        }

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveServiceRequestYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) ($request->get('status') ?: $request->get('overall_status', '')),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
                'fund_type_id' => (string) MemoFundTypeFilter::selectedId($request),
                'service_type' => (string) $request->get('service_type', ''),
            ],
            'counts' => $this->serviceRequestIndexTabCounts($request, $currentStaffId),
            'canViewAllRequests' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildServiceRequestYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildServiceRequestDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildServiceRequestStaffSelectOptions($staff),
            'statusOptions' => $this->buildServiceRequestStatusSelectOptions(),
            'fundTypeOptions' => $this->buildServiceRequestFundTypeSelectOptions(),
            'serviceTypeOptions' => $this->buildServiceRequestServiceTypeSelectOptions(),
            'routes' => [
                'ajax' => route('service-requests.ajax'),
                'exportMySubmitted' => route('service-requests.export.my-submitted'),
                'exportAll' => route('service-requests.export.all'),
            ],
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateServiceRequestIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'mySubmitted' => $this->buildServiceRequestMySubmittedQuery($request, $currentStaffId),
            'myDivision' => $this->buildServiceRequestMyDivisionQuery($request),
            'allRequests' => in_array(87, user_session('permissions', []))
                ? $this->buildServiceRequestAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,my_division:int,all_requests:int}
     */
    protected function serviceRequestIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_submitted' => $this->paginateServiceRequestIndexTab($request, $currentStaffId, 'mySubmitted', 1, 1)->total(),
            'my_division' => $this->paginateServiceRequestIndexTab($request, $currentStaffId, 'myDivision', 1, 1)->total(),
            'all_requests' => $this->paginateServiceRequestIndexTab($request, $currentStaffId, 'allRequests', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeServiceRequestIndexRow(ServiceRequest $sr, int $rowNum): array
    {
        $status = (string) ($sr->overall_status ?? 'draft');
        $canEditDelete = in_array($status, ['draft', 'returned'], true);

        $responsibleName = null;
        if ($sr->responsiblePerson) {
            $responsibleName = trim(collect([$sr->responsiblePerson->fname, $sr->responsiblePerson->lname])->filter()->implode(' '));
        }

        $workflowRole = $sr->workflowDefinition->role ?? null;
        $actorName = null;
        if ($sr->current_actor ?? null) {
            $actorName = trim(collect([$sr->current_actor->fname ?? '', $sr->current_actor->lname ?? ''])->filter()->implode(' '));
        }

        return [
            'row_num' => $rowNum,
            'id' => $sr->id,
            'document_number' => $sr->document_number,
            'service_title' => strip_tags((string) ($sr->service_title ?: $sr->title)),
            'responsible_person_name' => $responsibleName ?? 'N/A',
            'division_name' => $sr->division->division_name ?? 'N/A',
            'created_at' => $sr->created_at?->format('M d, Y'),
            'overall_status' => $status,
            'approval_level' => $sr->approval_level,
            'workflow_role' => $workflowRole,
            'current_actor_name' => $actorName,
            'show_url' => route('service-requests.show', $sr->id),
            'edit_url' => $canEditDelete ? route('service-requests.edit', $sr->id) : null,
            'delete_url' => $canEditDelete ? route('service-requests.destroy', $sr->id) : null,
        ];
    }

    protected function buildServiceRequestMySubmittedQuery(Request $request, int $currentStaffId): Builder
    {
        $q = ServiceRequest::query()->with(['staff', 'responsiblePerson', 'division', 'workflowDefinition'])
            ->where('staff_id', $currentStaffId);
        $this->applyServiceRequestIndexFilters($q, $request);

        return $q;
    }

    protected function buildServiceRequestMyDivisionQuery(Request $request): Builder
    {
        $q = ServiceRequest::query()->with(['staff', 'responsiblePerson', 'division', 'workflowDefinition']);
        $divisionId = (int) (user_session('division_id') ?? 0);
        if ($divisionId > 0) {
            $q->where('division_id', $divisionId)->where('overall_status', '!=', 'archived');
        } else {
            $q->whereRaw('1=0');
        }
        $this->applyServiceRequestIndexFilters($q, $request);

        return $q;
    }

    protected function buildServiceRequestAllQuery(Request $request): Builder
    {
        $q = ServiceRequest::query()->with(['staff', 'responsiblePerson', 'division', 'workflowDefinition']);
        $this->applyServiceRequestIndexFilters($q, $request);

        return $q;
    }

    protected function applyServiceRequestIndexFilters(Builder $query, Request $request): void
    {
        $year = $this->resolveServiceRequestYearString($request);
        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $query->whereYear('created_at', (int) $year);
        }
        if ($request->filled('staff_id')) {
            $query->where('responsible_person_id', (int) $request->staff_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->division_id);
        }
        if ($request->filled('service_type')) {
            $query->where('service_type', (string) $request->service_type);
        }
        $status = $request->filled('status')
            ? (string) $request->status
            : ($request->filled('overall_status') ? (string) $request->overall_status : '');
        if ($status !== '') {
            $query->where('overall_status', $status);
        }
        if ($request->filled('document_number')) {
            $docNum = (string) $request->document_number;
            $query->where(function (Builder $q) use ($docNum) {
                $q->where('document_number', 'like', '%'.$docNum.'%')
                    ->orWhere('request_number', 'like', '%'.$docNum.'%');
            });
        }
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function (Builder $q) use ($search) {
                $q->where('service_title', 'like', '%'.$search.'%')
                    ->orWhere('title', 'like', '%'.$search.'%')
                    ->orWhere('request_number', 'like', '%'.$search.'%')
                    ->orWhere('document_number', 'like', '%'.$search.'%');
            });
        }
        MemoFundTypeFilter::apply($query, $request);
    }

    protected function resolveServiceRequestYearString(Request $request): string
    {
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '' || (is_numeric($year) && (int) $year === 0)) {
            $year = (string) $currentYear;
        }

        return (string) $year;
    }

    protected function serviceRequestStaffId(): int
    {
        return (int) user_session('staff_id', 0);
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildServiceRequestYearSelectOptions(int $currentYear, int $minYear): array
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
    protected function buildServiceRequestDivisionSelectOptions(iterable $divisions): array
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
    protected function buildServiceRequestStaffSelectOptions(iterable $staff): array
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
    protected function buildServiceRequestStatusSelectOptions(): array
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
    protected function buildServiceRequestFundTypeSelectOptions(): array
    {
        $options = [['title' => 'All fund types', 'value' => '']];
        foreach (MemoFundTypeFilter::options() as $id => $label) {
            $options[] = ['title' => $label, 'value' => (string) $id];
        }

        return $options;
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildServiceRequestServiceTypeSelectOptions(): array
    {
        return [
            ['title' => 'All types', 'value' => ''],
            ['title' => 'IT Support', 'value' => 'IT Support'],
            ['title' => 'Maintenance', 'value' => 'Maintenance'],
            ['title' => 'Other', 'value' => 'Other'],
        ];
    }
}
