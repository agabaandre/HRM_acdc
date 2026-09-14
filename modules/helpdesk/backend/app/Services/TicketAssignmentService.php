<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskSupportGroup;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    public function __construct(
        private readonly AiAgentPickerService $aiAgentPicker,
        private readonly AgentCategoryRoutingService $routing,
    ) {}

    /**
     * @return list<int>
     */
    public function eligibleAgentUserIds(HelpdeskTicket $ticket): array
    {
        $categoryId = (int) $ticket->category_id;
        $excludeUserId = (int) ($ticket->created_by_user_id ?? 0);

        $agentUserIds = User::query()
            ->actsAsHelpdeskAgent()
            ->whereHas('helpdeskProfile', function ($q) {
                $q->where(function ($q) {
                    $q->where('is_agent_disabled', false)
                        ->orWhereNull('is_agent_disabled');
                });
            })
            ->pluck('id')
            ->all();

        if ($agentUserIds === []) {
            return [];
        }

        $eligible = [];
        foreach ($agentUserIds as $uid) {
            $uid = (int) $uid;
            if ($excludeUserId > 0 && $uid === $excludeUserId) {
                continue;
            }
            if ($this->routing->agentHandlesCategory($uid, $categoryId)) {
                $eligible[] = $uid;
            }
        }

        return $eligible;
    }

    /**
     * @return list<int>
     */
    public function eligibleGroupIds(HelpdeskTicket $ticket): array
    {
        return $this->routing->eligibleGroupsForCategory((int) $ticket->category_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Prefer same duty station, same division as ticket requester, then lowest open workload.
     *
     * @param  list<int>  $eligible
     * @return list<int>
     */
    public function rankAgentUserIds(array $eligible, HelpdeskTicket $ticket, ?string $requesterDutyStation): array
    {
        if ($eligible === []) {
            return [];
        }

        $profiles = HelpdeskProfile::query()
            ->whereIn('user_id', $eligible)
            ->get()
            ->keyBy('user_id');

        $reqDuty = $requesterDutyStation ? trim($requesterDutyStation) : '';
        $ticketDiv = $ticket->division_id ? (int) $ticket->division_id : null;

        $ranked = $eligible;
        usort($ranked, function (int $a, int $b) use ($profiles, $reqDuty, $ticketDiv) {
            $pa = $profiles->get($a);
            $pb = $profiles->get($b);
            $stationA = $pa?->duty_station ? trim((string) $pa->duty_station) : '';
            $stationB = $pb?->duty_station ? trim((string) $pb->duty_station) : '';

            $dutyMissA = ($reqDuty !== '' && strcasecmp($stationA, $reqDuty) !== 0) ? 1 : 0;
            $dutyMissB = ($reqDuty !== '' && strcasecmp($stationB, $reqDuty) !== 0) ? 1 : 0;
            if ($dutyMissA !== $dutyMissB) {
                return $dutyMissA <=> $dutyMissB;
            }

            $divA = $pa?->division_id !== null ? (int) $pa->division_id : null;
            $divB = $pb?->division_id !== null ? (int) $pb->division_id : null;
            $divMissA = ($ticketDiv !== null && $divA !== null && $divA === $ticketDiv) ? 0 : 1;
            $divMissB = ($ticketDiv !== null && $divB !== null && $divB === $ticketDiv) ? 0 : 1;
            if ($divMissA !== $divMissB) {
                return $divMissA <=> $divMissB;
            }

            $loadA = HelpdeskTicket::query()
                ->where('assigned_user_id', $a)
                ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                ->count();
            $loadB = HelpdeskTicket::query()
                ->where('assigned_user_id', $b)
                ->whereIn('status', ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'])
                ->count();

            return $loadA <=> $loadB;
        });

        return $ranked;
    }

    /**
     * @return array{user_id: ?int, group_id: ?int}
     */
    public function assignAgent(HelpdeskTicket $ticket, ?string $requesterDutyStation): array
    {
        $categoryId = (int) $ticket->category_id;
        $groups = $this->routing->eligibleGroupsForCategory($categoryId);

        $groupPick = $this->pickGroup($groups);
        $pool = $groupPick !== null
            ? $this->routing->eligibleMemberUserIdsForGroup($groupPick, $categoryId)
            : $this->eligibleAgentUserIds($ticket);

        $excludeUserId = (int) ($ticket->created_by_user_id ?? 0);
        if ($excludeUserId > 0) {
            $pool = array_values(array_filter($pool, fn (int $uid) => $uid !== $excludeUserId));
        }

        // Drop disabled agents from group pools as well.
        if ($pool !== []) {
            $enabled = User::query()
                ->whereIn('id', $pool)
                ->whereHas('helpdeskProfile', function ($q) {
                    $q->where(function ($q) {
                        $q->where('is_agent_disabled', false)
                            ->orWhereNull('is_agent_disabled');
                    });
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $pool = array_values(array_intersect($pool, $enabled));
        }

        if ($pool === []) {
            return ['user_id' => null, 'group_id' => $groupPick?->id];
        }

        $aiPick = $this->aiAgentPicker->pickUserId($ticket, $pool, $requesterDutyStation);
        if ($aiPick !== null && in_array($aiPick, $pool, true)) {
            return ['user_id' => $aiPick, 'group_id' => $groupPick?->id];
        }

        $ranked = $this->rankAgentUserIds($pool, $ticket, $requesterDutyStation);

        return [
            'user_id' => $ranked[0] ?? null,
            'group_id' => $groupPick?->id,
        ];
    }

    /**
     * Round-robin among helpdesk admins by open workload (AI categorization fallback).
     *
     * @return array{user_id: ?int, group_id: ?int}
     */
    public function assignAdminRoundRobin(HelpdeskTicket $ticket): array
    {
        return $this->assignRoundRobinAmongProfiles($ticket, function ($q) {
            $q->where('role', HelpdeskProfile::ROLE_ADMIN)
                ->orWhere('grant_helpdesk_admin', true);
        });
    }

    /**
     * Round-robin among supervisors (role or grant_supervisor_access) by open workload.
     * Used when category routing finds no eligible agent.
     *
     * @return array{user_id: ?int, group_id: ?int}
     */
    public function assignSupervisorRoundRobin(HelpdeskTicket $ticket): array
    {
        $result = $this->assignRoundRobinAmongProfiles($ticket, function ($q) {
            $q->where('role', HelpdeskProfile::ROLE_SUPERVISOR)
                ->orWhere('grant_supervisor_access', true);
        });

        if ($result['user_id']) {
            return $result;
        }

        // No supervisors configured — fall back to helpdesk admins.
        return $this->assignAdminRoundRobin($ticket);
    }

    /**
     * Prefer an eligible agent; if none, assign a supervisor (then admin) by load.
     * When enabled in settings, tickets created by an agent who is eligible for the
     * category (Agents & support groups routing) are assigned to that creator.
     *
     * @return array{user_id: ?int, group_id: ?int, fallback: bool}
     */
    public function assignAgentOrSupervisorFallback(HelpdeskTicket $ticket, ?string $requesterDutyStation): array
    {
        $creatorAssign = $this->assignToCreatingAgentIfEligible($ticket);
        if ($creatorAssign !== null) {
            return $creatorAssign;
        }

        $result = $this->assignAgent($ticket, $requesterDutyStation);
        if ($result['user_id'] || $result['group_id']) {
            return ['user_id' => $result['user_id'], 'group_id' => $result['group_id'], 'fallback' => false];
        }

        $fallback = $this->assignSupervisorRoundRobin($ticket);

        return [
            'user_id' => $fallback['user_id'],
            'group_id' => $fallback['group_id'],
            'fallback' => $fallback['user_id'] !== null,
        ];
    }

    /**
     * @return array{user_id: int, group_id: null, fallback: false}|null
     */
    public function assignToCreatingAgentIfEligible(HelpdeskTicket $ticket): ?array
    {
        if (! HelpdeskSetting::assignAgentCreatedTicketsToCreator()) {
            return null;
        }

        $creatorId = (int) ($ticket->created_by_user_id ?? 0);
        $categoryId = (int) ($ticket->category_id ?? 0);
        if ($creatorId < 1 || $categoryId < 1) {
            return null;
        }

        $creator = User::query()->with('helpdeskProfile')->find($creatorId);
        $profile = $creator?->helpdeskProfile;
        if ($profile === null || ! $profile->isEligibleForTicketRouting()) {
            return null;
        }

        if (! $this->routing->agentHandlesCategory($creatorId, $categoryId)) {
            return null;
        }

        return [
            'user_id' => $creatorId,
            'group_id' => null,
            'fallback' => false,
        ];
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Builder): void  $profileConstraint
     * @return array{user_id: ?int, group_id: ?int}
     */
    private function assignRoundRobinAmongProfiles(HelpdeskTicket $ticket, callable $profileConstraint): array
    {
        $excludeUserId = (int) ($ticket->created_by_user_id ?? 0);

        $ids = User::query()
            ->whereHas('helpdeskProfile', function ($q) use ($profileConstraint) {
                $q->where(function ($q) {
                    $q->where('is_agent_disabled', false)
                        ->orWhereNull('is_agent_disabled');
                })->where(function ($q) use ($profileConstraint) {
                    $profileConstraint($q);
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($excludeUserId > 0) {
            $ids = array_values(array_filter($ids, fn (int $id) => $id !== $excludeUserId));
        }

        if ($ids === []) {
            return ['user_id' => null, 'group_id' => null];
        }

        $ranked = $this->rankAgentUserIds($ids, $ticket, null);

        return [
            'user_id' => $ranked[0] ?? null,
            'group_id' => null,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, HelpdeskSupportGroup>  $groups
     */
    private function pickGroup($groups): ?HelpdeskSupportGroup
    {
        if ($groups->isEmpty()) {
            return null;
        }

        $openStatuses = ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'];

        return $groups->sortBy(function (HelpdeskSupportGroup $group) use ($openStatuses) {
            $load = HelpdeskTicket::query()
                ->where('assigned_group_id', $group->id)
                ->whereIn('status', $openStatuses)
                ->count();

            return [$load, $group->sort_order, $group->name];
        })->first();
    }
}
