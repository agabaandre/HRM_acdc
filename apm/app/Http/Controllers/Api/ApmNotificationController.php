<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApmApiUser;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmNotificationController extends Controller
{
    /**
     * List notifications for the authenticated staff member.
     * Default: unread only. Set unread_only=false to include read items.
     */
    public function index(Request $request): JsonResponse
    {
        $staffId = $this->resolveStaffId();
        if ($staffId instanceof JsonResponse) {
            return $staffId;
        }

        $unreadOnly = filter_var($request->query('unread_only', true), FILTER_VALIDATE_BOOLEAN);
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $query = Notification::query()
            ->where('staff_id', $staffId)
            ->orderByDesc('created_at');

        if ($unreadOnly) {
            $query->where('is_read', false);
        }

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items())->map(fn (Notification $n) => $this->toApiRow($n))->values();

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'unread_only' => $unreadOnly,
            ],
        ]);
    }

    /**
     * Mark all unread notifications as read for the current user.
     */
    public function readAll(): JsonResponse
    {
        $staffId = $this->resolveStaffId();
        if ($staffId instanceof JsonResponse) {
            return $staffId;
        }

        $count = Notification::query()
            ->where('staff_id', $staffId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifications marked as read.',
            'marked_count' => $count,
        ]);
    }

    /**
     * Mark one notification as read (must belong to current staff).
     */
    public function markRead(int $id): JsonResponse
    {
        $staffId = $this->resolveStaffId();
        if ($staffId instanceof JsonResponse) {
            return $staffId;
        }

        $notification = Notification::query()
            ->where('staff_id', $staffId)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        if (!$notification->is_read) {
            $notification->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $this->toApiRow($notification->fresh()),
        ]);
    }

    private function resolveStaffId(): int|JsonResponse
    {
        $user = auth('api')->user();
        if (!$user instanceof ApmApiUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $staffId = (int) ($user->auth_staff_id ?? 0);
        if ($staffId < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No staff profile linked to this account.',
            ], 403);
        }

        return $staffId;
    }

    /**
     * @return array<string, mixed>
     */
    private function toApiRow(Notification $n): array
    {
        $title = $n->title;
        if ($title === null || $title === '') {
            $title = Notification::DEFAULT_TITLE;
        }

        return [
            'id' => $n->id,
            'title' => $title,
            'message' => $n->message,
            'type' => $n->type,
            'is_read' => (bool) $n->is_read,
            'read_at' => optional($n->read_at)->toIso8601String(),
            'model_id' => $n->model_id,
            'model_type' => $n->model_type,
            'created_at' => optional($n->created_at)->toIso8601String(),
        ];
    }
}
