<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Support\WeeklyBriefingMailTemplate;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WeeklyBriefingHodRemindersCommand extends Command
{
    protected $signature = 'weekly-briefing:hod-reminders {--force : Run immediately, ignoring schedule gates}';

    protected $description = 'Remind contributors (or legacy division HoDs) about missing weekly briefs for the configured filing week, on each configured day-before-deadline offset at the chosen clock time.';

    public function handle(): int
    {
        $settings = WeeklyBriefingSetting::current();
        $force = (bool) $this->option('force');

        if (! $force && ! $settings->reminders_enabled) {
            return self::SUCCESS;
        }

        $filing = $settings->filingIsoWeekPair();
        $y = $filing['iso_year'];
        $w = $filing['iso_week'];
        $deadline = WeeklyBriefingReport::syntheticDeadlineForIsoWeek($settings, $y, $w);
        $subjectPrefix = env('MAIL_SUBJECT_PREFIX', 'APM').': ';

        if (! $force) {
            if (Carbon::now()->greaterThan($deadline)) {
                return self::SUCCESS;
            }
            $offsets = $settings->normalizedHodReminderDaysBeforeDeadline();
            $clockCol = $settings->hodReminderClockColumn();
            if (! $settings->matchesTimeNow($clockCol)) {
                return self::SUCCESS;
            }
            $today = Carbon::now()->startOfDay();
            $deadlineDay = $deadline->copy()->startOfDay();
            $matched = false;
            foreach ($offsets as $offset) {
                if ($today->equalTo($deadlineDay->copy()->subDays($offset))) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return self::SUCCESS;
            }
        }

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
                        $html = $this->contributorReminderHtml($settings, (string) $contributionKey, $y, $w, $report, $c->staff);
                        $label = WeeklyBriefingContributor::presentationLabelForContributionKey((string) $contributionKey);
                        $subject = $subjectPrefix.'Weekly brief reminder — '.$label.WeeklyBriefingMailTemplate::subjectSuffix();
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
                $html = $this->legacyHodReminderHtml($settings, $division->division_name ?? 'Division', $y, $w, $report, $hod);
                $subject = $subjectPrefix.'Weekly brief reminder — '.($division->division_name ?? 'Division').WeeklyBriefingMailTemplate::subjectSuffix();
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

    private function contributorReminderHtml(WeeklyBriefingSetting $settings, string $contributionKey, int $y, int $w, ?WeeklyBriefingReport $report, ?Staff $recipient): string
    {
        $label = htmlspecialchars(WeeklyBriefingContributor::presentationLabelForContributionKey($contributionKey), ENT_QUOTES, 'UTF-8');
        $deadline = htmlspecialchars($this->deadlineLine($settings, $y, $w, $report), ENT_QUOTES, 'UTF-8');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');
        if ($report) {
            $actionUrl = htmlspecialchars(route('weekly-briefing.edit', ['report' => $report->id], true), ENT_QUOTES, 'UTF-8');
            $actionText = 'Open draft to complete and submit';
        } else {
            $actionUrl = htmlspecialchars(route('weekly-briefing.create', [
                'contribution_key' => $contributionKey,
                'iso_year' => $y,
                'iso_week' => $w,
            ], true), ENT_QUOTES, 'UTF-8');
            $actionText = 'Start this reporting week’s Weekly brief';
        }

        $weekHuman = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8');

        $inner = <<<HTML
<p>You are kindly reminded to complete the <strong>Weekly brief</strong> for reporting unit <strong>{$label}</strong>.</p>
<p><strong>Reporting week:</strong> {$weekHuman}</p>
<p><strong>Submission deadline:</strong> {$deadline}</p>
<p style="text-align:center;"><a class="btn" href="{$actionUrl}">{$actionText}</a></p>
<p>You may also open the <strong>Weekly brief</strong> module from the <a href="{$indexUrl}">APM home navigation</a>.</p>
<p style="font-size:12px;color:#64748b;">This message was sent because you are listed as a contributor for this reporting unit. If you have already submitted your report, please disregard this reminder.</p>
HTML;

        return WeeklyBriefingMailTemplate::wrap($recipient, 'Weekly brief reminder', $inner);
    }

    private function legacyHodReminderHtml(WeeklyBriefingSetting $settings, string $divisionName, int $y, int $w, ?WeeklyBriefingReport $report, Staff $hod): string
    {
        $deadline = htmlspecialchars($this->deadlineLine($settings, $y, $w, $report), ENT_QUOTES, 'UTF-8');
        $dn = htmlspecialchars($divisionName, ENT_QUOTES, 'UTF-8');
        $indexUrl = htmlspecialchars(route('weekly-briefing.index', [], true), ENT_QUOTES, 'UTF-8');

        $weekHuman = htmlspecialchars(WeeklyBriefingReport::humanIsoWeekRange($y, $w), ENT_QUOTES, 'UTF-8');

        $inner = <<<HTML
<p>You are kindly requested to remind the responsible staff to complete the <strong>Weekly brief</strong> for <strong>{$dn}</strong>.</p>
<p><strong>Reporting week:</strong> {$weekHuman}</p>
<p><strong>Submission deadline:</strong> {$deadline}</p>
<p>Contributors may file their returns through the <a href="{$indexUrl}">Weekly brief</a> section in the Approvals Management System (APM).</p>
HTML;

        return WeeklyBriefingMailTemplate::wrap($hod, 'Weekly brief reminder', $inner);
    }
}
