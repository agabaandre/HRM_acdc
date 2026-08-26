<?php

namespace App\Console\Commands;

use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskTicket;
use App\Services\EmailTicketAttachmentImporter;
use Illuminate\Console\Command;

class ImportEmailTicketAttachmentsCommand extends Command
{
    protected $signature = 'helpdesk:import-email-attachments
                            {ticket : Ticket id or ticket number (e.g. 504 or HD-504)}';

    protected $description = 'Re-import Microsoft Graph attachments for an email-created helpdesk ticket';

    public function handle(EmailTicketAttachmentImporter $importer): int
    {
        $key = trim((string) $this->argument('ticket'));
        $ticket = HelpdeskTicket::query()
            ->when(ctype_digit($key), fn ($q) => $q->where('id', (int) $key))
            ->when(! ctype_digit($key), fn ($q) => $q->where('ticket_number', $key))
            ->first();

        if (! $ticket) {
            $this->error('Ticket not found: '.$key);

            return self::FAILURE;
        }

        $email = HelpdeskEmailMessage::query()
            ->with('businessUnit')
            ->where('ticket_id', $ticket->id)
            ->first();

        if (! $email) {
            $this->error('No stored mailbox message for ticket '.$ticket->id.'.');

            return self::FAILURE;
        }

        $mailbox = trim((string) ($email->businessUnit?->support_mailbox ?? ''));
        if ($mailbox === '' || $email->graph_message_id === '') {
            $this->error('Mailbox or Graph message id is missing.');

            return self::FAILURE;
        }

        $before = $ticket->attachments()->count();
        $html = $importer->importForMessage(
            $ticket,
            $mailbox,
            (string) $email->graph_message_id,
            (string) $ticket->description,
            'html',
        );
        if ($html !== $ticket->description) {
            $ticket->description = $html;
            $ticket->save();
        }

        $after = $ticket->attachments()->count();
        $this->info('Imported attachments for ticket '.$ticket->id.' ('.$ticket->ticket_number.'): '.$before.' → '.$after.'.');

        return self::SUCCESS;
    }
}
