<?php

namespace Modules\Jobs\Services;

use App\Services\PortalMailer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendQueuedMailService
{
    public function __construct(
        private PortalMailer $mailer,
        private EmailNotificationService $notifications,
    ) {}

    /**
     * Full queue pass (non-birthday), CI send_mails parity.
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function sendScheduled(bool $sleep = false): array
    {
        $this->notifications->purgeTestRecipients();
        $today = date('Y-m-d');

        $messages = DB::table('email_notifications')
            ->where('next_dispatch', 'like', $today.'%')
            ->where('status', '!=', '1')
            ->where('email_to', 'not like', 'xx%')
            ->where('subject', 'not like', '%Birthday%')
            ->orderBy('id')
            ->limit(200)
            ->get();

        return $this->dispatchMessages($messages, $sleep);
    }

    /**
     * Instant subjects (performance / birthday), CI send_instant_mails parity.
     *
     * @return array{sent:int, failed:int, skipped:int}
     */
    public function sendInstant(bool $sleep = false): array
    {
        $today = date('Y-m-d');

        // Reclaim locks stuck > 15 minutes.
        DB::table('email_notifications')
            ->where('status', '2')
            ->where('next_dispatch', 'like', $today.'%')
            ->where('created_at', '<', now()->subMinutes(15))
            ->update(['status' => '0']);

        $messages = DB::table('email_notifications')
            ->where('next_dispatch', 'like', $today.'%')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere('status', '0')
                    ->orWhere('status', 0);
            })
            ->where(function ($q) {
                $q->where('subject', 'like', '%PPA%')
                    ->orWhere('subject', 'like', '%Midterm%')
                    ->orWhere('subject', 'like', '%Endterm%')
                    ->orWhere('subject', 'like', '%Consent%')
                    ->orWhere('subject', 'like', '%Birthday%')
                    ->orWhere('subject', 'like', '%Contract%')
                    ->orWhere('subject', 'like', '%Profile%');
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        return $this->dispatchMessages($messages, $sleep);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $messages
     * @return array{sent:int, failed:int, skipped:int}
     */
    protected function dispatchMessages($messages, bool $sleep): array
    {
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $counter = 0;

        foreach ($messages as $message) {
            $locked = DB::table('email_notifications')
                ->where('id', $message->id)
                ->whereNotIn('status', ['1', '2', 1, 2])
                ->update(['status' => '2']);

            if ($locked === 0) {
                // Also allow null/0 status lock
                $locked = DB::table('email_notifications')
                    ->where('id', $message->id)
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhereIn('status', ['', '0', 0]);
                    })
                    ->update(['status' => '2']);
            }

            if ($locked === 0) {
                $skipped++;

                continue;
            }

            $recipients = $this->splitRecipients((string) $message->email_to);
            if ($recipients === []) {
                DB::table('email_notifications')->where('id', $message->id)->update(['status' => '0']);
                $skipped++;

                continue;
            }

            [$to, $bcc] = $this->partitionToAndBcc($recipients);

            try {
                $this->mailer->send($to, (string) $message->subject, (string) $message->body, [], null, $bcc);
                $this->markDispatched((int) $message->id, (string) $message->subject, $message->end_date);
                $sent++;
                DB::table('email_notifications')
                    ->where('next_dispatch', '<', now()->subWeek())
                    ->where('status', '1')
                    ->delete();
            } catch (\Throwable $e) {
                Log::warning('jobs send mail failed', [
                    'id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
                $next = $this->nextRunDate($message->end_date)->format('Y-m-d');
                DB::table('email_notifications')->where('id', $message->id)->update([
                    'status' => '0',
                    'next_dispatch' => $next,
                ]);
                $failed++;
            }

            $counter++;
            if ($sleep) {
                sleep(1);
                if ($counter % 20 === 0) {
                    sleep(2);
                }
            }
        }

        return compact('sent', 'failed', 'skipped');
    }

    /**
     * @return list<string>
     */
    protected function splitRecipients(string $raw): array
    {
        $parts = preg_split('/[;,]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if ($this->notifications->isBlockedAuditAddress($email)) {
                continue;
            }
            $out[] = $email;
        }

        return array_values(array_unique($out));
    }

    /**
     * First address is To; remaining addresses (incl. system@) are BCC — CI async_mail parity.
     * Always BCC system@africacdc.org; never registry@.
     *
     * @param  list<string>  $recipients
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function partitionToAndBcc(array $recipients): array
    {
        $system = strtolower($this->notifications->systemEmail());
        $to = [];
        $bcc = [];

        foreach ($recipients as $email) {
            if ($email === $system) {
                $bcc[] = $email;

                continue;
            }
            if ($to === []) {
                $to[] = $email;
            } else {
                $bcc[] = $email;
            }
        }

        if ($to === [] && $bcc !== []) {
            // Should not happen for real staff mail; keep deliverable.
            $to = [array_shift($bcc)];
        }

        if ($system !== '' && ! in_array($system, $bcc, true) && ! in_array($system, $to, true)) {
            $bcc[] = $system;
        }

        $bcc = array_values(array_filter(
            array_unique($bcc),
            fn (string $email) => ! $this->notifications->isBlockedAuditAddress($email) && ! in_array($email, $to, true),
        ));

        return [$to, $bcc];
    }

    protected function markDispatched(int $id, string $subject, mixed $endDate): void
    {
        if (stripos($subject, 'Birthday') !== false) {
            DB::table('email_notifications')->where('id', $id)->update([
                'status' => '1',
                'next_dispatch' => now()->toDateTimeString(),
            ]);

            return;
        }

        $next = $this->nextRunDate($endDate);
        $status = $next->isToday() ? '1' : '0';
        DB::table('email_notifications')->where('id', $id)->update([
            'status' => $status,
            'next_dispatch' => $next->format('Y-m-d'),
        ]);
    }

    protected function nextRunDate(mixed $endDate): Carbon
    {
        $end = $endDate ? Carbon::parse((string) $endDate)->startOfDay() : Carbon::today();
        $today = Carbon::today();
        $thresholds = [90, 30, 21, 14, 7, 6, 5, 4, 3, 2, 1];

        foreach ($thresholds as $days) {
            $candidate = $end->copy()->subDays($days);
            if ($candidate->greaterThan($today)) {
                return $candidate;
            }
        }

        return $end->greaterThan($today) ? $end : $today;
    }
}
