<?php

namespace App\Services;

use App\Support\StaffApiBaseUrl;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Staff\Shared\StaffPortalMailClient;

/**
 * Helpdesk bridge: prefer Share mail hub, fall back to Laravel Mail.
 */
class CbpMailDispatcher
{
    public function sendMailable(string|array $to, Mailable $mailable): void
    {
        if (Mail::getFacadeRoot() instanceof MailFake) {
            Mail::to($to)->send($mailable);

            return;
        }

        $mode = strtolower(trim((string) env('STAFF_MAIL_DISPATCH', 'auto')));
        if ($mode === 'local') {
            Mail::to($to)->send($mailable);

            return;
        }

        $html = $mailable->render();
        $subject = (string) ($mailable->envelope()->subject ?? $mailable->subject ?? 'Notification');

        $client = $this->makeClient(
            $mode,
            function (string|array $recipients, string $subj, string $body) use ($mailable): void {
                Mail::html($body, function ($message) use ($recipients, $subj, $mailable): void {
                    $message->to($recipients)->subject($subj);
                    $from = $mailable->envelope()->from;
                    if ($from) {
                        $message->from($from->address, $from->name);
                    }
                });
            }
        );

        $client->send($to, $subject, $html);
    }

    /**
     * @param  callable(string|array, string, string, array): void  $localSender
     */
    private function makeClient(string $mode, callable $localSender): StaffPortalMailClient
    {
        $cfg = config('helpdesk.staff_api', []);
        $base = StaffApiBaseUrl::resolve((string) ($cfg['base_url'] ?? 'http://127.0.0.1/staff/backend'));

        return new StaffPortalMailClient(
            baseUrl: $base,
            token: isset($cfg['token']) ? (string) $cfg['token'] : null,
            username: isset($cfg['username']) ? (string) $cfg['username'] : null,
            password: isset($cfg['password']) ? (string) $cfg['password'] : null,
            dispatch: $mode === 'portal' ? 'portal' : 'auto',
            configKey: env('STAFF_MAIL_CONFIG_KEY') ?: (string) config('app.key'),
            localSender: $localSender,
        );
    }
}
