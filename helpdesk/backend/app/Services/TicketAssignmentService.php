<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketAssignmentService
{
    public function __construct(
        private readonly AiAgentPickerService $aiAgentPicker,
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
            $catIds = DB::table('helpdesk_agent_categories')->where('user_id', $uid)->pluck('category_id')->all();
            if ($catIds === [] || in_array($categoryId, array_map('intval', $catIds), true)) {
                $eligible[] = (int) $uid;
            }
        }

        return $eligible;
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

    public function assignAgent(HelpdeskTicket $ticket, ?string $requesterDutyStation): ?int
    {
        $eligible = $this->eligibleAgentUserIds($ticket);
        if ($eligible === []) {
            return null;
        }

        $aiPick = $this->aiAgentPicker->pickUserId($ticket, $eligible, $requesterDutyStation);
        if ($aiPick !== null && in_array($aiPick, $eligible, true)) {
            return $aiPick;
        }

        $ranked = $this->rankAgentUserIds($eligible, $ticket, $requesterDutyStation);

        return $ranked[0] ?? null;
    }
}
