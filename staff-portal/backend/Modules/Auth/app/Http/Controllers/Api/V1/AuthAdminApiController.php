<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\PortalPermission;
use Modules\Core\Support\PortalTable;

class AuthAdminApiController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        $q = DB::table('user as u')
            ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
            ->leftJoin('user_groups as ug', 'ug.id', '=', 'u.role')
            ->select(
                'u.user_id',
                'u.name',
                'u.status',
                'u.role',
                'u.auth_staff_id',
                'ug.group_name',
                's.work_email',
                DB::raw("TRIM(CONCAT(COALESCE(s.fname,''), ' ', COALESCE(s.lname,''))) as staff_name")
            )
            ->orderBy('u.name');

        if ($request->filled('q')) {
            $term = '%'.$request->query('q').'%';
            $q->where(function ($w) use ($term): void {
                $w->where('u.name', 'like', $term)
                    ->orWhere('s.work_email', 'like', $term)
                    ->orWhere('s.fname', 'like', $term)
                    ->orWhere('s.lname', 'like', $term);
            });
        }

        $paginator = PortalTable::paginateDistinct(
            $q,
            'u.user_id',
            min(100, max(10, (int) $request->query('per_page', 20))),
            max(1, (int) $request->query('page', 1))
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        if (! Schema::hasTable('user_logs')) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => min(100, max(1, (int) $request->query('per_page', 50))),
                    'total' => 0,
                    'extended' => false,
                    'message' => 'user_logs table missing',
                ],
            ]);
        }

        $hasHttpMethodColumn = Schema::hasColumn('user_logs', 'http_method');
        $hasEventTypeColumn = Schema::hasColumn('user_logs', 'event_type');
        $hasRequestUriColumn = Schema::hasColumn('user_logs', 'request_uri');
        $hasTargetTableColumn = Schema::hasColumn('user_logs', 'target_table');
        $hasTargetIdColumn = Schema::hasColumn('user_logs', 'target_id');
        $dateColumn = Schema::hasColumn('user_logs', 'created_at') ? 'l.created_at' : 'l.date_loged_in';
        $search = trim((string) ($request->query('search', $request->query('q', ''))));

        $q = DB::table('user_logs as l')
            ->leftJoin('user as u', 'u.user_id', '=', 'l.user_id')
            ->leftJoin('staff as s', 's.staff_id', '=', 'u.auth_staff_id')
            ->select('l.*', 'u.name as user_name', 's.work_email as user_email')
            ->orderByDesc('l.id');

        if ($search !== '') {
            $term = '%'.$search.'%';
            $q->where(function ($w) use ($hasEventTypeColumn, $hasRequestUriColumn, $hasTargetIdColumn, $hasTargetTableColumn, $term): void {
                $w->where('l.action', 'like', $term);

                if ($hasRequestUriColumn) {
                    $w->orWhere('l.request_uri', 'like', $term);
                }
                if ($hasTargetTableColumn) {
                    $w->orWhere('l.target_table', 'like', $term);
                }
                if ($hasTargetIdColumn) {
                    $w->orWhere('l.target_id', 'like', $term);
                }
                if ($hasEventTypeColumn) {
                    $w->orWhere('l.event_type', 'like', $term);
                }
            });
        }

        if ($request->filled('name')) {
            $q->where('u.name', 'like', $request->query('name').'%');
        }

        if ($request->filled('email')) {
            $q->where('s.work_email', 'like', $request->query('email').'%');
        }

        if ($hasHttpMethodColumn && $request->filled('http_method')) {
            $q->where('l.http_method', strtoupper((string) $request->query('http_method')));
        }

        if ($hasEventTypeColumn && $request->filled('event_type')) {
            $q->where('l.event_type', (string) $request->query('event_type'));
        }

        if ($request->filled('date_from')) {
            $q->whereDate($dateColumn, '>=', (string) $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $q->whereDate($dateColumn, '<=', (string) $request->query('date_to'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));
        $total = (clone $q)->count();
        $items = $q->forPage($page, $perPage)->get();
        $extended = ($hasHttpMethodColumn || $hasEventTypeColumn)
            && DB::table('user_logs as l')
                ->where(function ($extendedQuery) use ($hasEventTypeColumn, $hasHttpMethodColumn): void {
                    if ($hasHttpMethodColumn) {
                        $extendedQuery->where(function ($methodQuery): void {
                            $methodQuery->whereNotNull('l.http_method')
                                ->where('l.http_method', '!=', '');
                        });
                    }

                    if ($hasEventTypeColumn) {
                        $method = $hasHttpMethodColumn ? 'orWhere' : 'where';
                        $extendedQuery->{$method}(function ($eventQuery): void {
                            $eventQuery->whereNotNull('l.event_type')
                                ->where('l.event_type', '!=', '');
                        });
                    }
                })
                ->exists();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
                'extended' => $extended,
            ],
        ]);
    }
}
