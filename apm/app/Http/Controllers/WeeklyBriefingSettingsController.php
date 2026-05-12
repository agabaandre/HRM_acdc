<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Division;
use App\Models\Staff;
use App\Models\WeeklyBriefingContributor;
use App\Models\WeeklyBriefingSetting;
use App\Services\DivisionWeeklyBriefGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            ->get(['staff_id', 'fname', 'lname', 'job_name']);

        $divisions = Division::query()->orderBy('division_name')->get(['id', 'division_name', 'division_short_name']);

        $directorates = Directorate::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('weekly-briefing.settings', compact('settings', 'staffList', 'divisions', 'directorates'));
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(DivisionWeeklyBriefGate::isSystemAdmin(), 403);

        $data = $request->validate([
            'submission_weekday' => 'required|integer|min:0|max:6',
            'hod_reminder_time' => 'required|string|max:8',
            'submission_close_time' => 'required|string|max:8',
            'summary_send_time' => 'required|string|max:8',
            'compiled_recipient_emails' => 'nullable|string|max:5000',
            'cc_division_hod_on_compiled' => 'sometimes|boolean',
            'reminders_enabled' => 'sometimes|boolean',
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
            $apmDivisionId = (int) ($row['apm_division_id'] ?? 0);
            if ($apmDivisionId <= 0) {
                return back()->withInput()->with('error', 'Each contributor row needs an APM division.');
            }
            $kind = (string) ($row['contribution_kind'] ?? 'division');
            if ($kind === 'directorate') {
                $dirId = (int) ($row['contribution_directorate_id'] ?? 0);
                if ($dirId <= 0) {
                    return back()->withInput()->with('error', 'Directorate contribution rows need a directorate.');
                }
                if (! Directorate::query()->whereKey($dirId)->exists()) {
                    return back()->withInput()->with('error', 'Invalid directorate selected.');
                }
                $key = WeeklyBriefingContributor::contributionKeyForDirectorate($dirId);
            } else {
                $divId = (int) ($row['contribution_division_id'] ?? 0);
                if ($divId <= 0) {
                    return back()->withInput()->with('error', 'Division contribution rows need a contribution division.');
                }
                if (! Division::query()->whereKey($divId)->exists()) {
                    return back()->withInput()->with('error', 'Invalid division selected.');
                }
                $key = WeeklyBriefingContributor::contributionKeyForDivision($divId);
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
                'display_name' => (($dn = trim((string) ($row['display_name'] ?? ''))) !== '' ? Str::limit($dn, 255, '') : null),
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

        $settings->fill([
            'submission_weekday' => $data['submission_weekday'],
            'hod_reminder_time' => $this->normalizeTime($data['hod_reminder_time']),
            'submission_close_time' => $this->normalizeTime($data['submission_close_time']),
            'summary_send_time' => $this->normalizeTime($data['summary_send_time']),
            'compiled_recipient_emails' => $data['compiled_recipient_emails'] ?? null,
            'cc_division_hod_on_compiled' => $request->boolean('cc_division_hod_on_compiled'),
            'reminders_enabled' => $request->boolean('reminders_enabled'),
            'report_viewer_staff_ids' => $viewerIds,
        ]);
        $settings->save();

        $settings->contributors()->delete();
        foreach ($normalized as $n) {
            $settings->contributors()->create($n);
        }

        return redirect()->route('weekly-briefing.settings.edit')->with('status', 'Weekly briefing settings saved.');
    }

    private function normalizeTime(string $t): string
    {
        $t = trim($t);
        if (strlen($t) === 5) {
            return $t.':00';
        }

        return substr($t, 0, 8);
    }
}
