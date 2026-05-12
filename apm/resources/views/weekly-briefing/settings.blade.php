@extends('layouts.app')

@section('title', 'Weekly briefing settings')
@section('header', 'Weekly briefing settings')

@section('content')
<div class="container py-3" style="max-width: 960px;">
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
                <p class="small text-muted mt-2 mb-0">The server scheduler runs the weekly briefing commands every minute; each command only acts on the weekday above when the current server time matches the configured time (minute precision). Ensure <code>php artisan schedule:run</code> is invoked every minute (standard Laravel cron).</p>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>Allowed heads / contributors</span>
                <button type="button" class="btn btn-sm btn-outline-success" id="wb-add-contributor">+ Add row</button>
            </div>
            <div class="card-body">
                <p class="small text-muted">Pick <strong>staff</strong> who may submit, their <strong>APM division</strong> (context), and the <strong>contribution division or directorate</strong> the weekly report is for. Use <strong>PDF display name</strong> when the name on reports should differ from the system division/directorate title. Expected reporting units for reminders and the completion summary come from the distinct contribution targets in this list.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="wb-contributors-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:2.5rem" class="text-center">#</th>
                                <th style="min-width:200px">Staff</th>
                                <th style="min-width:160px">APM division</th>
                                <th style="min-width:130px">Contribution type</th>
                                <th style="min-width:180px">Contribution division</th>
                                <th style="min-width:180px">Contribution directorate</th>
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
                                    $contribDiv = $row ? (int)($row['contribution_division_id'] ?? 0) : ($kind === 'division' ? (int)substr((string)($c->contribution_key ?? ''), 2) : 0);
                                    $contribDir = $row ? (int)($row['contribution_directorate_id'] ?? 0) : ($kind === 'directorate' ? (int)substr((string)($c->contribution_key ?? ''), 3) : 0);
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
                <p class="small text-muted">These staff can open Division Weekly Brief, see every unit’s status and PDFs, and download the compiled PDF and completion summary. They cannot edit or submit another person’s report unless they are also listed as a contributor for that unit.</p>
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
            <div class="card-header fw-bold">Compiled PDF &amp; mail</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Compiled report recipients (comma-separated emails)</label>
                    <textarea name="compiled_recipient_emails" class="form-control" rows="2" placeholder="a@example.com, b@example.com">{{ old('compiled_recipient_emails', $settings->compiled_recipient_emails) }}</textarea>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="cc_division_hod_on_compiled" value="1" id="ccHod" @checked(old('cc_division_hod_on_compiled', $settings->cc_division_hod_on_compiled))>
                    <label class="form-check-label" for="ccHod">CC division HoDs on the compiled summary email (one message with all PDFs)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reminders_enabled" value="1" id="remOn" @checked(old('reminders_enabled', $settings->reminders_enabled))>
                    <label class="form-check-label" for="remOn">Enable automated reminders / locks / summary dispatch</label>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success">Save settings</button>
        <a href="{{ route('weekly-briefing.index') }}" class="btn btn-outline-secondary">Back to weekly briefing</a>
    </form>

    <template id="wb-contributor-template">
        @include('weekly-briefing.partials.contributor-row', [
            'idx' => '__INDEX__',
            'rowNum' => '',
            'showRowNum' => true,
            'staffId' => 0,
            'apmDiv' => 0,
            'kind' => 'division',
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

    function wbWireRow(tr) {
        tr.querySelectorAll('.wb-kind').forEach(function (sel) {
            sel.addEventListener('change', function () {
                wbToggleKind(tr, sel.value);
            });
        });
        tr.querySelectorAll('.wb-remove-row').forEach(function (b) {
            b.addEventListener('click', function () {
                tr.remove();
                wbRenumberContributorRows();
            });
        });
        var k = tr.querySelector('.wb-kind');
        if (k) wbToggleKind(tr, k.value);
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
            dCol.style.opacity = '0.45';
            dCol.querySelectorAll('select').forEach(function (s) { s.disabled = true; });
            rCol.style.opacity = '1';
            rCol.querySelectorAll('select').forEach(function (s) { s.disabled = false; });
        } else {
            dCol.style.opacity = '1';
            dCol.querySelectorAll('select').forEach(function (s) { s.disabled = false; });
            rCol.style.opacity = '0.45';
            rCol.querySelectorAll('select').forEach(function (s) { s.disabled = true; });
        }
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
})();
</script>
@endpush
