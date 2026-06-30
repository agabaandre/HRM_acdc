<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
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
     * @return array<string, mixed>
     */
    private function buildLeaderboard(\DateTimeInterface $start, \DateTimeInterface $end, string $periodLabel): array
    {
        $weights = HelpdeskSetting::screenAgentLeaderboardWeights();
        $statsByUser = $this->collectAgentStats($start, $end);
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
    private function collectAgentStats(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        $ticketIdsByUser = [];

        $responseRows = HelpdeskTicket::query()
            ->whereNotNull('assigned_user_id')
            ->whereNotNull('first_response_at')
            ->whereBetween('first_response_at', [$startStr, $endStr])
            ->get(['id', 'assigned_user_id']);

        foreach ($responseRows as $row) {
            $userId = (int) $row->assigned_user_id;
            $ticketIdsByUser[$userId] ??= [];
            $ticketIdsByUser[$userId][(int) $row->id] = true;
        }

        $resolutionRows = HelpdeskTicket::query()
            ->whereNotNull('resolved_by_user_id')
            ->whereBetween('resolved_at', [$startStr, $endStr])
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
            ->groupBy('assigned_user_id')
            ->selectRaw('assigned_user_id, AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) AS avg_min')
            ->pluck('avg_min', 'assigned_user_id');

        $eligibleUserIds = $this->eligibleAgentUserIds(array_keys($ticketIdsByUser));

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
