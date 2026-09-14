<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskAuditLog;
use App\Services\HelpdeskAuditLogger;
use App\Services\HelpdeskAuditReversalService;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class AdminAuditLogController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(
        Request $request,
        StaffDirectoryLookupService $directory,
        HelpdeskAuditReversalService $reversal,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $q = trim((string) $request->query('q', ''));
        $action = trim((string) $request->query('action', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $baseQuery = HelpdeskAuditLog::query();

        if ($action !== '') {
            $baseQuery->where('action', $action);
        }

        if ($dateFrom !== '') {
            try {
                $baseQuery->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($dateTo !== '') {
            try {
                $baseQuery->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $baseQuery->where(function ($w) use ($like, $q) {
                $w->where('action', 'like', $like)
                    ->orWhere('correlation_id', 'like', $like)
                    ->orWhere('auditable_type', 'like', $like)
                    ->orWhere('ip_address', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('staff_id', (int) $q)
                        ->orWhere('auditable_id', (int) $q)
                        ->orWhere('id', (int) $q);
                }
                $w->orWhereHas('user', fn ($u) => $u
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            });
        }

        $statsQuery = clone $baseQuery;
        $recentQuery = clone $baseQuery;

        $paginator = (clone $baseQuery)
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(
            fn (HelpdeskAuditLog $row) => $this->formatRow($row, $directory, $reversal),
        );

        $actions = HelpdeskAuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->values();

        $topAction = (clone $statsQuery)
            ->selectRaw('action, COUNT(*) as c')
            ->groupBy('action')
            ->orderByDesc('c')
            ->first();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'actions' => $actions,
                'stats' => [
                    'total' => (clone $statsQuery)->count(),
                    'recent_24h' => $recentQuery->where('created_at', '>=', now()->subDay())->count(),
                    'top_action' => $topAction->action ?? null,
                    'top_action_count' => (int) ($topAction->c ?? 0),
                ],
            ],
        ]);
    }

    public function reverse(
        Request $request,
        HelpdeskAuditLog $auditLog,
        HelpdeskAuditReversalService $reversal,
        HelpdeskAuditLogger $auditLogger,
    ): JsonResponse {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'action_type' => ['required', 'string', 'in:restore,delete'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        try {
            $result = $reversal->reverse(
                $auditLog,
                $validated['action_type'],
                trim($validated['reason']),
                $request->user(),
                $auditLogger,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(
        HelpdeskAuditLog $row,
        StaffDirectoryLookupService $directory,
        HelpdeskAuditReversalService $reversal,
    ): array {
        $staffName = $row->user?->name;
        $staffEmail = $row->user?->email;
        $dutyStation = null;

        if ($row->staff_id) {
            $resolved = $directory->resolveByStaffId((int) $row->staff_id);
            if ($resolved !== null) {
                $staffName = $staffName ?: $resolved['name'];
                $staffEmail = $staffEmail ?: ($resolved['work_email'] ?: null);
                $dutyStation = $resolved['duty_station_name'] ?? null;
            }
        }

        $auditableShort = $row->auditable_type
            ? class_basename($row->auditable_type)
            : null;

        return [
            'id' => $row->id,
            'action' => $row->action,
            'created_at' => $row->created_at?->utc()->toIso8601String(),
            'user_id' => $row->user_id,
            'staff_id' => $row->staff_id,
            'staff_name' => $staffName,
            'staff_email' => $staffEmail,
            'duty_station_name' => $dutyStation,
            'auditable_type' => $row->auditable_type,
            'auditable_type_short' => $auditableShort,
            'auditable_id' => $row->auditable_id,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'correlation_id' => $row->correlation_id,
            'old_values' => $row->old_values,
            'new_values' => $row->new_values,
            'can_reverse' => $reversal->canReverse($row),
            'default_reversal_action' => $reversal->canReverse($row)
                ? $reversal->defaultReversalAction($row->action)
                : null,
        ];
    }
}
