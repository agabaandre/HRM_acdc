<?php

namespace App\Services;

use App\Models\HelpdeskTicket;
use App\Models\HelpdeskTicketAttachment;
use App\Support\HelpdeskAttachmentUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies Microsoft Graph file attachments onto a helpdesk ticket and rewrites
 * cid: image references in the email HTML so they render on ticket details.
 */
class EmailTicketAttachmentImporter
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'zip',
    ];

    /** @var list<string> */
    private const BLOCKED_NAMES = [
        'winmail.dat',
        'oledata.mso',
    ];

    public function __construct(
        private ExchangeGraphMailReader $reader,
        private EmailBodyNormalizer $bodyNormalizer,
    ) {}

    public function importForMessage(
        HelpdeskTicket $ticket,
        string $mailbox,
        string $graphMessageId,
        string $rawBody,
        string $contentType = 'html',
    ): string {
        $mailbox = trim($mailbox);
        $graphMessageId = trim($graphMessageId);
        if ($mailbox === '' || $graphMessageId === '') {
            return $this->bodyNormalizer->toTicketHtml($rawBody, $contentType);
        }

        try {
            $items = $this->reader->listMessageAttachments($mailbox, $graphMessageId);
        } catch (Throwable $e) {
            Log::warning('helpdesk.email_intake.attachments_list_failed', [
                'ticket_id' => $ticket->id,
                'message' => $e->getMessage(),
            ]);

            return $this->bodyNormalizer->toTicketHtml($rawBody, $contentType);
        }

        $workingBody = $this->bodyForCidMatching($rawBody, $contentType);
        $cidUrls = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $stored = $this->storeFileAttachment($ticket, $mailbox, $graphMessageId, $item, $workingBody);
            if ($stored === null) {
                continue;
            }
            [$row, $contentId] = $stored;
            if ($contentId !== null) {
                $cidUrls[$contentId] = HelpdeskAttachmentUrl::forAttachment($row);
            }
        }

        if ($cidUrls !== []) {
            $workingBody = $this->rewriteCidReferences($workingBody, $cidUrls);
        }

        return $this->bodyNormalizer->toTicketHtml($workingBody, $contentType);
    }

    private function bodyForCidMatching(string $rawBody, string $contentType): string
    {
        $type = strtolower(trim($contentType));
        if ($type === 'text' || $type === 'text/plain') {
            return $rawBody;
        }

        $decoded = $this->bodyNormalizer->decodeOverEscapedHtml($rawBody);

        return $this->bodyNormalizer->stripHtmlSignatures(
            $this->bodyNormalizer->stripHtmlReplies($decoded)
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: HelpdeskTicketAttachment, 1: ?string}|null
     */
    private function storeFileAttachment(
        HelpdeskTicket $ticket,
        string $mailbox,
        string $graphMessageId,
        array $item,
        string $workingBody,
    ): ?array {
        $odataType = strtolower((string) ($item['@odata.type'] ?? ''));
        if ($odataType !== '' && ! str_contains($odataType, 'fileattachment')) {
            return null;
        }

        $name = basename(str_replace('\\', '/', (string) ($item['name'] ?? 'attachment')));
        if ($name === '' || $this->isBlockedName($name) || $this->isSignatureAttachment($name, $item)) {
            return null;
        }

        $mime = strtolower(trim((string) ($item['contentType'] ?? '')));
        if (! $this->isAllowed($name, $mime)) {
            return null;
        }

        $declaredSize = (int) ($item['size'] ?? 0);
        if ($declaredSize > self::MAX_BYTES) {
            return null;
        }

        if (HelpdeskTicketAttachment::query()
            ->where('ticket_id', $ticket->id)
            ->where('original_name', $name)
            ->exists()) {
            return null;
        }

        $contentId = $this->normalizeContentId(isset($item['contentId']) ? (string) $item['contentId'] : null);
        $referenced = $contentId !== null && $this->bodyReferencesCid($workingBody, $contentId);
        $isImage = str_starts_with($mime, 'image/') || (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $name);
        if ($isImage && $this->isInlineFlag($item) && ! $referenced) {
            return null;
        }

        $bytes = $this->attachmentBytes($mailbox, $graphMessageId, $item);
        if ($bytes === null) {
            return null;
        }

        $storeInline = $referenced && $isImage;

        $dir = $storeInline
            ? 'helpdesk/'.$ticket->id.'/inline'
            : 'helpdesk/'.$ticket->id;

        $safeName = $this->safeFilename($name);
        $path = $dir.'/'.Str::uuid()->toString().'_'.$safeName;

        Storage::disk('public')->put($path, $bytes);

        $row = HelpdeskTicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => mb_substr($name, 0, 512),
            'size_bytes' => strlen($bytes),
            'mime_type' => $mime !== '' ? mb_substr($mime, 0, 191) : null,
            'uploaded_by' => null,
        ]);

        return [$row, $storeInline ? $contentId : null];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function attachmentBytes(string $mailbox, string $graphMessageId, array $item): ?string
    {
        $encoded = $item['contentBytes'] ?? null;
        if (is_string($encoded) && $encoded !== '') {
            $decoded = base64_decode($encoded, true);
            if ($decoded !== false && $decoded !== '') {
                if (strlen($decoded) > self::MAX_BYTES) {
                    return null;
                }

                return $decoded;
            }
        }

        $attachmentId = trim((string) ($item['id'] ?? ''));
        if ($attachmentId === '') {
            return null;
        }

        try {
            $raw = $this->reader->downloadMessageAttachmentBytes($mailbox, $graphMessageId, $attachmentId);
        } catch (Throwable $e) {
            Log::warning('helpdesk.email_intake.attachment_download_failed', [
                'attachment_id' => $attachmentId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if ($raw === '' || strlen($raw) > self::MAX_BYTES) {
            return null;
        }

        return $raw;
    }

    private function isBlockedName(string $name): bool
    {
        return in_array(strtolower($name), self::BLOCKED_NAMES, true);
    }

    /**
     * CodeTwo / Exchange signature assets (social icons, banners, logo slices).
     *
     * @param  array<string, mixed>  $item
     */
    private function isSignatureAttachment(string $name, array $item): bool
    {
        $hay = strtolower($name.' '.(string) ($item['contentId'] ?? ''));

        return str_contains($hay, 'c2_signature_');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isInlineFlag(array $item): bool
    {
        $flag = $item['isInline'] ?? false;
        if (is_bool($flag)) {
            return $flag;
        }

        return filter_var($flag, FILTER_VALIDATE_BOOLEAN) || $flag === 1 || $flag === '1';
    }

    private function isAllowed(string $name, string $mime): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return true;
        }

        return in_array($mime, [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
            'application/zip',
        ], true);
    }

    private function safeFilename(string $name): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'attachment';

        return mb_substr($safe, 0, 120);
    }

    private function normalizeContentId(?string $contentId): ?string
    {
        if ($contentId === null) {
            return null;
        }
        $id = trim($contentId);
        $id = trim($id, '<>');
        $id = preg_replace('/^cid:/i', '', $id) ?? $id;

        return $id !== '' ? $id : null;
    }

    private function bodyReferencesCid(string $html, string $contentId): bool
    {
        return preg_match('/cid:\s*'.preg_quote($contentId, '/').'/i', $html) === 1;
    }

    /**
     * @param  array<string, string>  $cidUrls
     */
    private function rewriteCidReferences(string $html, array $cidUrls): string
    {
        foreach ($cidUrls as $contentId => $url) {
            $html = preg_replace(
                '/cid:\s*'.preg_quote($contentId, '/').'/i',
                htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                $html
            ) ?? $html;
        }

        return $html;
    }
}
