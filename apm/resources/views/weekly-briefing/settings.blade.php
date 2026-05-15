@extends('layouts.app')

@section('title', 'Weekly briefing settings')
@section('header', 'Weekly briefing settings')

@section('content')
<div class="container-fluid py-3 px-3 px-lg-4 weekly-briefing-settings-page" style="max-width: 1680px; margin-left: auto; margin-right: auto;">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('weekly-briefing.settings.update') }}" id="wb-settings-form">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Submission window</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Day HoDs are reminded &amp; submissions close</label>
                    <select name="submission_weekday" class="form-select">
                        @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d => $label)
                            <option value="{{ $d }}" @selected((int)$settings->submission_weekday === $d)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Default workflow: reminder and same-day close (see times below).</small>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label">HoD reminder time</label>
                        <input type="time" name="hod_reminder_time" class="form-control" value="{{ old('hod_reminder_time', substr($settings->hod_reminder_time,0,5)) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Submission closes</label>
                        <input type="time" name="submission_close_time" class="form-control" value="{{ old('submission_close_time', substr($settings->submission_close_time,0,5)) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Compiled summary send</label>
                        <input type="time" name="summary_send_time" class="form-control" value="{{ old('summary_send_time', substr($settings->summary_send_time,0,5)) }}" required>
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Scheduler times come from the fields above: <strong>HoD reminder time</strong> for contributor/HoD emails, <strong>Submission closes</strong> for the filing deadline, <strong>Compiled summary send</strong> for the organisation pack. HoD and director review reminders also use <strong>days before deadline</strong> below (exact minute match, plus a {{ \App\Services\WeeklyBriefingScheduleGate::DISPATCH_GRACE_MINUTES }}-minute grace window). Ensure <code>php artisan schedule:run</code> runs every minute (timezone: <strong>{{ config('app.timezone') }}</strong>).</p>
                @php $wb = $wbScheduleStatus ?? null; @endphp
                @if(is_array($wb))
                    <div class="alert alert-secondary small mt-3 mb-0">
                        <strong>Schedule check (now):</strong>
                        Filing week W{{ $wb['filing']['iso_week'] }}/{{ $wb['filing']['iso_year'] }} ·
                        Deadline {{ $wb['deadline']->format('l, M j, Y g:i A') }} ·
                        Reminders {{ ($wb['reminders_enabled'] ?? false) ? 'enabled' : 'disabled' }}.
                        <ul class="mb-0 mt-2">
                            <li>HoD / contributor: <strong>{{ $settings->hodReminderTimeHm() }}</strong> (HoD reminder time)
                                @if(!empty($wb['hod_is_reminder_day']))
                                    — today is a configured reminder day
                                @else
                                    — <span class="text-warning">today is not a configured reminder day</span>
                                @endif
                            </li>
                            <li>Director review: <strong>{{ $wb['director_clock_label'] ?? '—' }}</strong></li>
                            <li>Compiled summary: <strong>{{ $settings->summarySendTimeHm() }}</strong> on deadline day
                                @if(!empty($wb['compiled_is_deadline_day']))
                                    (today)
                                @else
                                    — <span class="text-warning">not today</span>
                                @endif
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Default reporting week (hub &amp; reminders)</div>
            <div class="card-body">
                <p class="small text-muted">Controls which ISO week the <strong>Weekly brief</strong> index tab, <strong>Start</strong> links, HoD reminder emails, and the compiled summary send target by default. Individual reports still store their own ISO week; the <strong>All reports</strong> tab can list any week. When <strong>Next ISO week</strong> is selected, the submission deadline for that filing week is the <strong>Friday before</strong> that reporting week begins (at <strong>Submission closes</strong> time), so briefs are filed in advance.</p>
                <div class="mb-0">
                    <label class="form-label">HoDs file for</label>
                    <select name="filing_iso_week_offset" class="form-select" style="max-width:28rem;">
                        <option value="0" @selected((int) old('filing_iso_week_offset', $settings->filing_iso_week_offset ?? 0) === 0)>Current ISO week (week containing today)</option>
                        <option value="1" @selected((int) old('filing_iso_week_offset', $settings->filing_iso_week_offset ?? 0) === 1)>Next ISO week (the week after the one containing today)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">HoD / contributor reminders (before deadline)</div>
            <div class="card-body">
                <p class="small text-muted">For the <strong>default filing ISO week</strong>, reminders go out on each listed calendar day counting back from the submission deadline, at <strong>HoD reminder time</strong> (configured above). Sends stop once the deadline has passed.</p>
                <input type="hidden" name="hod_reminder_clock" value="hod_reminder_time">
                <div class="row g-2">
                    <div class="col-md-12">
                        <label class="form-label">Days before deadline <span class="text-muted fw-normal">(comma-separated)</span></label>
                        <input type="text" name="hod_reminder_days_before_deadline" class="form-control" value="{{ old('hod_reminder_days_before_deadline', implode(', ', $settings->normalizedHodReminderDaysBeforeDeadline())) }}" required placeholder="1, 0" autocomplete="off">
                        <small class="text-muted">Example: <code>1, 0</code> = one send the day before the deadline and one on deadline day.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Director review reminders (before deadline)</div>
            <div class="card-body">
                <p class="small text-muted">Directorate directors receive a grouped email listing <strong>submitted</strong> briefs that still need their sign-off (<code>directorates.director_id</code>), on the same day-offset pattern. Reminders stop after the submission deadline.</p>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Days before deadline <span class="text-muted fw-normal">(comma-separated)</span></label>
                        <input type="text" name="director_review_reminder_days_before_deadline" class="form-control" value="{{ old('director_review_reminder_days_before_deadline', implode(', ', $settings->normalizedDirectorReviewReminderDaysBeforeDeadline())) }}" required placeholder="1, 0" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Send at this clock time</label>
                        <select name="director_review_reminder_clock" class="form-select">
                            <option value="submission_close_time" @selected(old('director_review_reminder_clock', $settings->directorReviewReminderClockColumn()) === 'submission_close_time')>Submission closes — {{ substr($settings->submission_close_time,0,5) }}</option>
                            <option value="hod_reminder_time" @selected(old('director_review_reminder_clock', $settings->directorReviewReminderClockColumn()) === 'hod_reminder_time')>HoD reminder time — {{ substr($settings->hod_reminder_time,0,5) }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Allowed heads / contributors</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="wb-add-contributor">+ Add row</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">Pick <strong>staff</strong>, their <strong>APM division</strong>, and <strong>Reporting unit type</strong>. Every row needs a <strong>contributing division</strong> — each division files its own weekly brief (<code>d-…</code>). <strong>Directorate</strong> is optional metadata for director review and combined directorate PDFs only. The hub lists one line per row.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="wb-contributors-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:2.5rem" class="text-center">#</th>
                                <th style="min-width:200px">Staff</th>
                                <th style="min-width:160px">APM division</th>
                                <th style="min-width:130px">Reporting unit type</th>
                                <th style="min-width:200px">Contributing division <span class="text-muted fw-normal">(Division: required; Directorate: optional)</span></th>
                                <th style="min-width:200px">Directorate <span class="text-muted fw-normal">(validation / director scope)</span></th>
                                <th style="min-width:200px">PDF display name <span class="text-muted fw-normal">(optional)</span></th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody id="wb-contributors-body">
                            @php
                                $contributorRows = old('contributors');
                                if (! is_array($contributorRows)) {
                                    $contributorRows = $settings->contributors;
                                }
                            @endphp
                            @foreach($contributorRows as $idx => $c)
                                @php
                                    $row = is_array($c) ? $c : null;
                                    $staffId = $row ? (int)($row['staff_id'] ?? 0) : (int)($c->staff_id ?? 0);
                                    $apmDiv = $row ? (int)($row['apm_division_id'] ?? 0) : (int)($c->apm_division_id ?? 0);
                                    $kind = $row ? ($row['contribution_kind'] ?? 'division') : (str_starts_with((string)($c->contribution_key ?? ''), 'dr-') ? 'directorate' : 'division');
                                    $contribDiv = $row ? (int)($row['contribution_division_id'] ?? 0) : (str_starts_with((string)($c->contribution_key ?? ''), 'd-') ? (int)substr((string)($c->contribution_key ?? ''), 2) : (int)($c->apm_division_id ?? 0));
                                    $contribDir = $row ? (int)($row['contribution_directorate_id'] ?? 0) : 0;
                                    if (! $row && $contribDir <= 0 && $contribDiv > 0) {
                                        $contribDir = (int) (\App\Models\Division::query()->whereKey($contribDiv)->value('directorate_id') ?? 0);
                                    }
                                    $displayName = $row ? (string)($row['display_name'] ?? '') : (string)($c->display_name ?? '');
                                @endphp
                                @include('weekly-briefing.partials.contributor-row', [
                                    'idx' => $idx,
                                    'rowNum' => $loop->iteration,
                                    'showRowNum' => true,
                                    'staffId' => $staffId,
                                    'apmDiv' => $apmDiv,
                                    'kind' => $kind,
                                    'contribDiv' => $contribDiv,
                                    'contribDir' => $contribDir,
                                    'displayName' => $displayName,
                                    'staffList' => $staffList,
                                    'divisions' => $divisions,
                                    'directorates' => $directorates,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0">Staff listed here may <strong>start, edit, and submit</strong> for their assigned reporting unit only. <strong>System administrators</strong> and <strong>report viewers</strong> (below) can open the module and see all units; only assigned contributors get filing actions.</p>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Individuals allowed to view all reports</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="wb-add-viewer">+ Add viewer</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">These staff can open Weekly brief, see every unit’s status and PDFs, and download the compiled PDF and completion summary. They cannot edit or submit another person’s report unless they are also listed as a contributor for that unit.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="wb-viewers-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:2.5rem" class="text-center">#</th>
                                <th style="min-width:220px">Staff</th>
                                <th style="width:48px"></th>
                            </tr>
                        </thead>
                        <tbody id="wb-viewers-body">
                            @php
                                $viewerRows = old('report_viewers');
                                if (! is_array($viewerRows)) {
                                    $viewerRows = [];
                                    foreach ($settings->report_viewer_staff_ids ?? [] as $vid) {
                                        $viewerRows[] = ['staff_id' => (int) $vid];
                                    }
                                }
                            @endphp
                            @foreach($viewerRows as $vidx => $vr)
                                @php
                                    $vrow = is_array($vr) ? $vr : [];
                                    $viewerStaffId = (int) ($vrow['staff_id'] ?? 0);
                                @endphp
                                @include('weekly-briefing.partials.viewer-row', [
                                    'idx' => $vidx,
                                    'rowNum' => $loop->iteration,
                                    'viewerStaffId' => $viewerStaffId,
                                    'staffList' => $staffList,
                                ])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Directorate director access</div>
            <div class="card-body">
                <input type="hidden" name="division_directors_can_access_module" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="division_directors_can_access_module" value="1" id="wbDirModule" @checked(filter_var(old('division_directors_can_access_module', $settings->division_directors_can_access_module ?? true), FILTER_VALIDATE_BOOLEAN))>
                    <label class="form-check-label" for="wbDirModule">Allow staff who are the <strong>directorate director</strong> on the <code>directorates</code> table (<code>directorates.director_id</code>) to open <strong>Weekly brief</strong> in the top menu and home dashboard, and to review or download reports for configured reporting units in their directorate. If unchecked, they only see the module if they are also a contributor or a configured report viewer.</label>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3 border-warning">
            <div class="card-header fw-bold">Late submission unlock (administrative)</div>
            <div class="card-body">
                <p class="small text-muted">When the ISO-week deadline has passed, drafts are normally locked and <strong>cannot be submitted</strong>. Use this window only when you need to let contributors (and <strong>directorate directors</strong> for submitted briefs) work again until a fixed end time. Scope <strong>All reporting units</strong> applies to every weekly brief row; <strong>One division</strong> limits the unlock to division briefs (<code>d-…</code>) for that contribution division, or directorate briefs (<code>dr-…</code>) whose APM division on the report matches the selected division.</p>
                <input type="hidden" name="report_unlock_override_enabled" value="0">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="report_unlock_override_enabled" value="1" id="wbUnlockEn" @checked(filter_var(old('report_unlock_override_enabled', $settings->report_unlock_override_enabled ?? false), FILTER_VALIDATE_BOOLEAN))>
                    <label class="form-check-label fw-semibold" for="wbUnlockEn">Enable unlock window</label>
                </div>
                @php
                    $untilOld = old('report_unlock_override_until');
                    $untilDisplay = $untilOld;
                    if ($untilDisplay === null && isset($settings->report_unlock_override_until) && $settings->report_unlock_override_until) {
                        $untilDisplay = $settings->report_unlock_override_until->timezone(config('app.timezone'))->format('Y-m-d\TH:i');
                    }
                    $scopeOld = old('report_unlock_override_scope', $settings->report_unlock_override_scope ?? 'all');
                @endphp
                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="form-label" for="wbUnlockUntil">Unlock active until (server time: {{ config('app.timezone') }})</label>
                        <input type="datetime-local" name="report_unlock_override_until" id="wbUnlockUntil" class="form-control" value="{{ $untilDisplay }}">
                    </div>
                    <div class="col-md-7">
                        <label class="form-label d-block">Applies to</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="report_unlock_override_scope" id="wbUnlockScopeAll" value="all" @checked($scopeOld === 'all')>
                            <label class="form-check-label" for="wbUnlockScopeAll">All reporting units</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="report_unlock_override_scope" id="wbUnlockScopeDiv" value="division" @checked($scopeOld === 'division')>
                            <label class="form-check-label" for="wbUnlockScopeDiv">One division only</label>
                        </div>
                    </div>
                </div>
                <div class="mb-0" id="wbUnlockDivisionWrap" style="{{ $scopeOld === 'division' ? '' : 'display:none;' }}">
                    <label class="form-label" for="wbUnlockDivisionId">Division (contribution / APM context)</label>
                    <select name="report_unlock_override_division_id" id="wbUnlockDivisionId" class="form-select" style="max-width:28rem;">
                        <option value="">— Select division —</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" @selected((string) old('report_unlock_override_division_id', $settings->report_unlock_override_division_id ?? '') === (string) $division->id)>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold">Compiled PDF &amp; mail</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Compiled report recipients (comma-separated emails)</label>
                    <textarea name="compiled_recipient_emails" class="form-control" rows="2" placeholder="a@example.com, b@example.com">{{ old('compiled_recipient_emails', $settings->compiled_recipient_emails) }}</textarea>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="cc_division_hod_on_compiled" value="1" id="ccHod" @checked(old('cc_division_hod_on_compiled', $settings->cc_division_hod_on_compiled))>
                    <label class="form-check-label" for="ccHod">Email division HoDs their division’s submitted briefing PDF; email each <strong>directorate director</strong> (from the <code>directorates</code> table) a <strong>separate director report</strong> for their directorate scope (submitted briefs under that directorate — not the organisation-wide compiled pack) plus a completion summary for that scope only. Directorates without a director are skipped for the director copy.</label>
                </div>
                <input type="hidden" name="compiled_exclude_unreviewed_director_divisions" value="0">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="compiled_exclude_unreviewed_director_divisions" value="1" id="wbCompiledExcludeUnreviewed" @checked(filter_var(old('compiled_exclude_unreviewed_director_divisions', $settings->compiled_exclude_unreviewed_director_divisions ?? false), FILTER_VALIDATE_BOOLEAN))>
                    <label class="form-check-label" for="wbCompiledExcludeUnreviewed">Exclude from the <strong>organisation-wide</strong> compiled PDF (and central compiled attachment) any <strong>submitted</strong> division brief that requires director review but is not yet marked reviewed — default is off (include all submitted division pages).</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reminders_enabled" value="1" id="remOn" @checked(old('reminders_enabled', $settings->reminders_enabled))>
                    <label class="form-check-label" for="remOn">Enable automated reminders / locks / summary dispatch</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Save settings</button>
        <a href="{{ route('weekly-briefing.index') }}" class="btn btn-outline-secondary">Back to Weekly brief</a>
    </form>

    <template id="wb-contributor-template">
        @include('weekly-briefing.partials.contributor-row', [
            'idx' => '__INDEX__',
            'rowNum' => '',
            'showRowNum' => true,
            'staffId' => 0,
            'apmDiv' => 0,
            'kind' => 'directorate',
            'contribDiv' => 0,
            'contribDir' => 0,
            'displayName' => '',
            'staffList' => $staffList,
            'divisions' => $divisions,
            'directorates' => $directorates,
        ])
    </template>

    <template id="wb-viewer-template">
        @include('weekly-briefing.partials.viewer-row', [
            'idx' => '__VINDEX__',
            'rowNum' => '',
            'viewerStaffId' => 0,
            'staffList' => $staffList,
        ])
    </template>
@endsection

@push('scripts')
<script>
(function () {
    var body = document.getElementById('wb-contributors-body');
    var tpl = document.getElementById('wb-contributor-template');
    var btn = document.getElementById('wb-add-contributor');
    var vBody = document.getElementById('wb-viewers-body');
    var vTpl = document.getElementById('wb-viewer-template');
    var vBtn = document.getElementById('wb-add-viewer');
    if (!body || !tpl || !btn) return;

    function wbRenumberContributorRows() {
        var n = 1;
        body.querySelectorAll('tr[data-wb-row]').forEach(function (tr) {
            var c = tr.querySelector('.wb-contrib-row-num');
            if (c) c.textContent = String(n++);
        });
    }

    function wbRenumberViewerRows() {
        if (!vBody) return;
        var n = 1;
        vBody.querySelectorAll('tr[data-wb-viewer-row]').forEach(function (tr) {
            var c = tr.querySelector('.wb-viewer-row-num');
            if (c) c.textContent = String(n++);
        });
    }

    function wbNextIndex() {
        var max = -1;
        body.querySelectorAll('tr[data-wb-row]').forEach(function (tr) {
            var i = parseInt(tr.getAttribute('data-wb-row'), 10);
            if (!isNaN(i) && i > max) max = i;
        });
        return max + 1;
    }

    function wbNextViewerIndex() {
        if (!vBody) return 0;
        var max = -1;
        vBody.querySelectorAll('tr[data-wb-viewer-row]').forEach(function (tr) {
            var i = parseInt(tr.getAttribute('data-wb-viewer-row'), 10);
            if (!isNaN(i) && i > max) max = i;
        });
        return max + 1;
    }

    function wbSyncContribDivOptional(tr) {
        var kindEl = tr.querySelector('.wb-kind');
        var dnEl = tr.querySelector('input.wb-display-name');
        var hint = tr.querySelector('.wb-contrib-div-hint');
        if (!kindEl || !hint) return;
        var kind = kindEl.value;
        var hasDn = dnEl && dnEl.value.trim() !== '';
        if (kind === 'directorate') {
            hint.classList.remove('d-none');
            hint.textContent = 'Required — each division has its own brief; directorate PDFs merge at export.';
        } else {
            hint.classList.add('d-none');
            hint.textContent = '';
        }
    }

    function wbWireRow(tr) {
        tr.querySelectorAll('.wb-kind').forEach(function (sel) {
            sel.addEventListener('change', function () {
                wbToggleKind(tr, sel.value);
            });
        });
        tr.querySelectorAll('.wb-display-name').forEach(function (inp) {
            inp.addEventListener('input', function () { wbSyncContribDivOptional(tr); });
            inp.addEventListener('change', function () { wbSyncContribDivOptional(tr); });
        });
        tr.querySelectorAll('.wb-remove-row').forEach(function (b) {
            b.addEventListener('click', function () {
                tr.remove();
                wbRenumberContributorRows();
            });
        });
        var k = tr.querySelector('.wb-kind');
        if (k) wbToggleKind(tr, k.value);
        wbSyncContribDivOptional(tr);
    }

    function wbWireViewerRow(tr) {
        tr.querySelectorAll('.wb-remove-viewer-row').forEach(function (b) {
            b.addEventListener('click', function () {
                tr.remove();
                wbRenumberViewerRows();
            });
        });
    }

    function wbToggleKind(tr, kind) {
        var dCol = tr.querySelector('td.wb-col-div');
        var rCol = tr.querySelector('td.wb-col-dir');
        if (!dCol || !rCol) return;
        if (kind === 'directorate') {
            dCol.style.opacity = '1';
            dCol.querySelectorAll('select').forEach(function (s) { s.disabled = false; });
            rCol.style.opacity = '1';
            rCol.querySelectorAll('select').forEach(function (s) { s.disabled = false; });
        } else {
            dCol.style.opacity = '1';
            dCol.querySelectorAll('select').forEach(function (s) { s.disabled = false; });
            rCol.style.opacity = '0.45';
            rCol.querySelectorAll('select').forEach(function (s) { s.disabled = true; });
        }
        wbSyncContribDivOptional(tr);
    }

    btn.addEventListener('click', function () {
        var ix = wbNextIndex();
        var html = tpl.innerHTML.replace(/__INDEX__/g, String(ix));
        var wrap = document.createElement('tbody');
        wrap.innerHTML = html.trim();
        var tr = wrap.querySelector('tr');
        if (tr) {
            body.appendChild(tr);
            wbWireRow(tr);
            wbInitStaffSelect2(tr);
            wbRenumberContributorRows();
        }
    });

    function wbInitStaffSelect2(tr) {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        jQuery(tr).find('.wb-staff-select').each(function () {
            var $s = jQuery(this);
            if ($s.hasClass('select2-hidden-accessible')) {
                try { $s.select2('destroy'); } catch (e) {}
            }
            $s.select2({ theme: 'bootstrap4', width: '100%' });
        });
    }

    function wbInitViewerStaffSelect2(tr) {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        jQuery(tr).find('.wb-viewer-staff-select').each(function () {
            var $s = jQuery(this);
            if ($s.hasClass('select2-hidden-accessible')) {
                try { $s.select2('destroy'); } catch (e) {}
            }
            $s.select2({ theme: 'bootstrap4', width: '100%' });
        });
    }

    body.querySelectorAll('tr[data-wb-row]').forEach(function (tr) {
        wbWireRow(tr);
    });

    if (vBody && vTpl && vBtn) {
        vBtn.addEventListener('click', function () {
            var ix = wbNextViewerIndex();
            var html = vTpl.innerHTML.replace(/__VINDEX__/g, String(ix));
            var wrap = document.createElement('tbody');
            wrap.innerHTML = html.trim();
            var tr = wrap.querySelector('tr');
            if (tr) {
                vBody.appendChild(tr);
                wbWireViewerRow(tr);
                wbInitViewerStaffSelect2(tr);
                wbRenumberViewerRows();
            }
        });
        vBody.querySelectorAll('tr[data-wb-viewer-row]').forEach(wbWireViewerRow);
    }

    var unlockScopeAll = document.getElementById('wbUnlockScopeAll');
    var unlockScopeDiv = document.getElementById('wbUnlockScopeDiv');
    var unlockDivWrap = document.getElementById('wbUnlockDivisionWrap');
    function wbSyncUnlockDivisionVis() {
        if (!unlockDivWrap) return;
        unlockDivWrap.style.display = (unlockScopeDiv && unlockScopeDiv.checked) ? '' : 'none';
    }
    if (unlockScopeAll) unlockScopeAll.addEventListener('change', wbSyncUnlockDivisionVis);
    if (unlockScopeDiv) unlockScopeDiv.addEventListener('change', wbSyncUnlockDivisionVis);
    wbSyncUnlockDivisionVis();
})();
</script>
@endpush
