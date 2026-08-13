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
        return trim((string) config('jobs.schedule.system_email'));
    }

    public function appendSystemInbox(string $emailTo): string
    {
        $sys = $this->systemEmail();
        if ($sys === '') {
            return $emailTo;
        }
        if (stripos($emailTo, $sys) !== false) {
            return $emailTo;
        }

        return rtrim($emailTo, ';').';'.$sys;
    }

    public function purgeTestRecipients(): void
    {
        DB::table('email_notifications')->where('email_to', 'like', '%xxx%')->delete();
    }
}
