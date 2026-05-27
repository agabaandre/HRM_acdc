<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
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

    public function __invoke(): JsonResponse
    {
        $now = now();
        $startOfToday = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->endOfDay();
        $sevenDaysAgo = $now->copy()->subDays(7)->startOfDay();
        $thirtyDaysAgo = $now->copy()->subDays(30)->startOfDay();

        return response()->json([
            'data' => [
                'generated_at' => $now->toIso8601String(),
                'volumes' => $this->volumes($now, $startOfToday, $endOfToday),
                'wait' => $this->waitMetrics($now),
                'sla' => $this->slaMetrics($sevenDaysAgo, $now),
                'by_priority' => $this->byPriority(),
                'by_category' => $this->byCategory(),
                'workload' => $this->workload(),
                'trend' => $this->trend30Days($thirtyDaysAgo, $now),
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
            'closed_today' => $closedToday,
            'total_active' => $open + $pending + $inProgress + $awaiting,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function waitMetrics(\DateTimeInterface $now): array
    {
        // Average first-response minutes for tickets that received their first
        // response in the last 24h (smoothed; ignores ancient outliers).
        $oneDayAgo = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)))->modify('-1 day');
        $avg = HelpdeskTicket::query()
            ->whereNotNull('first_response_at')
            ->where('first_response_at', '>=', $oneDayAgo->format('Y-m-d H:i:s'))
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_min')
            ->value('avg_min');

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
            ->get(['id', 'name']);

        $out = [];
        foreach ($users as $u) {
            $out[] = [
                'id' => $u->id,
                'name' => $u->name,
                'open' => (int) ($loads[$u->id] ?? 0),
                'work_mode' => $u->helpdeskProfile?->work_mode,
            ];
        }

        usort($out, fn ($a, $b) => $b['open'] <=> $a['open']);

        return array_slice($out, 0, 8);
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
