<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\TicketsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private function isStaff(HelpdeskProfile $p): bool
    {
        return in_array($p->role, [
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true);
    }

    public function agentDashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $base = HelpdeskTicket::query()->where('assigned_user_id', $user->id);

        $pendingStatuses = ['open', 'pending', 'in_progress'];
        $now = now();
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->endOfDay();
        $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay();

        // Cached pending query so we don't repeat the same predicate across counts.
        $pendingBase = (clone $base)->whereIn('status', $pendingStatuses);

        $counts = [
            'total_received' => (clone $base)->count(),
            'pending' => (clone $pendingBase)->count(),
            'awaiting_requester_confirmation' => (clone $base)->where('status', 'awaiting_requester_confirmation')->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->count(),
            'closed' => (clone $base)->where('status', 'closed')->count(),
            'overdue' => (clone $pendingBase)
                ->whereNotNull('sla_resolution_due_at')
                ->where('sla_resolution_due_at', '<', $now)
                ->count(),
            'due_today' => (clone $pendingBase)
                ->whereBetween('sla_resolution_due_at', [$startOfToday, $endOfToday])
                ->count(),
            'high_priority_pending' => (clone $pendingBase)
                ->whereIn('priority', ['high', 'urgent'])
                ->count(),
            'new_today' => (clone $base)
                ->whereBetween('created_at', [$startOfToday, $endOfToday])
                ->count(),
            'resolved_this_week' => (clone $base)
                ->where('status', 'resolved')
                ->where('resolved_at', '>=', $sevenDaysAgo)
                ->count(),
        ];

        $byStatusRows = (clone $base)
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');
        $byStatus = [];
        foreach (['open', 'pending', 'in_progress', 'awaiting_requester_confirmation', 'resolved', 'closed'] as $s) {
            $byStatus[$s] = (int) ($byStatusRows[$s] ?? 0);
        }

        $byPriorityRows = (clone $base)
            ->selectRaw('priority, COUNT(*) AS c')
            ->groupBy('priority')
            ->pluck('c', 'priority');
        $byPriority = [];
        foreach (['low', 'medium', 'high', 'urgent'] as $pr) {
            $byPriority[$pr] = (int) ($byPriorityRows[$pr] ?? 0);
        }

        $recent = HelpdeskTicket::query()
            ->with(['category', 'assignee'])
            ->where('assigned_user_id', $user->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return response()->json([
            'data' => [
                'counts' => $counts,
                'breakdown' => [
                    'by_status' => $byStatus,
                    'by_priority' => $byPriority,
                ],
                'recent' => TicketResource::collection($recent)->resolve(),
                'generated_at' => $now->toIso8601String(),
            ],
        ]);
    }

    public function myRequesterReport(Request $request): JsonResponse
    {
        $p = $request->user()->helpdeskProfile;
        abort_unless($p && $p->staff_id, 422, 'Missing staff_id on profile.');

        $sid = (int) $p->staff_id;
        $q = HelpdeskTicket::query()
            ->with(['category', 'assignee', 'resolvedBy'])
            ->where('requester_staff_id', $sid);

        $stats = [
            'total_received' => (clone $q)->count(),
            'pending' => (clone $q)->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])->count(),
            'resolved' => (clone $q)->where('status', 'resolved')->count(),
        ];

        $tickets = (clone $q)->orderByDesc('id')->paginate(min((int) $request->get('per_page', 20), 100));

        return response()->json([
            'data' => [
                'stats' => $stats,
                'tickets' => [
                    'current_page' => $tickets->currentPage(),
                    'data' => TicketResource::collection($tickets->items())->resolve(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ],
        ]);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        $p = $request->user()->helpdeskProfile;
        abort_unless($p && $p->role === HelpdeskProfile::ROLE_ADMIN, 403);

        $counts = [
            'total' => HelpdeskTicket::query()->count(),
            'open' => HelpdeskTicket::query()->whereIn('status', ['open', 'pending', 'in_progress'])->count(),
            'awaiting_requester_confirmation' => HelpdeskTicket::query()->where('status', 'awaiting_requester_confirmation')->count(),
            'resolved' => HelpdeskTicket::query()->where('status', 'resolved')->count(),
            'closed' => HelpdeskTicket::query()->where('status', 'closed')->count(),
        ];

        $recent = HelpdeskTicket::query()
            ->with(['category', 'assignee'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => [
                'counts' => $counts,
                'recent' => TicketResource::collection($recent)->resolve(),
            ],
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $scope = $request->query('scope', 'assigned');
        $q = HelpdeskTicket::query()->with(['category', 'assignee']);

        if ($scope === 'all' && $p->role === HelpdeskProfile::ROLE_ADMIN) {
            // all tickets
        } elseif ($scope === 'mine' && $p->staff_id) {
            $q->where('requester_staff_id', $p->staff_id);
        } else {
            $q->where('assigned_user_id', $user->id);
        }

        $rows = $q->orderByDesc('id')->limit(5000)->get();
        $filename = 'helpdesk-tickets-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TicketsExport($rows), $filename);
    }
}
