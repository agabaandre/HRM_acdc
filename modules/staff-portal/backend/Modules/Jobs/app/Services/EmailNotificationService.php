<?php

namespace Modules\Jobs\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class EmailNotificationService
{
    /**
     * Queue an email into email_notifications (CI golobal_log_email parity).
     */
    public function queue(
        string $trigger,
        string $emailTo,
        string $body,
        string $subject,
        int $staffId,
        string|false|null $endDate = null,
        string|false|null $nextDispatch = null,
        string|false|null $entryId = null,
    ): bool {
        if ($entryId) {
            DB::table('email_notifications')
                ->where('entry_id', $entryId)
                ->where('status', '!=', 1)
                ->delete();
        }

        $payload = [
            'entry_id' => $entryId ?: null,
            'trigger' => $trigger,
            'email_to' => $emailTo,
            'body' => $body,
            'staff_id' => $staffId,
            'subject' => $subject,
            'end_date' => $endDate === false ? null : $endDate,
            'next_dispatch' => $nextDispatch === false ? null : $nextDispatch,
        ];

        try {
            return (bool) DB::table('email_notifications')->insert($payload);
        } catch (\Throwable) {
            // Unique entry_id may already exist as sent (status=1); treat as skip.
            return false;
        }
    }

    public function entryExists(string $entryId): bool
    {
        return DB::table('email_notifications')->where('entry_id', $entryId)->exists();
    }

    public function render(string $view, array $data = []): string
    {
        $data['logoUrl'] = $data['logoUrl'] ?? (string) config('jobs.schedule.mail_logo_url');
        $data['portalBase'] = $data['portalBase'] ?? (string) config('jobs.schedule.portal_base_url');

        return View::make('jobs::emails.'.$view, $data)->render();
    }

    public function systemEmail(): string
    {
        $sys = strtolower(trim((string) config('jobs.schedule.system_email')));
        if ($sys === '' || $this->isBlockedAuditAddress($sys) || $this->isFromAddressNotAuditBcc($sys)) {
            return 'system@africacdc.org';
        }

        return $sys;
    }

    /**
     * Append the audit BCC inbox to a semicolon-separated recipient list.
     * registry@africacdc.org is never appended (use system@ only).
     */
    public function appendSystemInbox(string $emailTo): string
    {
        $parts = preg_split('/[;,]+/', $emailTo) ?: [];
        $cleaned = [];
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email === '' || $this->isBlockedAuditAddress($email)) {
                continue;
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $cleaned[] = $email;
        }

        $sys = $this->systemEmail();
        if ($sys !== '' && ! in_array($sys, $cleaned, true)) {
            $cleaned[] = $sys;
        }

        return implode(';', array_values(array_unique($cleaned)));
    }

    public function isBlockedAuditAddress(string $email): bool
    {
        $email = strtolower(trim($email));

        return $email === 'registry@africacdc.org'
            || str_ends_with($email, '@registry.africacdc.org');
    }

    /**
     * MAIL_FROM / notifications@ must not be used as the audit BCC.
     */
    public function isFromAddressNotAuditBcc(string $email): bool
    {
        $email = strtolower(trim($email));
        $from = strtolower(trim((string) (config('mail.from.address') ?: env('MAIL_FROM_ADDRESS', ''))));

        return $email === 'notifications@africacdc.org'
            || ($from !== '' && $email === $from);
    }

    public function purgeTestRecipients(): void
    {
        DB::table('email_notifications')->where('email_to', 'like', '%xxx%')->delete();
    }
}
