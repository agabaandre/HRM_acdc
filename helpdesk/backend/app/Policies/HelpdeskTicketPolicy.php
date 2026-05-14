<?php

namespace App\Policies;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;

class HelpdeskTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->helpdeskProfile !== null;
    }

    public function view(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }
        if ($this->elevated($p)) {
            return true;
        }
        if ($p->role === HelpdeskProfile::ROLE_USER && $p->staff_id) {
            if ((int) $ticket->requester_staff_id === (int) $p->staff_id) {
                return true;
            }
            if ((int) $ticket->created_by_user_id === (int) $user->id) {
                return true;
            }
        }

        return (int) $ticket->assigned_user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->helpdeskProfile !== null;
    }

    public function update(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }
        if (in_array($p->role, [HelpdeskProfile::ROLE_ADMIN, HelpdeskProfile::ROLE_SUPERVISOR], true)) {
            return true;
        }
        if ($p->role === HelpdeskProfile::ROLE_AGENT && (int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }
        if ($p->role === HelpdeskProfile::ROLE_USER && $p->staff_id
            && (int) $ticket->requester_staff_id === (int) $p->staff_id
            && in_array($ticket->status, ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'], true)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;

        return $p && $p->role === HelpdeskProfile::ROLE_ADMIN;
    }

    /**
     * Add a comment on the ticket (public or internal notes for staff).
     */
    public function comment(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }

        if ($this->elevated($p)) {
            return true;
        }

        if ($p->role === HelpdeskProfile::ROLE_AGENT && (int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        return $p->role === HelpdeskProfile::ROLE_USER && $p->staff_id
            && ((int) $ticket->requester_staff_id === (int) $p->staff_id
                || (int) $ticket->created_by_user_id === (int) $user->id)
            && in_array($ticket->status, ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'], true);
    }

    public function commentInternal(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        $p = $user->helpdeskProfile;

        return $p && in_array($p->role, [
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true);
    }

    public function attachFiles(User $user, HelpdeskTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function submitResolution(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }
        if (in_array($p->role, [HelpdeskProfile::ROLE_ADMIN, HelpdeskProfile::ROLE_SUPERVISOR], true)) {
            return $this->view($user, $ticket);
        }
        if ($p->role === HelpdeskProfile::ROLE_AGENT && (int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    private function elevated(HelpdeskProfile $p): bool
    {
        return in_array($p->role, [
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true);
    }
}
