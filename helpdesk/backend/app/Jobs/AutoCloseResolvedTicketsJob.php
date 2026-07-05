<?php

namespace App\Jobs;

use App\Mail\TicketClosedMail;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Services\TicketHistoryLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AutoCloseResolvedTicketsJob implements ShouldQueue
{
    use Queueable;

    public function handle(TicketHistoryLogger $logger): void
    {
        $days = HelpdeskSetting::resolvedAutoCloseDays();
        if ($days < 1) {
            return;
        }

        $cutoff = now()->subDays($days);
        $frontend = rtrim((string) config('helpdesk.frontend_url', 'http://localhost/staff/helpdesk'), '/');

        HelpdeskTicket::query()
            ->where('status', 'resolved')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($tickets) use ($logger, $frontend): void {
                foreach ($tickets as $ticket) {
                    $ticket->forceFill([
                        'status' => 'closed',
                        'closed_at' => now(),
                        'resolution_confirm_token' => null,
                    ])->save();

                    $logger->log($ticket, 'ticket.closed', null, [
                        'auto_closed' => true,
                        'auto_close_days' => HelpdeskSetting::resolvedAutoCloseDays(),
                    ]);

                    $email = trim((string) $ticket->requester_email);
                    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        try {
                            Mail::to($email)->send(new TicketClosedMail(
                                $ticket->fresh(),
                                $frontend.'/tickets/'.$ticket->id,
                                true,
                            ));
                        } catch (\Throwable $e) {
                            Log::warning('helpdesk.auto_close_mail_failed', [
                                'ticket_id' => $ticket->id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            });
    }
}
