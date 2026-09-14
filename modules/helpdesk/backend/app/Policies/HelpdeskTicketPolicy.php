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

        return (int) $ticket->assigned_user_id === (int) $user->id
            || $this->isTicketAssignee($user, $ticket);
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
        if ($p->hasSupervisorAccess()) {
            return true;
        }
        if ($p->actsAsAgent() && $this->isTicketAssignee($user, $ticket)) {
            return true;
        }
        if ($p->role === HelpdeskProfile::ROLE_USER && $p->staff_id
            && (int) $ticket->requester_staff_id === (int) $p->staff_id
            && in_array($ticket->status, ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation', 'closed', 'resolved'], true)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;

        return $p && $p->isHelpdeskAdmin();
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

        if ($p->actsAsAgent() && $this->isTicketAssignee($user, $ticket)) {
            return true;
        }

        return $p->role === HelpdeskProfile::ROLE_USER && $p->staff_id
            && ((int) $ticket->requester_staff_id === (int) $p->staff_id
                || (int) $ticket->created_by_user_id === (int) $user->id)
            && in_array($ticket->status, ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation', 'closed', 'resolved'], true);
    }

    /**
     * Requester reopens a closed or resolved ticket when the issue persists.
     */
    public function reopen(User $user, HelpdeskTicket $ticket): bool
    {
        $p = $user->helpdeskProfile;
        if (! $p || $p->role !== HelpdeskProfile::ROLE_USER || ! $p->staff_id) {
            return false;
        }

        if (! in_array($ticket->status, ['closed', 'resolved', 'awaiting_requester_confirmation'], true)) {
            return false;
        }

        return (int) $ticket->requester_staff_id === (int) $p->staff_id
            || (int) $ticket->created_by_user_id === (int) $user->id;
    }

    public function commentInternal(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        $p = $user->helpdeskProfile;

        return $p && ($p->actsAsAgent() || in_array($p->role, [
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true));
    }

    public function attachFiles(User $user, HelpdeskTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Remove a file uploaded with the original request (not inline editor images).
     */
    public function deleteRequestAttachment(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        $p = $user->helpdeskProfile;
        if (! $p || in_array($ticket->status, ['closed', 'resolved'], true)) {
            return false;
        }

        return $p->canDeleteRequestAttachments();
    }

    /**
     * Change issue category while the ticket remains open.
     */
    public function changeCategory(User $user, HelpdeskTicket $ticket): bool
    {
        if (! $this->view($user, $ticket)) {
            return false;
        }

        $p = $user->helpdeskProfile;
        if (! $p || in_array($ticket->status, ['closed', 'resolved'], true)) {
            return false;
        }

        return $p->canChangeTicketCategory();
    }

    public function submitResolution(User $user, HelpdeskTicket $ticket): bool
    {
        if (in_array($ticket->status, ['closed', 'resolved', 'awaiting_requester_confirmation'], true)) {
            return false;
        }

        $p = $user->helpdeskProfile;
        if (! $p) {
            return false;
        }
        if ($p->hasSupervisorAccess()) {
            return $this->view($user, $ticket);
        }
        if ($p->actsAsAgent() && $this->isTicketAssignee($user, $ticket)) {
            return true;
        }

        return false;
    }

    /**
     * Requester confirms satisfaction and closes a resolved ticket (ITIL closure).
     */
    public function confirmClose(User $user, HelpdeskTicket $ticket): bool
    {
        if ($ticket->status !== 'resolved') {
            return false;
        }

        $p = $user->helpdeskProfile;
        if (! $p || $p->role !== HelpdeskProfile::ROLE_USER || ! $p->staff_id) {
            return false;
        }

        return (int) $ticket->requester_staff_id === (int) $p->staff_id
            || (int) $ticket->created_by_user_id === (int) $user->id;
    }

    private function elevated(HelpdeskProfile $p): bool
    {
        return in_array($p->role, [
            HelpdeskProfile::ROLE_ADMIN,
            HelpdeskProfile::ROLE_SUPERVISOR,
            HelpdeskProfile::ROLE_AGENT,
            HelpdeskProfile::ROLE_AUDITOR,
        ], true) || $p->grant_supervisor_access === true;
    }

    private function isTicketAssignee(User $user, HelpdeskTicket $ticket): bool
    {
        if ((int) $ticket->assigned_user_id === (int) $user->id) {
            return true;
        }

        if ($ticket->relationLoaded('assignees')) {
            return $ticket->assignees->contains(fn ($assignee) => (int) $assignee->id === (int) $user->id);
        }

        return $ticket->assignees()->where('users.id', $user->id)->exists();
    }
}
