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
use Illuminate\Support\Facades\Mail;

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
                        Mail::raw(
                            "Please complete the Division Weekly Brief for ISO week W{$w}/{$y} (reporting unit key: {$contributionKey}).",
                            function ($message) use ($email, $contributionKey) {
                                $message->to($email)->subject('Weekly briefing reminder — '.$contributionKey);
                            }
                        );
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
                Mail::raw(
                    "Please remind your team to complete the Division Weekly Brief for ISO week W{$w}/{$y} (division: {$division->division_name}).",
                    function ($message) use ($email, $division) {
                        $message->to($email)->subject('Weekly briefing reminder — '.$division->division_name);
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('weekly-briefing:hod-reminders mail failed', ['e' => $e->getMessage(), 'to' => $email]);
            }
        }

        $this->info('weekly-briefing:hod-reminders completed (legacy division HoDs).');

        return self::SUCCESS;
    }
}
