<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\LegacySchema;
use App\Support\PortalReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Audit\Services\AuditLogRevertService;
use Modules\Auth\Models\PortalUser;
use Modules\Auth\Services\AuthUserAdminService;
use Modules\Auth\Services\PortalImpersonationService;
use Modules\Core\Support\PortalPermission;

class AuthAdminApiController extends Controller
{
    public function users(Request $request, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        $filters = [
            'q' => $request->query('q'),
            'group_id' => $request->query('group_id'),
            'status' => $request->query('status'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 20),
        ];

        $user = $request->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
        $cacheKey = PortalReadCache::key('permissions', 'auth_users', $userId, [
            'q' => (string) ($filters['q'] ?? ''),
            'group_id' => (string) ($filters['group_id'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'page' => (int) $filters['page'],
            'per_page' => (int) $filters['per_page'],
        ]);

        $payload = PortalReadCache::remember($cacheKey, fn () => $users->paginate($filters));

        return response()->json($payload);
    }

    public function userGroups(AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        return response()->json(['data' => $users->groups()]);
    }

    public function updateUser(int $id, Request $request, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:50'],
            'role' => ['sometimes', 'integer', 'min:1', 'exists:user_groups,id'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
            'allow_email_login' => ['sometimes', 'integer', 'in:0,1'],
        ]);

        try {
            $row = $users->update($id, $payload);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $row, 'message' => 'User updated.']);
    }

    public function blockUser(int $id, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        try {
            $message = $users->block($id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => $message]);
    }

    public function unblockUser(int $id, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        try {
            $message = $users->unblock($id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => $message]);
    }

    public function resetPassword(int $id, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        try {
            $message = $users->resetPassword($id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json(['message' => $message]);
    }

    public function setAllowEmailLogin(int $id, Request $request, AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);

        $validated = $request->validate([
            'allow_email_login' => ['required', 'integer', 'in:0,1'],
        ]);

        try {
            $message = $users->setAllowEmailLogin($id, (int) $validated['allow_email_login'] === 1);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $message]);
    }

    public function bulkCreateUsers(AuthUserAdminService $users): JsonResponse
    {
        PortalPermission::authorize(17);
        $result = $users->bulkCreate();

        return response()->json($result);
    }

    public function impersonate(int $id, PortalImpersonationService $impersonation): JsonResponse
    {
        PortalPermission::authorize(17);

        try {
            $payload = $impersonation->impersonate($id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'You are now impersonating '.$payload['user']['name'].'.',
            ...$payload,
        ]);
    }

    public function revertImpersonation(PortalImpersonationService $impersonation): JsonResponse
    {
        // Allowed while impersonating even if the target lacks permission 17.
        try {
            $payload = $impersonation->revert();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'You have returned to your admin session.',
            ...$payload,
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        if (! LegacySchema::has('user_logs')) {
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

        $hasHttpMethodColumn = LegacySchema::hasColumn('user_logs', 'http_method');
        $hasEventTypeColumn = LegacySchema::hasColumn('user_logs', 'event_type');
        $hasRequestUriColumn = LegacySchema::hasColumn('user_logs', 'request_uri');
        $hasTargetTableColumn = LegacySchema::hasColumn('user_logs', 'target_table');
        $hasTargetIdColumn = LegacySchema::hasColumn('user_logs', 'target_id');
        $dateColumn = LegacySchema::hasColumn('user_logs', 'created_at') ? 'l.created_at' : 'l.date_loged_in';
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

    public function revertAuditLog(int $id, Request $request): JsonResponse
    {
        PortalPermission::authorize(17);

        $result = app(AuditLogRevertService::class)->revert(
            $id,
            auth()->id() ?? session('user.user_id')
        );

        return response()->json($result['body'], $result['status']);
    }
}
