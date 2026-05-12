<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklyBriefingHodRemindersCommand extends Command
{
    protected $signature = 'weekly-briefing:hod-reminders {--force : Run immediately, ignoring reminders_enabled, weekday, and hod_reminder_time}';

    protected $description = 'Remind configured contributors (or legacy division HoDs) when a weekly briefing is missing for the current ISO week.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $force = (bool) $this->option('force');
        if (! $force) {
            if (! $settings->reminders_enabled) {
                return self::SUCCESS;
            }
            if ((int) Carbon::now()->dayOfWeek !== (int) $settings->submission_weekday) {
                return self::SUCCESS;
            }
            if (! $settings->matchesTimeNow('hod_reminder_time')) {
                return self::SUCCESS;
            }
        }

        $y = Carbon::now()->isoWeekYear();
        $w = Carbon::now()->isoWeek();

        $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';

        if ($settings->contributors()->exists()) {
            $keys = $settings->contributors()->distinct()->pluck('contribution_key')->filter()->values();
            foreach ($keys as $contributionKey) {
                $report = WeeklyBriefingReport::query()
                    ->where('contribution_key', $contributionKey)
                    ->where('report_iso_week_year', $y)
                    ->where('report_iso_week', $w)
                    ->first();
                if ($report && $report->status === WeeklyBriefingReport::STATUS_SUBMITTED) {
                    continue;
                }
                $contributors = $settings->contributors()->where('contribution_key', $contributionKey)->with('staff')->get();
                foreach ($contributors as $c) {
                    $email = $c->staff?->work_email;
                    if (! $email) {
                        Log::info('weekly-briefing:hod-reminders — no contributor email', ['staff_id' => $c->staff_id, 'key' => $contributionKey]);

                        continue;
                    }
                    try {
                        $html = $this->contributorReminderHtml($settings, (string) $contributionKey, $y, $w, $report);
                        $label = WeeklyBriefingContributor::presentationLabelForContributionKey((string) $contributionKey);
                        $subject = $subjectPrefix.'Weekly briefing reminder — '.$label;
                        if (! sendEmail($email, $subject, $html)) {
                            Log::warning('weekly-briefing:hod-reminders sendEmail returned false', ['to' => $email, 'key' => $contributionKey]);
                        }
                    } catch (\Throwable $e) {
                        Log::warning('weekly-briefing:hod-reminders mail failed', ['e' => $e->getMessage(), 'to' => $email]);
                    }
                }
            }

            $this->info('weekly-briefing:hod-reminders completed (contributor-based).');

            return self::SUCCESS;
        }

        foreach (Division::query()->cursor() as $division) {
            $key = WeeklyBriefingContributor::contributionKeyForDivision((int) $division->id);
            $report = WeeklyBriefingReport::query()
                ->where('contribution_key', $key)
                ->where('report_iso_week_year', $y)
                ->where('report_iso_week', $w)
                ->first();

            if ($report && $report->status === WeeklyBriefingReport::STATUS_SUBMITTED) {
                continue;
            }

            $hodId = $division->division_head ?? null;
            if (! $hodId) {
                continue;
            }
            $hod = Staff::query()->where('staff_id', $hodId)->first();
            $email = $hod?->work_email;
            if (! $email) {
                Log::info('weekly-briefing:hod-reminders — no HoD email', ['division_id' => $division->id]);

                continue;
            }

            try {
                $html = $this->legacyHodReminderHtml($settings, $division->division_name ?? 'Division', $y, $w, $report);
                $subject = $subjectPrefix.'Weekly briefing reminder — '.($division->division_name ?? 'Division');
                if (! sendEmail($email, $subject, $html)) {
                    Log::warning('weekly-briefing:hod-reminders sendEmail returned false', ['to' => $email, 'division_id' => $division->id]);
                }
            } catch (\Throwable $e) {
                Log::warning('weekly-briefing:hod-reminders mail failed', ['e' => $e->getMessage(), 'to' => $email]);
            }
        }

        $this->info('weekly-briefing:hod-reminders completed (legacy division HoDs).');

        return self::SUCCESS;
    }

    private function deadlineLine(WeeklyBriefingSetting $settings, int $y, int $w, ?WeeklyBriefingReport $report): string
    {
        $r = $report;
        if (! $r) {
            $r = new WeeklyBriefingReport;
            $r->period_start = WeeklyBriefingReport::periodMonday($y, $w);
        }

        return $r->submissionDeadline($settings)->format('l, F j, Y \a\t g:i A');
    }

    private function contributorReminderHtml(WeeklyBriefingSetting $settings, string $contributionKey, int $y, int $w, ?WeeklyBriefingReport $report): string
    {
        $label = htmlspecialchars(WeeklyBriefingContributor::presentationLabelForContributionKey($contributionKey), ENT_QUOTES, 'UTF-8');
        $deadline = htmlspecialchars($this->deadlineLine($settings, $y, $w, $report), ENT_QUOTES, 'UTF-8');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');
        if ($report) {
            $actionUrl = htmlspecialchars(route('weekly-briefing.edit', ['report' => $report->id], true), ENT_QUOTES, 'UTF-8');
            $actionText = 'Open your draft to complete and submit';
        } else {
            $actionUrl = htmlspecialchars(route('weekly-briefing.create', ['contribution_key' => $contributionKey], true), ENT_QUOTES, 'UTF-8');
            $actionText = 'Start this week’s Division Weekly Brief';
        }

        return <<<HTML
<p>Please complete the <strong>Division Weekly Brief</strong> for reporting unit <strong>{$label}</strong> (ISO week <strong>W{$w} / {$y}</strong>).</p>
<p><strong>Submission deadline:</strong> {$deadline}</p>
<p><a href="{$actionUrl}">{$actionText}</a> — or open the <a href="{$indexUrl}">Division Weekly Brief home</a> in APM.</p>
<p style="font-size:12px;color:#64748b;">This message was sent because you are listed as a contributor for this reporting unit. If you have already submitted, you can ignore this reminder.</p>
HTML;
    }

    private function legacyHodReminderHtml(WeeklyBriefingSetting $settings, string $divisionName, int $y, int $w, ?WeeklyBriefingReport $report): string
    {
        $deadline = htmlspecialchars($this->deadlineLine($settings, $y, $w, $report), ENT_QUOTES, 'UTF-8');
        $dn = htmlspecialchars($divisionName, ENT_QUOTES, 'UTF-8');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<p>Please remind your team to complete the <strong>Division Weekly Brief</strong> for <strong>{$dn}</strong> (ISO week <strong>W{$w} / {$y}</strong>).</p>
<p><strong>Submission deadline:</strong> {$deadline}</p>
<p>Contributors can file from the <a href="{$indexUrl}">Division Weekly Brief</a> section in APM.</p>
HTML;
    }
}
