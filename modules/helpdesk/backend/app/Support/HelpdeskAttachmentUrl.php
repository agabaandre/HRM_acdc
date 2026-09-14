<?php

namespace App\Support;

use App\Models\HelpdeskTicketAttachment;

/**
 * Signed attachment URLs for browser use (<img>, <a download>) without Bearer headers.
 */
final class HelpdeskAttachmentUrl
{
    public static function forAttachment(HelpdeskTicketAttachment $attachment): string
    {
        $ttl = (int) config('helpdesk.attachment_signed_ttl_seconds', 604800);
        $exp = time() + max(120, $ttl);
        $sig = self::sign($attachment, $exp);

        $path = '/api/v1/attachments/'.$attachment->id.'/file?exp='.$exp.'&sig='.$sig;
        $public = trim((string) config('helpdesk.api_public_url', ''));
        if ($public === '') {
            $public = rtrim((string) config('app.url'), '/');
        }

        return $public !== '' ? $public.$path : $path;
    }

    public static function sign(HelpdeskTicketAttachment $attachment, int $exp): string
    {
        $secret = (string) (config('helpdesk.attachment_signing_secret') ?: config('app.key'));

        return hash_hmac('sha256', $attachment->id.'|'.$attachment->ticket_id.'|'.$exp, $secret);
    }

    public static function verify(HelpdeskTicketAttachment $attachment, int $exp, string $sig): void
    {
        if ($exp < time()) {
            abort(403, 'Attachment link expired.');
        }

        $expected = self::sign($attachment, $exp);
        if (! hash_equals($expected, $sig)) {
            abort(403, 'Invalid attachment signature.');
        }
    }
}
