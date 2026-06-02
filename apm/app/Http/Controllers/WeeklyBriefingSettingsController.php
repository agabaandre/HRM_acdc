<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingReport;
use App\Models\WeeklyBriefingSetting;
use App\Services\DirectorateDivisionLink;
use App\Services\DivisionWeeklyBriefGate;
use App\Services\WeeklyBriefingContributionKeyResolver;
use App\Services\WeeklyBriefingScheduleGate;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyBriefingSettingsController extends Controller
{
    public function edit(): View
    {
        abort_unless(DivisionWeeklyBriefGate::isSystemAdmin(), 403);

        $settings = WeeklyBriefingSetting::current()->load(['contributors.staff', 'contributors.apmDivision']);

        $staffList = Staff::query()->active()
            ->orderBy('lname')
            ->orderBy('fname')
            ->get(['staff_id', 'title', 'fname', 'lname', 'oname', 'job_name']);

        $divisions = Division::query()->orderBy('division_name')->get(['id', 'division_name', 'division_short_name']);

        $directorates = Directorate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $wbScheduleStatus = WeeklyBriefingScheduleGate::for($settings)->scheduleStatus();

        $divisionDirectorateMap = DirectorateDivisionLink::buildDivisionDirectorateMap();

        return view('weekly-briefing.settings', compact(
            'settings',
            'staffList',
            'divisions',
            'directorates',
            'wbScheduleStatus',
            'divisionDirectorateMap',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(DivisionWeeklyBriefGate::isSystemAdmin(), 403);

        // Backward-compatible fallback: allow week remap through the existing settings update route
        // on deployments where the dedicated remap route is not yet registered.
        if ($request->boolean('__remap_weeks')) {
            return $this->performWeekRemap($request);
        }

        $data = $request->validate([
            'submission_weekday' => 'required|integer|min:0|max:6',
            'filing_iso_week_offset' => 'required|integer|in:0,1',
            'hod_reminder_time' => 'required|string|max:8',
            'hod_reminder_days_before_deadline' => ['required', 'string', 'max:80', 'regex:/^\d+(\s*,\s*\d+)*$/'],
            'hod_reminder_clock' => ['nullable', 'string', 'in:submission_close_time,hod_reminder_time'],
            'director_review_reminder_days_before_deadline' => ['required', 'string', 'max:80', 'regex:/^\d+(\s*,\s*\d+)*$/'],
            'director_review_reminder_clock' => ['nullable', 'string', 'in:submission_close_time,hod_reminder_time'],
            'submission_close_time' => 'required|string|max:8',
            'summary_send_time' => 'required|string|max:8',
            'compiled_recipient_emails' => 'nullable|string|max:5000',
            'cc_division_hod_on_compiled' => 'sometimes|boolean',
            'compiled_exclude_unreviewed_director_divisions' => 'sometimes|boolean',
            'reminders_enabled' => 'sometimes|boolean',
            'division_directors_can_access_module' => 'sometimes|boolean',
            'report_unlock_override_enabled' => 'sometimes|boolean',
            'report_unlock_override_until' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $request->boolean('report_unlock_override_enabled')),
            ],
            'report_unlock_override_scope' => 'nullable|in:all,division',
            'report_unlock_override_division_id' => [
                'nullable',
                'integer',
                Rule::exists((new Division)->getTable(), 'id'),
                Rule::requiredIf(fn () => $request->boolean('report_unlock_override_enabled')
                    && $request->input('report_unlock_override_scope') === 'division'),
            ],
            'contributors' => 'nullable|array',
            'contributors.*.staff_id' => 'nullable|integer',
            'contributors.*.apm_division_id' => 'nullable|integer',
            'contributors.*.contribution_kind' => 'nullable|in:division,directorate',
            'contributors.*.contribution_division_id' => 'nullable|integer',
            'contributors.*.contribution_directorate_id' => 'nullable|integer',
            'contributors.*.display_name' => 'nullable|string|max:255',
            'report_viewers' => 'nullable|array',
            'report_viewers.*.staff_id' => 'nullable|integer',
        ]);

        $settings = WeeklyBriefingSetting::current();

        $normalized = [];
        foreach ($request->input('contributors', []) as $row) {
            $staffId = (int) ($row['staff_id'] ?? 0);
            if ($staffId <= 0) {
                continue;
            }
            $divisionId = (int) ($row['apm_division_id'] ?? 0);
            if ($divisionId <= 0) {
                $divisionId = WeeklyBriefingContributionKeyResolver::contributingDivisionIdFromRow($row);
            }
            if ($divisionId <= 0) {
                return back()->withInput()->with('error', 'Each contributor row needs a division.');
            }
            if (! Division::query()->whereKey($divisionId)->exists()) {
                return back()->withInput()->with('error', 'Invalid division selected.');
            }
            $apmDivisionId = $divisionId;
            $row['apm_division_id'] = $divisionId;
            $row['contribution_division_id'] = $divisionId;

            $rowDisplay = trim((string) ($row['display_name'] ?? ''));
            $hasPdfLabel = $rowDisplay !== '';
            $kind = (string) ($row['contribution_kind'] ?? 'division');
            $div = Division::query()->find($divisionId);

            if ($kind === 'directorate') {
                $dirId = DirectorateDivisionLink::directorateIdForSettingsRow($divisionId, $row);
                if ($dirId > 0) {
                    if (! Directorate::query()->whereKey($dirId)->exists()) {
                        return back()->withInput()->with('error', 'Invalid directorate for division "' . ($div->division_name ?? $divisionId) . '".');
                    }
                    if ($div && ! DirectorateDivisionLink::divisionBelongsToDirectorate($div, $dirId)) {
                        return back()->withInput()->with('error', 'The division "' . ($div->division_name ?? '') . '" does not match the directorate. Re-select the division so the directorate can auto-fill.');
                    }
                }
            }
            try {
                $key = WeeklyBriefingContributionKeyResolver::keyFromSettingsRow($row);
            } catch (\InvalidArgumentException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
            if (! Division::query()->whereKey($apmDivisionId)->exists()) {
                return back()->withInput()->with('error', 'Invalid APM division.');
            }
            if (! Staff::query()->whereKey($staffId)->exists()) {
                return back()->withInput()->with('error', 'Invalid staff selected.');
            }
            $normalized[] = [
                'staff_id' => $staffId,
                'apm_division_id' => $apmDivisionId,
                'contribution_key' => $key,
                'display_name' => ($hasPdfLabel ? Str::limit($rowDisplay, 255, '') : null),
            ];
        }

        $viewerIds = [];
        foreach ($request->input('report_viewers', []) as $row) {
            $sid = (int) ($row['staff_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            if (! Staff::query()->whereKey($sid)->exists()) {
                return back()->withInput()->with('error', 'Invalid staff selected in report viewers.');
            }
            $viewerIds[$sid] = $sid;
        }
        $viewerIds = array_values($viewerIds);

        $overrideEnabled = $request->boolean('report_unlock_override_enabled');
        $overrideScope = $overrideEnabled ? (string) ($data['report_unlock_override_scope'] ?? 'all') : 'all';
        if ($overrideScope !== 'division') {
            $overrideScope = 'all';
        }
        $overrideUntil = null;
        if ($overrideEnabled && ! empty($data['report_unlock_override_until'])) {
            $overrideUntil = Carbon::parse($data['report_unlock_override_until'])->format('Y-m-d H:i:s');
        }
        $overrideDivisionId = null;
        if ($overrideEnabled && $overrideScope === 'division') {
            $overrideDivisionId = (int) ($data['report_unlock_override_division_id'] ?? 0);
            if ($overrideDivisionId <= 0) {
                $overrideDivisionId = null;
            }
        }

        $hodReminderDays = WeeklyBriefingSetting::ensureReminderOffsets(
            $this->parseReminderDaysBeforeDeadline($data['hod_reminder_days_before_deadline'], 'hod_reminder_days_before_deadline'),
            WeeklyBriefingSetting::HOD_REMINDER_DAYS_BEFORE_DEADLINE
        );
        $directorReminderDays = array_values(array_filter(
            $this->parseReminderDaysBeforeDeadline($data['director_review_reminder_days_before_deadline'], 'director_review_reminder_days_before_deadline'),
            static fn (int $offset): bool => $offset > 0
        ));

        $hodReminderClock = $data['hod_reminder_clock'] ?? 'hod_reminder_time';
        $directorReminderClock = $data['director_review_reminder_clock'] ?? 'hod_reminder_time';

        $fill = [
            'submission_weekday' => $data['submission_weekday'],
            'filing_iso_week_offset' => (int) ($data['filing_iso_week_offset'] ?? 0),
            'hod_reminder_time' => $this->normalizeTime($data['hod_reminder_time']),
            'hod_reminder_days_before_deadline' => $hodReminderDays,
            'director_review_reminder_days_before_deadline' => $directorReminderDays,
            'submission_close_time' => $this->normalizeTime($data['submission_close_time']),
            'summary_send_time' => $this->normalizeTime($data['summary_send_time']),
            'compiled_recipient_emails' => $data['compiled_recipient_emails'] ?? null,
            'cc_division_hod_on_compiled' => $request->boolean('cc_division_hod_on_compiled'),
            'compiled_exclude_unreviewed_director_divisions' => $request->boolean('compiled_exclude_unreviewed_director_divisions'),
            'reminders_enabled' => $request->boolean('reminders_enabled'),
            'division_directors_can_access_module' => $request->boolean('division_directors_can_access_module'),
            'report_unlock_override_enabled' => $overrideEnabled,
            'report_unlock_override_until' => $overrideUntil,
            'report_unlock_override_scope' => $overrideScope,
            'report_unlock_override_division_id' => $overrideDivisionId,
            'report_viewer_staff_ids' => $viewerIds,
        ];

        if (Schema::hasColumn($settings->getTable(), 'hod_reminder_clock')) {
            $fill['hod_reminder_clock'] = $hodReminderClock;
        }
        if (Schema::hasColumn($settings->getTable(), 'director_review_reminder_clock')) {
            $fill['director_review_reminder_clock'] = $directorReminderClock;
        }
        if (! Schema::hasColumn($settings->getTable(), 'compiled_exclude_unreviewed_director_divisions')) {
            unset($fill['compiled_exclude_unreviewed_director_divisions']);
        }
        if (! Schema::hasColumn($settings->getTable(), 'hod_reminder_days_before_deadline')) {
            unset($fill['hod_reminder_days_before_deadline'], $fill['director_review_reminder_days_before_deadline']);
        }

        $settings->fill($fill);
        $settings->save();

        WeeklyBriefingContributionKeyResolver::migrateLegacyDirectorateReportsForNormalizedRows($normalized);

        $settings->contributors()->delete();
        foreach ($normalized as $n) {
            $settings->contributors()->create($n);
        }

        return redirect()->route('weekly-briefing.settings.edit')->with('status', 'Weekly briefing settings saved.');
    }

    public function remapWeeks(Request $request): RedirectResponse
    {
        abort_unless(DivisionWeeklyBriefGate::isSystemAdmin(), 403);

        return $this->performWeekRemap($request);
    }

    private function performWeekRemap(Request $request): RedirectResponse
    {
        abort_unless(DivisionWeeklyBriefGate::isSystemAdmin(), 403);

        $data = $request->validate([
            'week_shift' => 'required|integer|min:-104|max:104|not_in:0',
        ]);

        $shift = (int) $data['week_shift'];

        $reports = WeeklyBriefingReport::query()
            ->select(['id', 'report_iso_week_year', 'report_iso_week'])
            ->get();

        if ($reports->isEmpty()) {
            return redirect()->route('weekly-briefing.settings.edit')
                ->with('status', 'No weekly briefing reports found to remap.');
        }

        $targetById = [];
        foreach ($reports as $report) {
            $targetMonday = WeeklyBriefingReport::periodMonday(
                (int) $report->report_iso_week_year,
                (int) $report->report_iso_week
            )->addWeeks($shift);

            $targetById[(int) $report->id] = [
                'iso_year' => (int) $targetMonday->isoWeekYear(),
                'iso_week' => (int) $targetMonday->isoWeek(),
                'period_start' => $targetMonday->toDateString(),
            ];
        }

        DB::transaction(function () use ($reports, $targetById): void {
            // Phase 1: move out of the unique index range to avoid interim collisions.
            foreach ($reports as $report) {
                WeeklyBriefingReport::query()
                    ->whereKey((int) $report->id)
                    ->update([
                        'report_iso_week_year' => (int) $report->report_iso_week_year + 1000,
                    ]);
            }

            // Phase 2: write final week/year + aligned period_start.
            foreach ($targetById as $id => $target) {
                WeeklyBriefingReport::query()
                    ->whereKey($id)
                    ->update([
                        'report_iso_week_year' => $target['iso_year'],
                        'report_iso_week' => $target['iso_week'],
                        'period_start' => $target['period_start'],
                    ]);
            }
        });

        $direction = $shift > 0 ? '+' : '';

        return redirect()->route('weekly-briefing.settings.edit')->with(
            'status',
            'Remapped '.$reports->count().' weekly briefing report(s) by '.$direction.$shift.' week(s).'
        );
    }

    private function normalizeTime(string $t): string
    {
        $t = trim($t);
        if (strlen($t) === 5) {
            return $t.':00';
        }

        return substr($t, 0, 8);
    }

    /**
     * @return list<int>
     */
    private function parseReminderDaysBeforeDeadline(string $raw, string $errorKey): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', trim($raw)) as $part) {
            if ($part === '') {
                continue;
            }
            $i = (int) $part;
            if ($i < 0 || $i > 30) {
                throw ValidationException::withMessages([
                    $errorKey => ['Each value must be between 0 and 30 (days before the submission deadline).'],
                ]);
            }
            $out[$i] = $i;
        }
        if ($out === []) {
            throw ValidationException::withMessages([
                $errorKey => ['Enter at least one day offset (e.g. 1, 0).'],
            ]);
        }

        return array_values($out);
    }
}
