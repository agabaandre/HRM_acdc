<?php

namespace App\Services\ActivityReport;

use App\Jobs\SendNotificationEmailJob;
use App\Models\Activity;
use App\Models\ActivityReport;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Support\ActivityReportingModule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ActivityReportReminderService
{
    public function __construct(
        private ActivityReportRegistryService $registry,
        private ActivityReportAccessService $access,
    ) {}

    /**
     * Resolve the activity end date used for the +7 day reminder rule.
     */
    public function endDateForSource(Model $source): ?Carbon
    {
        $raw = $source->date_to
            ?? $source->end_date
            ?? $source->memo_date
            ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function reminderDueOn(Carbon $endDate): Carbon
    {
        return $endDate->copy()->addDays(7)->startOfDay();
    }

    public function isEligible(Model $source, ?ActivityReport $report, Carbon $today): bool
    {
        if ($report && ((bool) $report->not_executed || (bool) $report->has_been_submitted)) {
            return false;
        }

        $end = $this->endDateForSource($source);
        if ($end === null) {
            return false;
        }

        $bounds = $this->registry->currentQuarterBounds($today);
        if ($end->lt($bounds['start']) || $end->gt($bounds['end'])) {
            // Still allow if activity overlaps quarter via start, but due date is based on end.
            // Spec: current quarter only — require end date in current quarter.
            return false;
        }

        return $this->reminderDueOn($end)->lte($today->copy()->startOfDay());
    }

    /**
     * @return Collection<int, array{source: Model, report: ?ActivityReport, staff_ids: list<int>}>
     */
    public function eligibleSources(?Carbon $today = null): Collection
    {
        $today = ($today ?? Carbon::now())->copy()->startOfDay();
        $rows = collect($this->registry->listCurrentQuarter());
        $out = collect();

        foreach ($rows as $row) {
            $source = $this->loadSource((string) $row['source_type'], (int) $row['source_id']);
            if (! $source) {
                continue;
            }

            $report = ActivityReport::query()
                ->where('source_id', $source->getKey())
                ->where('source_type', $source::class)
                ->first();

            if (! $this->isEligible($source, $report, $today)) {
                continue;
            }

            $out->push([
                'source' => $source,
                'report' => $report,
                'staff_ids' => $this->access->participantStaffIds($source),
                'title' => (string) ($row['title'] ?? ''),
                'document_number' => $row['document_number'] ?? null,
                'is_external_funded' => (bool) ($row['is_external_funded'] ?? false),
            ]);
        }

        return $out;
    }

    /**
     * Dispatch reminder emails to participants. Returns number of notifications queued.
     */
    public function notifyEligible(bool $dryRun = false): int
    {
        if (! ActivityReportingModule::notificationsEnabled()) {
            Log::info('Activity report reminders skipped — module/notifications disabled in Module control settings.');

            return 0;
        }

        $eligible = $this->eligibleSources();
        $queued = 0;

        foreach ($eligible as $item) {
            $title = $item['title'] !== '' ? $item['title'] : 'Activity';
            $ref = $item['document_number'] ? " ({$item['document_number']})" : '';
            $external = ! empty($item['is_external_funded']) ? ' [external funded]' : '';
            $message = "Activity report overdue{$external}: please submit the report for {$title}{$ref}. Due 7 days after the activity end date.";

            foreach ($item['staff_ids'] as $staffId) {
                $staff = Staff::query()->where('staff_id', $staffId)->first();
                if (! $staff) {
                    continue;
                }
                if ($dryRun) {
                    $queued++;
                    continue;
                }
                try {
                    SendNotificationEmailJob::dispatch(
                        $item['report'] ?? $item['source'],
                        $staff,
                        'activity_report_reminder',
                        $message,
                    );
                    $queued++;
                } catch (\Throwable $e) {
                    Log::warning('Activity report reminder dispatch failed', [
                        'staff_id' => $staffId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $queued;
    }

    private function loadSource(string $type, int $id): ?Model
    {
        return match ($type) {
            Activity::class => Activity::query()->find($id),
            SpecialMemo::class => SpecialMemo::query()->find($id),
            NonTravelMemo::class => NonTravelMemo::query()->find($id),
            default => null,
        };
    }
}
