<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Division;
use App\Models\OtherMemo;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait OtherMemoListIndexResponses
{
    public function getOtherMemosIndexAjax(Request $request): JsonResponse
    {
        try {
            $tab = (string) $request->get('tab', 'mySubmitted');
            $page = max(1, (int) $request->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->get('pageSize', 20)));
            $currentStaffId = $this->staffId();

            $paginator = $this->paginateOtherMemoIndexTab($request, $currentStaffId, $tab, $page, $pageSize);
            $startIndex = ($paginator->currentPage() - 1) * $paginator->perPage();

            $items = $paginator->getCollection()
                ->values()
                ->map(fn (OtherMemo $memo, int $index) => $this->serializeOtherMemoIndexRow(
                    $memo,
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
                'counts' => $this->otherMemoIndexTabCounts($request, $currentStaffId),
                'year_applied' => $this->resolveOtherMemoYearString($request),
            ]);
        } catch (\Throwable $e) {
            Log::error('Other memos index AJAX error: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while loading other memos.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildOtherMemosIndexPageConfig(Request $request): array
    {
        $currentStaffId = $this->staffId();
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $canViewAll = in_array(87, user_session('permissions', []));

        $staff = Cache::remember('other_memos_index_staff', 60 * 60, fn () => Staff::active()->orderBy('lname')->orderBy('fname')->get());
        $divisions = Cache::remember('other_memos_index_divisions', 60 * 60, fn () => Division::query()->orderBy('division_name')->get());

        $tab = (string) $request->get('tab', 'mySubmitted');
        if (! in_array($tab, ['mySubmitted', 'myDivision', 'allMemos'], true)) {
            $tab = 'mySubmitted';
        }

        return [
            'currentYear' => $currentYear,
            'defaults' => [
                'tab' => $tab,
                'year' => $this->resolveOtherMemoYearString($request),
                'division_id' => (string) $request->get('division_id', ''),
                'staff_id' => (string) $request->get('staff_id', ''),
                'status' => (string) $request->get('status', ''),
                'document_number' => (string) $request->get('document_number', ''),
                'search' => (string) $request->get('search', ''),
            ],
            'counts' => $this->otherMemoIndexTabCounts($request, $currentStaffId),
            'canViewAllMemos' => $canViewAll,
            'perPage' => 20,
            'yearOptions' => $this->buildOtherMemoYearSelectOptions($currentYear, $minYear),
            'divisionOptions' => $this->buildOtherMemoDivisionSelectOptions($divisions),
            'staffOptions' => $this->buildOtherMemoStaffSelectOptions($staff),
            'statusOptions' => $this->buildOtherMemoStatusSelectOptions(),
            'routes' => [
                'ajax' => route('other-memos.ajax'),
                'create' => route('other-memos.create'),
            ],
            'csrf' => csrf_token(),
        ];
    }

    protected function paginateOtherMemoIndexTab(
        Request $request,
        int $currentStaffId,
        string $tab,
        int $page,
        int $pageSize
    ): LengthAwarePaginator {
        $query = match ($tab) {
            'mySubmitted' => $this->buildOtherMemoMySubmittedQuery($request, $currentStaffId),
            'myDivision' => $this->buildOtherMemoMyDivisionQuery($request),
            'allMemos' => in_array(87, user_session('permissions', []))
                ? $this->buildOtherMemoAllQuery($request)
                : null,
            default => null,
        };

        if ($query === null) {
            return new LengthAwarePaginator([], 0, $pageSize, $page);
        }

        return $query
            ->orderByDesc('updated_at')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /**
     * @return array{my_submitted:int,my_division:int,all_memos:int}
     */
    protected function otherMemoIndexTabCounts(Request $request, int $currentStaffId): array
    {
        return [
            'my_submitted' => $this->paginateOtherMemoIndexTab($request, $currentStaffId, 'mySubmitted', 1, 1)->total(),
            'my_division' => $this->paginateOtherMemoIndexTab($request, $currentStaffId, 'myDivision', 1, 1)->total(),
            'all_memos' => $this->paginateOtherMemoIndexTab($request, $currentStaffId, 'allMemos', 1, 1)->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeOtherMemoIndexRow(OtherMemo $memo, int $rowNum, int $currentStaffId): array
    {
        $title = (string) (data_get($memo->payload, 'title') ?: $memo->memo_type_name_snapshot);
        $status = (string) ($memo->overall_status ?? 'draft');
        $isOwner = (int) $memo->staff_id === $currentStaffId;

        $creatorName = null;
        if ($memo->creator) {
            $creatorName = trim(collect([
                $memo->creator->title,
                $memo->creator->fname,
                $memo->creator->lname,
            ])->filter()->implode(' '));
        }

        $approverName = null;
        if ($memo->currentApprover) {
            $approverName = trim(collect([
                $memo->currentApprover->title,
                $memo->currentApprover->fname,
                $memo->currentApprover->lname,
            ])->filter()->implode(' '));
        }

        return [
            'row_num' => $rowNum,
            'id' => $memo->id,
            'document_number' => $memo->document_number,
            'title' => $title,
            'memo_type_name' => $memo->memo_type_name_snapshot,
            'creator_name' => $creatorName,
            'division_name' => $memo->division->division_name ?? 'N/A',
            'created_at' => $memo->created_at?->format('M d, Y'),
            'overall_status' => $status,
            'current_approver_name' => $approverName,
            'show_url' => route('other-memos.show', $memo->id),
            'edit_url' => ($isOwner && in_array($status, ['draft', 'returned'], true))
                ? route('other-memos.edit', $memo->id)
                : null,
            'delete_url' => ($isOwner && $status === 'draft')
                ? route('other-memos.destroy', $memo->id)
                : null,
            'print_url' => ($isOwner && $status === 'approved')
                ? route('other-memos.print', $memo->id)
                : null,
        ];
    }

    /**
     * @return list<array{title:string,value:string}>
     */
    protected function buildOtherMemoYearSelectOptions(int $currentYear, int $minYear): array
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
    protected function buildOtherMemoDivisionSelectOptions(iterable $divisions): array
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
    protected function buildOtherMemoStaffSelectOptions(iterable $staff): array
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
    protected function buildOtherMemoStatusSelectOptions(): array
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
}
