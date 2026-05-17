<?php

namespace App\Services;

use App\Jobs\SendNotificationEmailJob;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * Weekly brief emails use the same queued notification job as approval alerts
 * ({@see SendNotificationEmailJob}), run synchronously from Artisan schedulers.
 */
final class WeeklyBriefingNotificationMailer
{
    /**
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     */
    public static function sendToStaff(
        Staff $recipient,
        string $messageSubject,
        string $headerTitle,
        string $innerHtml,
        string $type = 'weekly_briefing',
        ?Model $model = null,
        array $attachments = [],
    ): bool {
        if (! $recipient->work_email) {
            return false;
        }

        if ($attachments !== []) {
            return self::sendViaExchangeDirect(
                (string) $recipient->work_email,
                $messageSubject,
                $headerTitle,
                $innerHtml,
                $type,
                $attachments,
                $recipient,
            );
        }

        try {
            SendNotificationEmailJob::dispatchSync(
                $model,
                $recipient,
                $type,
                $messageSubject,
                'emails.weekly-briefing-notification',
                self::viewContext($headerTitle, $innerHtml, $attachments),
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing: notification mail failed', [
                'to' => $recipient->work_email,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     */
    public static function sendToAddress(
        string $email,
        string $messageSubject,
        string $headerTitle,
        string $innerHtml,
        string $type = 'weekly_briefing',
        array $attachments = [],
    ): bool {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return false;
        }

        if ($attachments !== []) {
            return self::sendViaExchangeDirect(
                $email,
                $messageSubject,
                $headerTitle,
                $innerHtml,
                $type,
                $attachments,
            );
        }

        try {
            SendNotificationEmailJob::dispatchSync(
                null,
                null,
                $type,
                $messageSubject,
                'emails.weekly-briefing-notification',
                array_merge(self::viewContext($headerTitle, $innerHtml, $attachments), [
                    'to_email' => $email,
                ]),
            );

            return true;
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing: notification mail failed', [
                'to' => $email,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     * @return array<string, mixed>
     */
    private static function viewContext(string $headerTitle, string $innerHtml, array $attachments): array
    {
        return [
            'headerTitle' => $headerTitle,
            'bodyHtml' => $innerHtml,
            'attachments' => $attachments,
            'skip_admin_cc' => true,
        ];
    }

    /**
     * PDF / binary attachments cannot be passed through {@see SendNotificationEmailJob} (queue JSON).
     * Uses the same {@see sendEmail()} → Exchange Graph path as other APM mail.
     *
     * @param  list<array{name: string, content: string, content_type?: string}>  $attachments
     */
    private static function sendViaExchangeDirect(
        string $toEmail,
        string $messageSubject,
        string $headerTitle,
        string $innerHtml,
        string $type,
        array $attachments,
        ?Staff $recipient = null,
    ): bool {
        try {
            $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';
            $htmlContent = View::make('emails.weekly-briefing-notification', array_merge(
                self::viewContext($headerTitle, $innerHtml, []),
                ['recipient' => $recipient],
            ))->render();

            return sendEmail(
                $toEmail,
                $subjectPrefix.$messageSubject,
                $htmlContent,
                null,
                null,
                [],
                ['system@africacdc.org'],
                $attachments,
            );
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing: notification mail failed (direct Exchange)', [
                'to' => $toEmail,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
