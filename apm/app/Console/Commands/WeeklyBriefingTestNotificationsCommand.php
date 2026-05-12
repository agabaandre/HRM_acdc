<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        $appUrl = rtrim((string) config('app.url'), '/');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');

        $reminderHtml = <<<HTML
<p><strong>TEST</strong> — from <code>weekly-briefing:test-notifications</code>.</p>
<p>Production contributor reminders are <strong>HTML</strong> and include the reporting unit, <strong>submission deadline</strong>, and direct links to <strong>start</strong> or <strong>open the draft</strong> plus the <a href="{$indexUrl}">Division Weekly Brief home</a>.</p>
<p>Example subject: <em>Weekly briefing reminder — [unit name]</em> · ISO week W{$w} / {$y}</p>
<p><a href="{$appUrl}">APM home</a></p>
HTML;

        $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';
        $reminderSubject = $subjectPrefix.'[TEST] Weekly briefing reminder — W'.$w.'/'.$y;

        try {
            if (! sendEmail($email, $reminderSubject, $reminderHtml)) {
                Log::warning('weekly-briefing:test-notifications reminder sendEmail returned false', ['to' => $email]);
                $this->error('Reminder test mail failed (Exchange / mail transport returned false).');

                return self::FAILURE;
            }
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

        $compiledSubject = $subjectPrefix.'[TEST] Weekly briefing compiled — W'.$w.'/'.$y;

        try {
            if (! sendEmail($email, $compiledSubject, $compiledHtml)) {
                Log::warning('weekly-briefing:test-notifications compiled sendEmail returned false', ['to' => $email]);
                $this->error('Compiled test mail failed (Exchange / mail transport returned false).');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            Log::warning('weekly-briefing:test-notifications compiled mail failed', ['e' => $e->getMessage(), 'to' => $email]);
            $this->error('Compiled test mail failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent 2 test messages to {$email} (reminder-style + compiled-style).");

        return self::SUCCESS;
    }
}
