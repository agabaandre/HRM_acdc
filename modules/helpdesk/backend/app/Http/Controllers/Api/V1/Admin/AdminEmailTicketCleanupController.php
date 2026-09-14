<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin tools to purge bad email-intake tickets (e.g. reply threads imported as tickets).
 */
class AdminEmailTicketCleanupController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function preview(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);
        $query = $this->baseQuery($request);

        return response()->json([
            'data' => [
                'count' => (clone $query)->count(),
                'filters' => $this->filtersFromRequest($request),
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);
        $validated = $request->validate([
            'confirm' => ['required', 'accepted'],
            'unassigned_only' => ['sometimes', 'boolean'],
            'source_email_only' => ['sometimes', 'boolean'],
            'open_only' => ['sometimes', 'boolean'],
            'created_before' => ['nullable', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ]);

        $limit = (int) ($validated['limit'] ?? 2000);
        $ids = $this->baseQuery($request)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return response()->json([
                'message' => 'No matching tickets to delete.',
                'data' => ['deleted' => 0],
            ]);
        }

        $deleted = 0;
        DB::transaction(function () use ($ids, &$deleted) {
            HelpdeskEmailMessage::query()->whereIn('ticket_id', $ids)->delete();
            HelpdeskTicketComment::query()->whereIn('ticket_id', $ids)->delete();
            // Detach common relations if tables exist via cascades; force delete tickets.
            $deleted = HelpdeskTicket::query()->whereIn('id', $ids)->delete();
        });

        return response()->json([
            'message' => "Deleted {$deleted} ticket(s).",
            'data' => ['deleted' => $deleted],
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<HelpdeskTicket>
     */
    private function baseQuery(Request $request)
    {
        $filters = $this->filtersFromRequest($request);
        $q = HelpdeskTicket::query();

        if ($filters['source_email_only']) {
            $q->where('source', 'email');
        }
        if ($filters['unassigned_only']) {
            $q->whereNull('assigned_user_id')->whereNull('assigned_group_id');
        }
        if ($filters['open_only']) {
            $q->whereIn('status', ['open', 'pending']);
        }
        if ($filters['created_before']) {
            $q->where('created_at', '<=', $filters['created_before']);
        }

        return $q;
    }

    /**
     * @return array{unassigned_only:bool,source_email_only:bool,open_only:bool,created_before:?string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'unassigned_only' => $request->boolean('unassigned_only', true),
            'source_email_only' => $request->boolean('source_email_only', true),
            'open_only' => $request->boolean('open_only', true),
            'created_before' => $request->filled('created_before')
                ? (string) $request->input('created_before')
                : null,
        ];
    }
}
