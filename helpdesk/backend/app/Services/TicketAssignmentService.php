<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
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

        $agentUserIds = User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->where('role', HelpdeskProfile::ROLE_AGENT))
            ->pluck('id')
            ->all();

        if ($agentUserIds === []) {
            return [];
        }

        $eligible = [];
        foreach ($agentUserIds as $uid) {
            if ($this->routing->agentHandlesCategory((int) $uid, $categoryId)) {
                $eligible[] = (int) $uid;
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
