<?php

namespace App\Services;

use App\Jobs\CategorizeTicketWithAi;
use App\Jobs\ImportEmailTicketAttachmentsJob;
use App\Models\HelpdeskBusinessUnit;
use App\Models\HelpdeskEmailMessage;
use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessUnitMailboxIntakeService
{
    public function __construct(
        private ExchangeGraphMailReader $reader,
        private StaffDirectoryLookupService $directory,
        private TicketNumberGenerator $numbers,
        private TicketSubjectGenerator $subjects,
        private EmailBodyNormalizer $bodyNormalizer,
    ) {}

    /**
     * Dry-run: list unread Inbox messages for this unit's mailbox (does not create tickets).
     *
     * @return array{mailbox:string,messages:list<array<string,mixed>>,count:int}
     */
    public function previewUnread(HelpdeskBusinessUnit $unit, int $top = 10): array
    {
        $mailbox = trim((string) $unit->support_mailbox);
        if ($mailbox === '' || ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('This business unit has no valid support mailbox.');
        }

        $raw = $this->reader->listUnreadInbox($mailbox, max(1, min(25, $top)));
        $messages = [];
        foreach ($raw as $message) {
            if (! is_array($message)) {
                continue;
            }
            $from = $message['from']['emailAddress'] ?? [];
            $graphId = trim((string) ($message['id'] ?? ''));
            $imported = $graphId !== '' ? $this->importedTicketState($graphId) : ['ticket' => null, 'row' => null];
            $ticket = $imported['ticket'];
            $messages[] = [
                'id' => $message['id'] ?? null,
                'subject' => $message['subject'] ?? null,
                'from_name' => is_array($from) ? ($from['name'] ?? null) : null,
                'from_email' => is_array($from) ? ($from['address'] ?? null) : null,
                'received_at' => $message['receivedDateTime'] ?? null,
                'preview' => mb_substr((string) ($message['bodyPreview'] ?? ''), 0, 240),
                'already_imported' => $ticket !== null,
                'ticket_missing' => $imported['row'] !== null && $ticket === null,
                'ticket_number' => $ticket?->ticket_number,
                'ticket_id' => $ticket?->id,
            ];
        }

        return [
            'mailbox' => $mailbox,
            'count' => count($messages),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{
     *     created:int,
     *     skipped:int,
     *     errors:int,
     *     reason:?string,
     *     skipped_items:list<array{reason:string,subject:?string,ticket_id:?int,ticket_number:?string}>,
     *     created_items:list<array{ticket_id:int,ticket_number:string,subject:?string}>
     * }
     */
    public function pollUnit(HelpdeskBusinessUnit $unit): array
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;
        $reason = null;
        $skippedItems = [];
        $createdItems = [];

        if (! HelpdeskSetting::emailTicketIntakeEnabled()) {
            return $this->pollResult(0, 0, 0, 'master_disabled');
        }

        if (! $unit->email_intake_enabled) {
            return $this->pollResult(0, 0, 0, 'unit_disabled');
        }

        $mailbox = trim((string) $unit->support_mailbox);
        if ($mailbox === '' || ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return $this->pollResult(0, 0, 0, 'no_mailbox');
        }

        $messages = $this->reader->listUnreadInbox($mailbox, 25);

        foreach ($messages as $message) {
            if (! is_array($message)) {
                $skipped++;
                $skippedItems[] = $this->skipItem('invalid_message', null, null, null);
                continue;
            }

            $graphId = trim((string) ($message['id'] ?? ''));
            $subject = mb_substr((string) ($message['subject'] ?? ''), 0, 200);
            if ($graphId === '') {
                $skipped++;
                $skippedItems[] = $this->skipItem('missing_graph_id', $subject, null, null);
                continue;
            }

            $state = $this->importedTicketState($graphId);
            if ($state['ticket'] !== null) {
                $skipped++;
                $skippedItems[] = $this->skipItem(
                    'already_imported',
                    $subject,
                    (int) $state['ticket']->id,
                    (string) $state['ticket']->ticket_number,
                );
                $this->tryMoveToProcessed($mailbox, $graphId, (int) $state['ticket']->id);
                continue;
            }
            $state['row']?->delete();

            try {
                $lock = Cache::lock('email-intake:'.$graphId, 120);
                if (! $lock->block(5)) {
                    $skipped++;
                    $skippedItems[] = $this->skipItem('lock_busy', $subject, null, null);
                    continue;
                }

                try {
                    $state = $this->importedTicketState($graphId);
                    if ($state['ticket'] !== null) {
                        $skipped++;
                        $skippedItems[] = $this->skipItem(
                            'already_imported',
                            $subject,
                            (int) $state['ticket']->id,
                            (string) $state['ticket']->ticket_number,
                        );
                        $this->tryMoveToProcessed($mailbox, $graphId, (int) $state['ticket']->id);
                        continue;
                    }
                    $state['row']?->delete();

                    $ticket = $this->createTicketFromMessage($unit, $message);
                    HelpdeskEmailMessage::query()->create([
                        'business_unit_id' => $unit->id,
                        'graph_message_id' => $graphId,
                        'internet_message_id' => isset($message['internetMessageId'])
                            ? mb_substr((string) $message['internetMessageId'], 0, 512)
                            : null,
                        'ticket_id' => $ticket->id,
                        'from_email' => $this->fromAddress($message),
                        'subject' => mb_substr((string) ($message['subject'] ?? ''), 0, 500),
                        'processed_at' => now(),
                        'raw_meta' => [
                            'receivedDateTime' => $message['receivedDateTime'] ?? null,
                            'conversationId' => $message['conversationId'] ?? null,
                        ],
                    ]);

                    [$rawBody, $rawType] = $this->rawBody($message);
                    ImportEmailTicketAttachmentsJob::dispatch(
                        $ticket->id,
                        $mailbox,
                        $graphId,
                        $rawBody,
                        $rawType,
                    );

                    $duty = null;
                    if ($ticket->requester_staff_id) {
                        $duty = $this->directory->dutyStationForStaffId((int) $ticket->requester_staff_id);
                    }

                    CategorizeTicketWithAi::dispatch($ticket->id, $duty);

                    $this->tryMoveToProcessed($mailbox, $graphId, $ticket->id);

                    $created++;
                    $createdItems[] = [
                        'ticket_id' => $ticket->id,
                        'ticket_number' => (string) $ticket->ticket_number,
                        'subject' => $subject !== '' ? $subject : null,
                    ];
                } finally {
                    $lock->release();
                }
            } catch (Throwable $e) {
                $errors++;
                Log::error('helpdesk.email_intake.message_failed', [
                    'business_unit_id' => $unit->id,
                    'graph_message_id' => $graphId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'reason' => $reason,
            'skipped_items' => $skippedItems,
            'created_items' => $createdItems,
        ];
    }

    /**
     * @param  list<array{reason:string,subject:?string,ticket_id:?int,ticket_number:?string}>  $skippedItems
     * @param  list<array{ticket_id:int,ticket_number:string,subject:?string}>  $createdItems
     * @return array{
     *     created:int,
     *     skipped:int,
     *     errors:int,
     *     reason:?string,
     *     skipped_items:list<array{reason:string,subject:?string,ticket_id:?int,ticket_number:?string}>,
     *     created_items:list<array{ticket_id:int,ticket_number:string,subject:?string}>
     * }
     */
    private function pollResult(
        int $created,
        int $skipped,
        int $errors,
        ?string $reason,
        array $skippedItems = [],
        array $createdItems = [],
    ): array {
        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'reason' => $reason,
            'skipped_items' => $skippedItems,
            'created_items' => $createdItems,
        ];
    }

    /**
     * @return array{reason:string,subject:?string,ticket_id:?int,ticket_number:?string}
     */
    private function skipItem(string $reason, ?string $subject, ?int $ticketId, ?string $ticketNumber): array
    {
        return [
            'reason' => $reason,
            'subject' => $subject !== '' ? $subject : null,
            'ticket_id' => $ticketId,
            'ticket_number' => $ticketNumber,
        ];
    }

    private function tryMoveToProcessed(string $mailbox, string $graphId, int $ticketId): void
    {
        try {
            $this->reader->markReadAndMoveToProcessed($mailbox, $graphId);
        } catch (Throwable $e) {
            Log::warning('helpdesk.email_intake.move_failed', [
                'graph_message_id' => $graphId,
                'ticket_id' => $ticketId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function createTicketFromMessage(HelpdeskBusinessUnit $unit, array $message): HelpdeskTicket
    {
        $fromEmail = $this->fromAddress($message);
        $fromName = $this->fromName($message);
        [$rawBody, $rawType] = $this->rawBody($message);
        $description = $this->bodyNormalizer->toTicketHtml($rawBody, $rawType);

        $subjectLine = trim((string) ($message['subject'] ?? ''));
        if ($subjectLine === '') {
            $subjectLine = 'Email request';
        }
        // Prefer clean mailbox subject; avoid stuffing the whole thread into the subject line.
        $displaySubject = mb_substr(html_entity_decode($subjectLine, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 0, 255);

        $resolved = $fromEmail ? $this->directory->resolveByWorkEmail($fromEmail) : null;
        $requesterStaffId = $resolved['staff_id'] ?? null;
        $requesterName = $resolved['name'] ?? ($fromName !== '' ? $fromName : ($fromEmail ?: 'Email requester'));
        $requesterEmail = $resolved['work_email'] ?? $fromEmail;
        $dutyStation = $resolved['duty_station_name'] ?? null;
        if ($requesterStaffId && (! $dutyStation || $dutyStation === '')) {
            $dutyStation = $this->directory->dutyStationForStaffId((int) $requesterStaffId);
        }

        $meta = ['email_intake' => true];
        if ($dutyStation) {
            $meta['requester_duty_station'] = $dutyStation;
        }

        $ticket = HelpdeskTicket::query()->create([
            'created_by_user_id' => null,
            'ticket_number' => $this->numbers->next(),
            'category_id' => null,
            'business_unit_id' => $unit->id,
            'subject' => $displaySubject,
            'description' => $description,
            'priority' => 'medium',
            'status' => 'open',
            'source' => 'email',
            'agent_logged_for_requester' => false,
            'requester_staff_id' => $requesterStaffId,
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'is_anonymous' => false,
            'directorate_id' => $resolved['directorate_id'] ?? null,
            'division_id' => $resolved['division_id'] ?? null,
            'meta' => $meta,
        ]);

        return $ticket;
    }

    /**
     * @return array{ticket:?HelpdeskTicket,row:?HelpdeskEmailMessage}
     */
    private function importedTicketState(string $graphId): array
    {
        $row = HelpdeskEmailMessage::query()->where('graph_message_id', $graphId)->first();
        if (! $row) {
            return ['ticket' => null, 'row' => null];
        }

        $ticket = $row->ticket_id
            ? HelpdeskTicket::query()->find($row->ticket_id)
            : null;

        return ['ticket' => $ticket, 'row' => $row];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function fromAddress(array $message): ?string
    {
        $address = $message['from']['emailAddress']['address'] ?? null;

        return is_string($address) && $address !== '' ? strtolower(trim($address)) : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function fromName(array $message): string
    {
        $name = $message['from']['emailAddress']['name'] ?? '';

        return is_string($name) ? trim($name) : '';
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{0: string, 1: string}
     */
    private function rawBody(array $message): array
    {
        $body = $message['body'] ?? null;
        if (is_array($body) && isset($body['content']) && is_string($body['content']) && trim($body['content']) !== '') {
            return [$body['content'], strtolower((string) ($body['contentType'] ?? 'html'))];
        }

        $preview = trim((string) ($message['bodyPreview'] ?? ''));
        if ($preview !== '') {
            return [$preview, 'text'];
        }

        return ['', 'html'];
    }
}
