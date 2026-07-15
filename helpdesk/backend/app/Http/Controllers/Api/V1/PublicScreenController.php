<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\AgentLeaderboardService;
use App\Services\StaffDirectoryLookupService;
use App\Services\TicketFirstResponseService;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\JsonResponse;

/**
 * Read-only public dashboard for office TVs / lobby screens.
 *
 * IMPORTANT: this endpoint is unauthenticated. It MUST only return aggregate
 * statistics. No ticket subjects, descriptions, requester names, emails, or
 * any other PII may appear in the response. Tests should fail if individual
 * ticket content is ever leaked here.
 */
class PublicScreenController extends Controller
{
    /** Active (not yet resolved) workload — drives every "open" stat. */
    private const ACTIVE_STATUSES = ['open', 'pending', 'in_progress'];

    /** Inclusive set including the "waiting on requester" hand-off state. */
    private const PENDING_STATUSES = ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'];

    public function __invoke(
        TicketFirstResponseService $firstResponse,
        StaffDirectoryLookupService $directory,
        AgentLeaderboardService $leaderboard,
    ): JsonResponse {
        $now = now();
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->endOfDay();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

        return response()->json([
            'data' => [
                'generated_at' => $now->toIso8601String(),
                'volumes' => $this->volumes($now, $startOfToday, $endOfToday),
                'wait' => $this->waitMetrics($now, $firstResponse),
                'sla' => $this->slaMetrics($sevenDaysAgo, $now),
                'by_priority' => $this->byPriority(),
                'priority_matrix_by_group' => $leaderboard->priorityMatrixBySupportGroup($now),
                'by_category' => $this->byCategory(),
                'by_duty_station' => $this->byDutyStation($now, $startOfWeek, $directory),
                'closures_by_agent_month' => $this->closuresByAgentThisMonth($startOfMonth, $now),
                'workload' => $this->workload(),
                'in_progress_workload' => $this->inProgressWorkload(),
                'trend' => $this->trend30Days($thirtyDaysAgo, $now),
                'agent_of_week' => $leaderboard->agentOfWeek($now),
                'agent_of_month' => $leaderboard->agentOfMonth($now),
                'screen' => HelpdeskSetting::screenDisplayConfig(),
                'csat' => [
                    'avg_score' => null,
                    'responses' => 0,
                    'note' => 'CSAT collection is not yet enabled.',
                ],
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function volumes(\DateTimeInterface $now, \DateTimeInterface $startOfToday, \DateTimeInterface $endOfToday): array
    {
        $byStatus = HelpdeskTicket::query()
            ->selectRaw('status, COUNT(*) AS c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $open = (int) ($byStatus['open'] ?? 0);
        $pending = (int) ($byStatus['pending'] ?? 0);
        $inProgress = (int) ($byStatus['in_progress'] ?? 0);
        $awaiting = (int) ($byStatus['awaiting_requester_confirmation'] ?? 0);

        $unassigned = HelpdeskTicket::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNull('assigned_user_id')
            ->count();

        $resolvedToday = HelpdeskTicket::query()
            ->whereIn('status', ['resolved', 'closed'])
            ->whereRaw('COALESCE(resolved_at, closed_at) BETWEEN ? AND ?', [
                $startOfToday->format('Y-m-d H:i:s'),
                $endOfToday->format('Y-m-d H:i:s'),
            ])
            ->count();

        $resolvedOnlyToday = HelpdeskTicket::query()
            ->where('status', 'resolved')
            ->whereBetween('resolved_at', [$startOfToday, $endOfToday])
            ->count();

        $closedToday = HelpdeskTicket::query()
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$startOfToday, $endOfToday])
            ->count();

        $createdToday = HelpdeskTicket::query()
            ->whereBetween('created_at', [$startOfToday, $endOfToday])
            ->count();

        return [
            'open' => $open,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'awaiting_confirm' => $awaiting,
            'unassigned' => $unassigned,
            'created_today' => $createdToday,
            'resolved_today' => $resolvedToday,
            'resolved_only_today' => $resolvedOnlyToday,
            'closed_today' => $closedToday,
            'total_active' => $open + $pending + $inProgress + $awaiting,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitMetrics(\DateTimeInterface $now, TicketFirstResponseService $firstResponse): array
    {
        // Average first-response minutes for tickets that received their first
        // response in the last 24h (smoothed; ignores ancient outliers).
        $oneDayAgo = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)))->modify('-1 day');
        $avg = $firstResponse->averageFirstResponseMinutesSince($oneDayAgo);

        $oldest = HelpdeskTicket::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->orderBy('created_at')
            ->first(['ticket_number', 'created_at', 'priority']);

        $longestOpenMinutes = null;
        if ($oldest && $oldest->created_at) {
            $longestOpenMinutes = max(0, (int) abs($oldest->created_at->diffInMinutes(now())));
        }

        return [
            'avg_first_response_minutes' => $avg !== null ? (int) round((float) $avg) : null,
            'longest_open_minutes' => $longestOpenMinutes,
            'oldest_open_ticket_number' => $oldest?->ticket_number,
            'oldest_open_priority' => $oldest?->priority,
            'window_label' => 'last 24h',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function slaMetrics(\DateTimeInterface $since, \DateTimeInterface $now): array
    {
        // Response SLA: tickets that breached or met their response target.
        $responseStats = HelpdeskTicket::query()
            ->whereNotNull('sla_response_due_at')
            ->whereNotNull('first_response_at')
            ->where('first_response_at', '>=', $since)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN first_response_at <= sla_response_due_at THEN 1 ELSE 0 END) AS met')
            ->first();

        // Resolution SLA: tickets resolved within their resolution target.
        $resolutionStats = HelpdeskTicket::query()
            ->whereNotNull('sla_resolution_due_at')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN resolved_at <= sla_resolution_due_at THEN 1 ELSE 0 END) AS met')
            ->first();

        // Active tickets already past their SLA — the "warning" count.
        $breachedPending = HelpdeskTicket::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', $now)
            ->count();

        $responseTotal = (int) ($responseStats?->total ?? 0);
        $responseMet = (int) ($responseStats?->met ?? 0);
        $resolutionTotal = (int) ($resolutionStats?->total ?? 0);
        $resolutionMet = (int) ($resolutionStats?->met ?? 0);

        return [
            'sample_window_days' => 7,
            'response_within_sla_pct' => $responseTotal > 0
                ? round(($responseMet / $responseTotal) * 100, 1)
                : null,
            'resolution_within_sla_pct' => $resolutionTotal > 0
                ? round(($resolutionMet / $resolutionTotal) * 100, 1)
                : null,
            'response_sample_size' => $responseTotal,
            'resolution_sample_size' => $resolutionTotal,
            'breached_pending' => $breachedPending,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function byPriority(): array
    {
        $rows = HelpdeskTicket::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->selectRaw('priority, COUNT(*) AS c')
            ->groupBy('priority')
            ->pluck('c', 'priority');

        $out = ['urgent' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($out as $k => $_) {
            $out[$k] = (int) ($rows[$k] ?? 0);
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function byCategory(): array
    {
        $rows = HelpdeskTicket::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->selectRaw('category_id, COUNT(*) AS c')
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        if ($rows->isEmpty()) {
            return [];
        }

        $names = HelpdeskCategory::query()
            ->whereIn('id', $rows->keys()->all())
            ->pluck('name', 'id');

        $out = [];
        foreach ($rows as $id => $count) {
            $out[] = [
                'id' => (int) $id,
                'name' => (string) ($names[$id] ?? ('Category '.$id)),
                'open' => (int) $count,
            ];
        }

        usort($out, fn ($a, $b) => $b['open'] <=> $a['open']);

        return array_slice($out, 0, 8);
    }

    /**
     * Open, closed-this-week, and SLA-overdue tickets grouped by requester duty station.
     * Duty station comes from the staff directory (requester's contract station), with
     * helpdesk profile sync as fallback.
     *
     * @return list<array<string, mixed>>
     */
    private function byDutyStation(
        \DateTimeInterface $now,
        \DateTimeInterface $startOfWeek,
        StaffDirectoryLookupService $directory,
    ): array {
        $openByStaff = HelpdeskTicket::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->selectRaw('requester_staff_id, COUNT(*) AS c')
            ->groupBy('requester_staff_id')
            ->pluck('c', 'requester_staff_id');

        $closedThisWeekByStaff = HelpdeskTicket::query()
            ->whereRaw('COALESCE(resolved_at, closed_at) BETWEEN ? AND ?', [
                $startOfWeek->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            ])
            ->selectRaw('requester_staff_id, COUNT(*) AS c')
            ->groupBy('requester_staff_id')
            ->pluck('c', 'requester_staff_id');

        $overtimeByStaff = HelpdeskTicket::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', $now)
            ->selectRaw('requester_staff_id, COUNT(*) AS c')
            ->groupBy('requester_staff_id')
            ->pluck('c', 'requester_staff_id');

        $staffIds = collect([$openByStaff, $closedThisWeekByStaff, $overtimeByStaff])
            ->flatMap(fn ($rows) => $rows->keys())
            ->map(fn ($id) => $id === null ? 0 : (int) $id)
            ->unique()
            ->values();

        if ($staffIds->isEmpty()) {
            return [];
        }

        $directory->ensureStaffCacheWarm();
        $metaByStaff = $directory->dutyStationMetaFromTickets();

        $buckets = [];
        foreach ($staffIds as $staffId) {
            $name = $directory->dutyStationLabelForStaffIdWithMeta(
                $staffId > 0 ? $staffId : null,
                $metaByStaff,
            );
            $buckets[$name] ??= [
                'name' => $name,
                'open' => 0,
                'closed_this_week' => 0,
                'overtime' => 0,
            ];

            if ($staffId > 0) {
                $buckets[$name]['open'] += (int) ($openByStaff[$staffId] ?? 0);
                $buckets[$name]['closed_this_week'] += (int) ($closedThisWeekByStaff[$staffId] ?? 0);
                $buckets[$name]['overtime'] += (int) ($overtimeByStaff[$staffId] ?? 0);
            } else {
                $buckets[$name]['open'] += (int) ($openByStaff[null] ?? $openByStaff[''] ?? 0);
                $buckets[$name]['closed_this_week'] += (int) ($closedThisWeekByStaff[null] ?? $closedThisWeekByStaff[''] ?? 0);
                $buckets[$name]['overtime'] += (int) ($overtimeByStaff[null] ?? $overtimeByStaff[''] ?? 0);
            }
        }

        $out = array_values($buckets);
        usort($out, function (array $a, array $b): int {
            return [$b['open'], $b['closed_this_week'], $b['overtime'], $a['name']]
                <=> [$a['open'], $a['closed_this_week'], $a['overtime'], $b['name']];
        });

        return array_slice($out, 0, 12);
    }

    /**
     * Tickets closed/resolved this calendar month, grouped by resolving agent.
     *
     * @return list<array<string, mixed>>
     */
    private function closuresByAgentThisMonth(\DateTimeInterface $startOfMonth, \DateTimeInterface $now): array
    {
        $loads = HelpdeskTicket::query()
            ->whereRaw('COALESCE(resolved_at, closed_at) BETWEEN ? AND ?', [
                $startOfMonth->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            ])
            ->whereNotNull('resolved_by_user_id')
            ->selectRaw('resolved_by_user_id, COUNT(*) AS c')
            ->groupBy('resolved_by_user_id')
            ->pluck('c', 'resolved_by_user_id');

        if ($loads->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $loads->keys()->all())
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->get(['id', 'name', 'photo']);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => StaffPhotoUrl::forUser($u),
                'closed' => (int) ($loads[$u->id] ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => [$b['closed'], $a['name']] <=> [$a['closed'], $b['name']]);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workload(): array
    {
        $loads = HelpdeskTicket::query()
            ->whereIn('status', self::PENDING_STATUSES)
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) AS c')
            ->groupBy('assigned_user_id')
            ->pluck('c', 'assigned_user_id');

        $inProgressLoads = HelpdeskTicket::query()
            ->where('status', 'in_progress')
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) AS c')
            ->groupBy('assigned_user_id')
            ->pluck('c', 'assigned_user_id');

        if ($loads->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $loads->keys()->all())
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->with('helpdeskProfile:id,user_id,work_mode')
            ->get(['id', 'name', 'photo']);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => StaffPhotoUrl::forUser($u),
                'open' => (int) ($loads[$u->id] ?? 0),
                'in_progress' => (int) ($inProgressLoads[$u->id] ?? 0),
                'work_mode' => $u->helpdeskProfile?->work_mode,
            ];
        }

        usort($out, fn ($a, $b) => $b['open'] <=> $a['open']);

        return array_slice($out, 0, 8);
    }

    /**
     * Agents currently working tickets (status = in_progress only).
     *
     * @return list<array<string, mixed>>
     */
    private function inProgressWorkload(): array
    {
        $loads = HelpdeskTicket::query()
            ->where('status', 'in_progress')
            ->whereNotNull('assigned_user_id')
            ->selectRaw('assigned_user_id, COUNT(*) AS c')
            ->groupBy('assigned_user_id')
            ->pluck('c', 'assigned_user_id');

        if ($loads->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $loads->keys()->all())
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->get(['id', 'name', 'photo']);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => $u->id,
                'name' => $u->name,
                'avatar_url' => StaffPhotoUrl::forUser($u),
                'in_progress' => (int) ($loads[$u->id] ?? 0),
            ];
        }

        usort($out, fn ($a, $b) => [$b['in_progress'], $a['name']] <=> [$a['in_progress'], $b['name']]);

        return array_slice($out, 0, 12);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function trend30Days(\DateTimeInterface $since, \DateTimeInterface $now): array
    {
        $created = HelpdeskTicket::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) AS d, COUNT(*) AS c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $resolved = HelpdeskTicket::query()
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $since)
            ->selectRaw('DATE(resolved_at) AS d, COUNT(*) AS c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $cursor = (new \DateTimeImmutable($since->format('Y-m-d')));
        $end = (new \DateTimeImmutable($now->format('Y-m-d')));
        $out = [];
        while ($cursor <= $end) {
            $day = $cursor->format('Y-m-d');
            $out[] = [
                'day' => $day,
                'created' => (int) ($created[$day] ?? 0),
                'resolved' => (int) ($resolved[$day] ?? 0),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $out;
    }
}
