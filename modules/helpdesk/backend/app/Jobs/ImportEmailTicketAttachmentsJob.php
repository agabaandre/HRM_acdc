<?php

namespace App\Jobs;

use App\Models\HelpdeskTicket;
use App\Services\EmailTicketAttachmentImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copy Graph file/inline attachments onto a ticket after the ticket row exists.
 * Kept off the intake request so logging is not blocked by large mailbox payloads.
 */
class ImportEmailTicketAttachmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public function __construct(
        public int $ticketId,
        public string $mailbox,
        public string $graphMessageId,
        public string $rawBody,
        public string $contentType,
    ) {
        $this->onQueue('helpdesk');
    }

    public function handle(EmailTicketAttachmentImporter $importer): void
    {
        $ticket = HelpdeskTicket::query()->find($this->ticketId);
        if (! $ticket) {
            return;
        }

        try {
            $html = $importer->importForMessage(
                $ticket,
                $this->mailbox,
                $this->graphMessageId,
                $this->rawBody,
                $this->contentType,
            );
            if ($html !== $ticket->description) {
                $ticket->description = $html;
                $ticket->save();
            }
        } catch (Throwable $e) {
            Log::warning('helpdesk.email_intake.attachments_failed', [
                'ticket_id' => $ticket->id,
                'graph_message_id' => $this->graphMessageId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
