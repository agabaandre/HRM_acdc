<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityReport;
use App\Services\ActivityReport\ActivityReportShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ApmActivityReportController extends Controller
{
    public function __construct(
        private ActivityReportShareService $shares,
    ) {}

    /**
     * Export a single activity report payload for RBM consumption.
     */
    public function export(ActivityReport $activityReport): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->shares->exportPayload($activityReport),
        ]);
    }

    /**
     * Share an activity report to the RBM platform.
     */
    public function share(Request $request, ActivityReport $activityReport): JsonResponse
    {
        $validated = $request->validate([
            'targets' => 'required|array|min:1',
            'targets.*' => 'required|string|in:rbm,rba,pra',
            'note' => 'nullable|string|max:1000',
        ]);

        $staffId = (int) (
            $request->user()?->staff_id
            ?? (function_exists('user_session') ? user_session('staff_id') : 0)
            ?? 0
        );

        try {
            $rows = $this->shares->share(
                $activityReport,
                $validated['targets'],
                $staffId,
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Activity report shared.',
            'data' => [
                'shares' => $rows,
                'export' => $this->shares->exportPayload($activityReport),
            ],
        ]);
    }

    /**
     * List approved/submitted reports available for RBM pull.
     */
    public function shareable(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = ActivityReport::query()
            ->where(function ($w) {
                $w->where('has_been_submitted', true)
                    ->orWhereIn('overall_status', ['approved', 'submitted', 'pending']);
            })
            ->where('not_executed', false)
            ->orderByDesc('id');

        if ($request->filled('division_id')) {
            $query->where('division_id', (int) $request->query('division_id'));
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn (ActivityReport $r) => [
                'id' => $r->id,
                'document_number' => $r->document_number,
                'activity_name_code' => $r->activity_name_code,
                'overall_status' => $r->overall_status,
                'division_id' => $r->division_id,
                'start_date' => optional($r->start_date)->toDateString(),
                'end_date' => optional($r->end_date)->toDateString(),
                'export_url' => url('/api/apm/v1/activity-reports/'.$r->id.'/export'),
                'share_url' => url('/api/apm/v1/activity-reports/'.$r->id.'/share'),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
