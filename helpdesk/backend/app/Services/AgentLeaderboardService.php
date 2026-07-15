<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskSupportGroup;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Support\StaffPhotoUrl;

/**
 * Ranks helpdesk agents for lobby / TV screen "agent of the week/month" tiles.
 *
 * Score = tickets_worked_weight × volume_norm + response_time_weight × response_norm
 * where faster average first-response time scores higher.
 */
class AgentLeaderboardService
{
    /** @var list<string> */
    private const PENDING_STATUSES = ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'];

    /**
     * @return array<string, mixed>
     */
    public function agentOfWeek(\DateTimeInterface $now): array
    {
        $start = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)))->modify('monday this week')->setTime(0, 0, 0);
        $end = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)));

        return $this->buildLeaderboard($start, $end, 'This week');
    }

    /**
     * @return array<string, mixed>
     */
    public function agentOfMonth(\DateTimeInterface $now): array
    {
        $start = (new \DateTimeImmutable($now->format('Y-m-01')))->setTime(0, 0, 0);
        $end = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)));

        return $this->buildLeaderboard($start, $end, 'This month');
    }

    /**
     * Per active support group: open-ticket priority breakdown and agent of the week
     * scoped to tickets assigned to that group and members configured in agents settings.
     *
     * @return list<array<string, mixed>>
     */
    public function priorityMatrixBySupportGroup(\DateTimeInterface $now): array
    {
        $start = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)))->modify('monday this week')->setTime(0, 0, 0);
        $end = (new \DateTimeImmutable($now->format(\DateTimeInterface::ATOM)));
        $weights = HelpdeskSetting::screenAgentLeaderboardWeights();

        $groups = HelpdeskSupportGroup::query()
            ->where('is_active', true)
            ->with('members:id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $groups->map(function (HelpdeskSupportGroup $group) use ($start, $end, $weights): array {
            $memberIds = $group->members->pluck('id')->map(fn ($id) => (int) $id)->all();
            $statsByUser = $this->collectAgentStats($start, $end, $group->id, $memberIds);
            $agent = $this->pickTopAgent($statsByUser, $weights['tickets'], $weights['response']);

            return [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                ],
                'by_priority' => $this->byPriorityForGroup($group->id),
                'agent_of_week' => [
                    'period_label' => 'This week',
                    'weights' => $weights,
                    'agent' => $agent,
                ],
            ];
        })->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function byPriorityForGroup(int $groupId): array
    {
        $rows = HelpdeskTicket::query()
            ->where('assigned_group_id', $groupId)
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
     * @return array<string, mixed>
     */
    private function buildLeaderboard(\DateTimeInterface $start, \DateTimeInterface $end, string $periodLabel): array
    {
        $weights = HelpdeskSetting::screenAgentLeaderboardWeights();
        $statsByUser = $this->collectAgentStats($start, $end, null, null);
        $agent = $this->pickTopAgent($statsByUser, $weights['tickets'], $weights['response']);

        return [
            'period_label' => $periodLabel,
            'weights' => $weights,
            'agent' => $agent,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  list<int>|null  $memberIds  When set, only these group members may rank (per support group).
     */
    private function collectAgentStats(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?int $groupId,
        ?array $memberIds,
    ): array {
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $ticketIdsByUser = [];

        $responseRows = HelpdeskTicket::query()
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('first_response_at')
            ->whereBetween('first_response_at', [$startStr, $endStr])
            ->when($groupId !== null, fn ($q) => $q->where('assigned_group_id', $groupId))
            ->get(['id', 'assigned_user_id']);

        foreach ($responseRows as $row) {
            $userId = (int) $row->assigned_user_id;
            $ticketIdsByUser[$userId] ??= [];
            $ticketIdsByUser[$userId][(int) $row->id] = true;
        }

        $resolutionRows = HelpdeskTicket::query()
            ->whereNotNull('resolved_by_user_id')
            ->whereBetween('resolved_at', [$startStr, $endStr])
            ->when($groupId !== null, fn ($q) => $q->where('assigned_group_id', $groupId))
            ->get(['id', 'resolved_by_user_id']);

        foreach ($resolutionRows as $row) {
            $userId = (int) $row->resolved_by_user_id;
            $ticketIdsByUser[$userId] ??= [];
            $ticketIdsByUser[$userId][(int) $row->id] = true;
        }

        if ($ticketIdsByUser === []) {
            return [];
        }

        $responseAvgs = HelpdeskTicket::query()
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('first_response_at')
            ->whereBetween('first_response_at', [$startStr, $endStr])
            ->when($groupId !== null, fn ($q) => $q->where('assigned_group_id', $groupId))
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_min')
            ->pluck('avg_min', 'assigned_user_id');

        $eligibleUserIds = $this->eligibleAgentUserIds(array_keys($ticketIdsByUser));
        if ($memberIds !== null) {
            $memberSet = array_fill_keys($memberIds, true);
            $eligibleUserIds = array_values(array_filter(
                $eligibleUserIds,
                fn (int $userId) => isset($memberSet[$userId]),
            ));
        }

        $stats = [];
        foreach ($ticketIdsByUser as $userId => $ticketMap) {
            if (! in_array($userId, $eligibleUserIds, true)) {
                continue;
            }

            $avg = $responseAvgs[$userId] ?? null;
            $stats[$userId] = [
                'user_id' => $userId,
                'tickets_worked' => count($ticketMap),
                'avg_response_minutes' => $avg !== null ? (int) round((float) $avg) : null,
            ];
        }

        return $stats;
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    private function eligibleAgentUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $statsByUser
     * @return array<string, mixed>|null
     */
    private function pickTopAgent(array $statsByUser, int $ticketsWeight, int $responseWeight): ?array
    {
        if ($statsByUser === []) {
            return null;
        }

        $maxTickets = max(array_map(fn (array $s) => (int) $s['tickets_worked'], $statsByUser));
        $responseValues = array_values(array_filter(
            array_map(fn (array $s) => $s['avg_response_minutes'], $statsByUser),
            fn ($v) => $v !== null,
        ));
        $minResp = $responseValues !== [] ? min($responseValues) : null;
        $maxResp = $responseValues !== [] ? max($responseValues) : null;

        $totalWeight = max(1, $ticketsWeight + $responseWeight);

        $bestUserId = null;
        $bestScore = -1.0;
        $bestStats = null;

        foreach ($statsByUser as $userId => $stats) {
            $volumeScore = $maxTickets > 0 ? ((int) $stats['tickets_worked']) / $maxTickets : 0.0;

            $responseScore = 0.0;
            if ($stats['avg_response_minutes'] !== null && $minResp !== null && $maxResp !== null) {
                $responseScore = $maxResp > $minResp
                    ? ($maxResp - (int) $stats['avg_response_minutes']) / ($maxResp - $minResp)
                    : 1.0;
            }

            $score = (($ticketsWeight * $volumeScore) + ($responseWeight * $responseScore)) / $totalWeight;

            $isBetter = $score > $bestScore
                || ($score === $bestScore && $bestStats !== null && (int) $stats['tickets_worked'] > (int) $bestStats['tickets_worked'])
                || ($score === $bestScore && $bestStats !== null
                    && (int) $stats['tickets_worked'] === (int) $bestStats['tickets_worked']
                    && (int) ($stats['avg_response_minutes'] ?? PHP_INT_MAX) < (int) ($bestStats['avg_response_minutes'] ?? PHP_INT_MAX));

            if ($isBetter) {
                $bestScore = $score;
                $bestUserId = $userId;
                $bestStats = $stats;
            }
        }

        if ($bestUserId === null || $bestStats === null) {
            return null;
        }

        $user = User::query()->find($bestUserId, ['id', 'name', 'photo']);
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => StaffPhotoUrl::forUser($user),
            'tickets_worked' => (int) $bestStats['tickets_worked'],
            'avg_response_minutes' => $bestStats['avg_response_minutes'],
            'score' => round($bestScore * 100, 1),
        ];
    }
}
