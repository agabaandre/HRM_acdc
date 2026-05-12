<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WeeklyBriefingTestNotificationsCommand extends Command
{
    protected $signature = 'weekly-briefing:test-notifications {email? : Recipient address (default: andrewa@africacdc.org)}';

    protected $description = 'Send sample Division Weekly Brief emails (deadline-style reminder + compiled-style notice) to verify mail delivery.';

    public function handle(): int
    {
        $email = (string) ($this->argument('email') ?: 'andrewa@africacdc.org');
        $email = trim($email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address: '.$email);

            return self::FAILURE;
        }

        $y = Carbon::now()->isoWeekYear();
        $w = Carbon::now()->isoWeek();
        $appUrl = (string) config('app.url');

        $reminderBody = <<<TXT
This is a TEST message from APM (weekly-briefing:test-notifications).

In production, configured contributors (or legacy division HoDs) receive a reminder like:

"Please complete the Division Weekly Brief for ISO week W{$w}/{$y} (reporting unit key: …)."

— Configure staff under Weekly briefing settings → Allowed heads / contributors.
— Reminder time uses hod_reminder_time on submission_weekday (see settings).
— Scheduler: weekly-briefing:hod-reminders (every minute, self-gated on time).

App: {$appUrl}
TXT;

        try {
            Mail::raw($reminderBody, function ($message) use ($email, $w, $y) {
                $message->to($email)->subject('[TEST] Weekly briefing reminder — W'.$w.'/'.$y);
            });
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing:test-notifications reminder mail failed', ['e' => $e->getMessage(), 'to' => $email]);
            $this->error('Reminder test mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $compiledHtml = <<<HTML
<p><strong>TEST</strong> — This message was sent by <code>weekly-briefing:test-notifications</code> from the APM Jobs page or Artisan.</p>
<p>In production, the compiled weekly briefing email is sent at <strong>summary_send_time</strong> on the configured weekday and includes:</p>
<ul>
<li>Compiled PDF (all submitted units)</li>
<li>One PDF per reporting unit</li>
<li>Completion summary PDF</li>
</ul>
<p>Recipients come from <strong>Compiled report recipients</strong> in weekly briefing settings; division HoDs may be CC’d when that option is enabled.</p>
<p>Current ISO week: <strong>W{$w} / {$y}</strong></p>
<p><a href="{$appUrl}">{$appUrl}</a></p>
HTML;

        try {
            Mail::html($compiledHtml, function ($message) use ($email, $w, $y) {
                $message->to($email)->subject('[TEST] Weekly briefing compiled — W'.$w.'/'.$y);
            });
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing:test-notifications compiled mail failed', ['e' => $e->getMessage(), 'to' => $email]);
            $this->error('Compiled test mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent 2 test messages to {$email} (reminder-style + compiled-style).");

        return self::SUCCESS;
    }
}
