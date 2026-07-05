<?php

namespace App\Jobs;

use App\Mail\AgentOpenTicketsReminderMail;
use App\Models\HelpdeskProfile;
use App\Models\HelpdeskTicket;
use App\Models\User;
use App\Services\TicketAssigneeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AgentOpenTicketReminderJob implements ShouldQueue
{
    use Queueable;

    private const OPEN_STATUSES = ['open', 'pending', 'in_progress', 'awaiting_requester_confirmation'];

    public function handle(TicketAssigneeService $assignees): void
    {
        if (! \App\Models\HelpdeskSetting::agentOpenTicketReminderEnabled()) {
            return;
        }

        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost/staff/helpdesk'), '/');
        $deskUrl = $frontend.'/tickets';

        $agents = User::query()
            ->whereHas('helpdeskProfile', fn ($q) => $q->whereIn('role', [
                HelpdeskProfile::ROLE_AGENT,
                HelpdeskProfile::ROLE_SUPERVISOR,
                HelpdeskProfile::ROLE_ADMIN,
            ]))
            ->with('helpdeskProfile')
            ->get();

        foreach ($agents as $agent) {
            $tickets = HelpdeskTicket::query()
                ->assignedToUser((int) $agent->id)
                ->whereIn('status', self::OPEN_STATUSES)
                ->orderByDesc('priority')
                ->orderBy('created_at')
                ->limit(25)
                ->get(['id', 'ticket_number', 'subject', 'status', 'priority']);

            if ($tickets->isEmpty()) {
                continue;
            }

            $email = trim((string) $agent->email);
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $rows = $tickets->map(fn (HelpdeskTicket $t) => [
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'status' => $t->status,
                'priority' => $t->priority,
            ])->all();

            try {
                Mail::to($email)->send(new AgentOpenTicketsReminderMail($agent, $rows, $deskUrl));
            } catch (\Throwable $e) {
                Log::warning('helpdesk.agent_reminder_mail_failed', [
                    'user_id' => $agent->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
