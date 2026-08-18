<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\TicketsExport;
use App\Http\Controllers\Concerns\DownloadsPdfReports;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Services\HelpdeskPdfReportService;
use App\Services\HtmlSanitizer;
use App\Services\TicketReadCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    use DownloadsPdfReports;

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

        $cacheKey = TicketReadCache::key('reports', 'agent_dashboard', (int) $user->id, $request->query());

        $payload = TicketReadCache::remember($cacheKey, function () use ($user) {
            return $this->buildAgentDashboardPayload($user);
        });

        return response()->json(['data' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAgentDashboardPayload(\App\Models\User $user): array
    {
        $base = HelpdeskTicket::query()->assignedToUser((int) $user->id);

        $pendingStatuses = ['open', 'pending', 'in_progress'];
        $now = now();
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->endOfDay();
        $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay();

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
            ->with(['category', 'assignee', 'assignees'])
            ->assignedToUser((int) $user->id)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        return [
            'counts' => $counts,
            'breakdown' => [
                'by_status' => $byStatus,
                'by_priority' => $byPriority,
            ],
            'recent' => TicketResource::collection($recent)->resolve(),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    public function myRequesterReport(Request $request): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $p->staff_id, 422, 'Missing staff_id on profile.');

        $cacheKey = TicketReadCache::key('reports', 'my_requester', (int) $user->id, $request->query());

        $payload = TicketReadCache::remember($cacheKey, function () use ($request, $p) {
            $sid = (int) $p->staff_id;
            $qTerm = trim((string) $request->query('q', ''));
            $base = HelpdeskTicket::query()
                ->with(['category', 'assignee', 'resolvedBy'])
                ->where('requester_staff_id', $sid);

            $this->applyTicketSearch($base, $qTerm);
            $this->applyReportFilters($base, $request);
            $this->applyColumnFilters($base, $request);

            $stats = [
                'total_received' => (clone $base)->count(),
                'pending' => (clone $base)->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])->count(),
                'resolved' => (clone $base)->where('status', 'resolved')->count(),
            ];

            $tickets = (clone $base)->orderByDesc('id')->paginate(min((int) $request->get('per_page', 20), 100));

            return [
                'stats' => $stats,
                'tickets' => [
                    'current_page' => $tickets->currentPage(),
                    'data' => TicketResource::collection($tickets->items())->resolve(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                ],
            ];
        });

        return response()->json(['data' => $payload]);
    }

    public function adminSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $p->isHelpdeskAdmin(), 403);

        $cacheKey = TicketReadCache::key('reports', 'admin_summary', (int) $user->id, $request->query());

        $payload = TicketReadCache::remember($cacheKey, function () use ($request) {
            $qTerm = trim((string) $request->query('q', ''));
            $perPage = min((int) $request->query('per_page', 20), 100);

            $countsBase = HelpdeskTicket::query();
            $this->applyReportFilters($countsBase, $request);
            $this->applyColumnFilters($countsBase, $request);
            $this->applyTicketSearch($countsBase, $qTerm);

            $counts = [
                'total' => (clone $countsBase)->count(),
                'open' => (clone $countsBase)->whereIn('status', ['open', 'pending', 'in_progress'])->count(),
                'awaiting_requester_confirmation' => (clone $countsBase)->where('status', 'awaiting_requester_confirmation')->count(),
                'resolved' => (clone $countsBase)->where('status', 'resolved')->count(),
                'closed' => (clone $countsBase)->where('status', 'closed')->count(),
            ];

            $recentQuery = HelpdeskTicket::query()
                ->with(['category', 'assignee'])
                ->orderByDesc('id');
            $this->applyTicketSearch($recentQuery, $qTerm);
            $this->applyReportFilters($recentQuery, $request);
            $this->applyColumnFilters($recentQuery, $request);
            $recent = $recentQuery->paginate($perPage);

            return [
                'counts' => $counts,
                'recent' => [
                    'current_page' => $recent->currentPage(),
                    'data' => TicketResource::collection($recent->items())->resolve(),
                    'last_page' => $recent->lastPage(),
                    'per_page' => $recent->perPage(),
                    'total' => $recent->total(),
                ],
            ];
        });

        return response()->json(['data' => $payload]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $scope = $request->query('scope', 'assigned');
        $q = HelpdeskTicket::query()->with(['category', 'assignee']);

        if ($scope === 'all' && $p->isHelpdeskAdmin()) {
            // all tickets
        } elseif ($scope === 'mine' && $p->staff_id) {
            $q->where('requester_staff_id', $p->staff_id);
        } else {
            $q->where('assigned_user_id', $user->id);
        }

        $qTerm = trim((string) $request->query('q', ''));
        $this->applyTicketSearch($q, $qTerm);
        $this->applyReportFilters($q, $request);
        $this->applyColumnFilters($q, $request);

        $rows = $q->orderByDesc('id')->limit(5000)->get();
        $filename = 'helpdesk-tickets-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TicketsExport($rows), $filename);
    }

    public function exportPdf(Request $request, HelpdeskPdfReportService $pdf): Response
    {
        $user = $request->user();
        $p = $user->helpdeskProfile;
        abort_unless($p && $this->isStaff($p), 403);

        $scope = $request->query('scope', 'assigned');
        $q = HelpdeskTicket::query()->with(['category', 'assignee']);

        if ($scope === 'all' && $p->isHelpdeskAdmin()) {
            // all tickets
        } elseif ($scope === 'mine' && $p->staff_id) {
            $q->where('requester_staff_id', $p->staff_id);
        } else {
            $q->where('assigned_user_id', $user->id);
            $scope = 'assigned';
        }

        $qTerm = trim((string) $request->query('q', ''));
        $this->applyTicketSearch($q, $qTerm);
        $this->applyReportFilters($q, $request);
        $this->applyColumnFilters($q, $request);

        $tickets = $q->orderByDesc('id')->limit(2000)->get();
        $rows = $tickets->map(fn (HelpdeskTicket $t) => [
            $t->ticket_number,
            $t->subject,
            $t->category?->name,
            $t->status,
            $t->priority,
            $t->requester_name,
            $t->assignee?->name,
            optional($t->created_at)?->format('Y-m-d H:i'),
            HtmlSanitizer::toPlainText($t->resolution_summary),
        ])->all();

        return $this->pdfTableDownload(
            $request,
            $pdf,
            'Help Desk tickets ('.$scope.')',
            ['Ticket #', 'Subject', 'Category', 'Status', 'Priority', 'Requester', 'Assignee', 'Created', 'Resolution'],
            $rows,
            'helpdesk-tickets-'.now()->format('Y-m-d-His').'.pdf',
            ['Scope: '.$scope, 'Rows: '.count($rows)],
        );
    }

    private function applyTicketSearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $query->where(function (Builder $w) use ($like) {
            $w->where('ticket_number', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('requester_name', 'like', $like)
                ->orWhere('requester_email', 'like', $like)
                ->orWhere('status', 'like', $like)
                ->orWhere('priority', 'like', $like)
                ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                ->orWhereHas('assignee', function (Builder $a) use ($like) {
                    $a->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
        });
    }

    private function likeTerm(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    private function applyColumnFilters(Builder $query, Request $request): void
    {
        $colTicket = trim((string) $request->query('col_ticket', ''));
        if ($colTicket !== '') {
            $query->where('ticket_number', 'like', $this->likeTerm($colTicket));
        }

        $colSubject = trim((string) $request->query('col_subject', ''));
        if ($colSubject !== '') {
            $query->where('subject', 'like', $this->likeTerm($colSubject));
        }

        $colAssignee = trim((string) $request->query('col_assignee', ''));
        if ($colAssignee !== '') {
            $like = $this->likeTerm($colAssignee);
            $query->whereHas('assignee', function (Builder $a) use ($like) {
                $a->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $colStatus = trim((string) $request->query('col_status', ''));
        if ($colStatus !== '') {
            $query->where('status', 'like', $this->likeTerm($colStatus));
        }
    }

    private function applyReportFilters(Builder $query, Request $request): void
    {
        $agentIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) $request->query('agent_ids', []),
        ), static fn (int $id) => $id > 0));

        if ($agentIds !== []) {
            $query->whereIn('assigned_user_id', $agentIds);
        }

        $groupIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) $request->query('group_ids', []),
        ), static fn (int $id) => $id > 0));

        if ($groupIds !== []) {
            $query->whereIn('assigned_group_id', $groupIds);
        }

        $categoryIds = array_values(array_filter(array_map(
            static fn ($id) => (int) $id,
            (array) $request->query('category_ids', []),
        ), static fn (int $id) => $id > 0));

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $statuses = array_values(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            (array) $request->query('statuses', []),
        )));

        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $priorities = array_values(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            (array) $request->query('priorities', []),
        )));

        if ($priorities !== []) {
            $query->whereIn('priority', $priorities);
        }

        $dateField = trim((string) $request->query('date_field', 'created_at'));
        if (! in_array($dateField, ['created_at', 'resolved_at', 'closed_at'], true)) {
            $dateField = 'created_at';
        }

        $dateFrom = trim((string) $request->query('date_from', ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $query->where($dateField, '>=', $dateFrom.' 00:00:00');
        }

        $dateTo = trim((string) $request->query('date_to', ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $query->where($dateField, '<=', $dateTo.' 23:59:59');
        }
    }
}
