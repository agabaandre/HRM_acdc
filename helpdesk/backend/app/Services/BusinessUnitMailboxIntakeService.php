<?php

namespace App\Services;

use App\Jobs\CategorizeTicketWithAi;
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
            $messages[] = [
                'id' => $message['id'] ?? null,
                'subject' => $message['subject'] ?? null,
                'from_name' => is_array($from) ? ($from['name'] ?? null) : null,
                'from_email' => is_array($from) ? ($from['address'] ?? null) : null,
                'received_at' => $message['receivedDateTime'] ?? null,
                'preview' => mb_substr((string) ($message['bodyPreview'] ?? ''), 0, 240),
                'already_imported' => ! empty($message['id'])
                    && HelpdeskEmailMessage::query()->where('graph_message_id', (string) $message['id'])->exists(),
            ];
        }

        return [
            'mailbox' => $mailbox,
            'count' => count($messages),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{created:int,skipped:int,errors:int}
     */
    public function pollUnit(HelpdeskBusinessUnit $unit): array
    {
        $created = 0;
        $skipped = 0;
        $errors = 0;

        if (! HelpdeskSetting::emailTicketIntakeEnabled()) {
            return compact('created', 'skipped', 'errors');
        }

        if (! $unit->email_intake_enabled) {
            return compact('created', 'skipped', 'errors');
        }

        $mailbox = trim((string) $unit->support_mailbox);
        if ($mailbox === '' || ! filter_var($mailbox, FILTER_VALIDATE_EMAIL)) {
            return compact('created', 'skipped', 'errors');
        }

        $messages = $this->reader->listUnreadInbox($mailbox, 25);

        foreach ($messages as $message) {
            if (! is_array($message)) {
                $skipped++;
                continue;
            }

            $graphId = trim((string) ($message['id'] ?? ''));
            if ($graphId === '') {
                $skipped++;
                continue;
            }

            if (HelpdeskEmailMessage::query()->where('graph_message_id', $graphId)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $lock = Cache::lock('email-intake:'.$graphId, 30);
                if (! $lock->block(5)) {
                    $skipped++;
                    continue;
                }

                try {
                    if (HelpdeskEmailMessage::query()->where('graph_message_id', $graphId)->exists()) {
                        $skipped++;
                        continue;
                    }

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

                    $duty = null;
                    if ($ticket->requester_staff_id) {
                        $duty = $this->directory->dutyStationForStaffId((int) $ticket->requester_staff_id);
                    }

                    CategorizeTicketWithAi::dispatch($ticket->id, $duty);

                    try {
                        $this->reader->markReadAndMoveToProcessed($mailbox, $graphId);
                    } catch (Throwable $e) {
                        Log::warning('helpdesk.email_intake.move_failed', [
                            'graph_message_id' => $graphId,
                            'ticket_id' => $ticket->id,
                            'message' => $e->getMessage(),
                        ]);
                    }

                    $created++;
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

        return compact('created', 'skipped', 'errors');
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function createTicketFromMessage(HelpdeskBusinessUnit $unit, array $message): HelpdeskTicket
    {
        $fromEmail = $this->fromAddress($message);
        $fromName = $this->fromName($message);
        $body = $this->extractBody($message);
        $description = HtmlSanitizer::sanitize($body)
            ?? '<p>'.e(trim(strip_tags($body)) ?: 'Email body was empty.').'</p>';

        $subjectLine = trim((string) ($message['subject'] ?? ''));
        if ($subjectLine === '') {
            $subjectLine = 'Email request';
        }

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

        $displaySubject = $this->subjects->generateForBusinessUnit(
            $unit->name,
            (string) $requesterName,
            $description
        );
        // Prefer mailbox subject when present (still generated subject if empty above).
        if (trim((string) ($message['subject'] ?? '')) !== '') {
            $displaySubject = mb_substr($subjectLine, 0, 255);
        }

        return HelpdeskTicket::query()->create([
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
     */
    private function extractBody(array $message): string
    {
        $body = $message['body'] ?? null;
        if (is_array($body) && isset($body['content']) && is_string($body['content']) && trim($body['content']) !== '') {
            $content = $body['content'];
            $type = strtolower((string) ($body['contentType'] ?? 'text'));
            if ($type === 'text') {
                return '<p>'.nl2br(e($content)).'</p>';
            }

            return $content;
        }

        $preview = trim((string) ($message['bodyPreview'] ?? ''));
        if ($preview !== '') {
            return '<p>'.nl2br(e($preview)).'</p>';
        }

        return '<p>Email body was empty.</p>';
    }
}
