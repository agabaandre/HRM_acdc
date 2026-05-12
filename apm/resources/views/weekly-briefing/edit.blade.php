@extends('layouts.app')

@section('title', 'Edit Division Weekly Brief')
@section('header', 'Edit Division Weekly Brief')

@section('content')
<div class="container-fluid py-3" id="weekly-briefing-page">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $submissionDeadline = $report->submissionDeadline($settings);
        $s1 = old('section1', $report->section1_major_happenings ?? []);
        while (count($s1) < 3) {
            $s1[] = ['major_happening' => '', 'description_key_actions' => '', 'strategic_relevance' => ''];
        }
        $s1 = array_slice($s1, 0, 3);
        $s2 = old('section2', $report->section2_bottlenecks ?? []);
        if (count($s2) === 0) {
            $s2[] = ['issue' => '', 'impact_risk' => '', 'required_action' => ''];
        }
    @endphp

    <div class="alert alert-{{ $window->canEditReport($report) ? 'info' : 'secondary' }} border shadow-sm mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong><i class="fas fa-calendar-check me-1"></i>Submission deadline</strong>
            <span class="ms-1">{{ $submissionDeadline->format('l, F j, Y') }} at {{ $submissionDeadline->format('g:i A') }}</span>
        </div>
        @if($report->status === \App\Models\WeeklyBriefingReport::STATUS_LOCKED)
            <span class="badge bg-dark">Locked</span>
        @elseif(! $window->canEditReport($report))
            <span class="badge bg-danger">Closed — deadline passed</span>
        @else
            <span class="badge bg-success">Open for edits</span>
        @endif
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between gap-2">
            <div>
                <h5 class="mb-1">ISO week W{{ $report->report_iso_week }} / {{ $report->report_iso_week_year }}</h5>
                <small class="text-muted">Period starts {{ $report->period_start?->format('M j, Y') }}</small>
                @if($report->directorate)
                    <div><span class="badge bg-info text-dark">{{ $report->directorate->name }}</span></div>
                @endif
                <div><span class="badge bg-secondary">{{ $report->contributionEntityLabel() }}</span></div>
                @if($report->division && str_starts_with((string) ($report->contribution_key ?? ''), 'dr-'))
                    <div class="small text-muted">APM division: {{ $report->division->division_name }}</div>
                @endif
                @if($report->submitted_by_staff_id && $report->submittedBy)
                    @php
                        $subName = trim(($report->submittedBy->fname ?? '').' '.($report->submittedBy->lname ?? ''));
                    @endphp
                    <div class="small text-muted mt-1">
                        Submitted by <strong>{{ $subName !== '' ? $subName : 'Staff #'.$report->submitted_by_staff_id }}</strong>
                        @if($report->submitted_at)
                            <span>· {{ $report->submitted_at->format('M j, Y g:i A') }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div class="text-end">
                <span class="badge bg-{{ $report->status === 'submitted' ? 'success' : ($report->status === 'locked' ? 'dark' : 'warning') }}">{{ $report->status }}</span>
            </div>
        </div>
    </div>

    <form id="weekly-briefing-form" method="post" action="{{ route('weekly-briefing.update', $report) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                <h6 class="mb-0 text-success fw-bold"><i class="bx bx-news me-2 text-success"></i>Section 1 — Major happenings (max 3)</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Complete each row: <strong>Major happening</strong> (short title), <strong>Description &amp; key actions</strong>, and <strong>Strategic relevance to Africa CDC</strong>.</p>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="wb-major-happenings-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:3rem" class="text-center">#</th>
                                <th style="min-width:180px">Major happening</th>
                                <th style="min-width:240px">Description &amp; key actions</th>
                                <th style="min-width:240px">Strategic relevance to Africa CDC</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($s1 as $idx => $row)
                                <tr>
                                    <td class="text-center text-muted fw-semibold">{{ $idx + 1 }}</td>
                                    <td>
                                        <textarea class="form-control form-control-sm" id="wb-major-{{ $idx }}" name="section1[{{ $idx }}][major_happening]" rows="3" maxlength="500" placeholder="Short title">{{ $row['major_happening'] ?? '' }}</textarea>
                                    </td>
                                    <td>
                                        <div class="wb-quill-wrap">
                                            <div id="wb-desc-{{ $idx }}" class="wb-quill-editor border rounded bg-white" style="min-height:140px;"></div>
                                            <input type="hidden" name="section1[{{ $idx }}][description_key_actions]" id="wb-desc-h-{{ $idx }}" value="{{ $row['description_key_actions'] ?? '' }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-quill-wrap">
                                            <div id="wb-strat-{{ $idx }}" class="wb-quill-editor border rounded bg-white" style="min-height:140px;"></div>
                                            <input type="hidden" name="section1[{{ $idx }}][strategic_relevance]" id="wb-strat-h-{{ $idx }}" value="{{ $row['strategic_relevance'] ?? '' }}">
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4 border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex align-items-center">
                <h6 class="mb-0 text-warning fw-bold"><i class="bx bx-error-circle me-2 text-warning"></i>Section 2 — Key bottlenecks &amp; escalation</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="bottleneck-table">
                        <thead class="table-warning">
                            <tr>
                                <th style="width:28%">Issue</th>
                                <th style="width:22%">Impact / risk level</th>
                                <th style="width:40%">Required action / SMT guidance or escalation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($s2 as $idx => $row)
                                <tr class="bottleneck-row">
                                    <td><textarea class="form-control" name="section2[{{ $idx }}][issue]" rows="3">{{ $row['issue'] ?? '' }}</textarea></td>
                                    <td><textarea class="form-control" name="section2[{{ $idx }}][impact_risk]" rows="3">{{ $row['impact_risk'] ?? '' }}</textarea></td>
                                    <td><textarea class="form-control" name="section2[{{ $idx }}][required_action]" rows="3">{{ $row['required_action'] ?? '' }}</textarea></td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-danger wb-remove-row" title="Remove row">×</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="wb-add-bottleneck"><i class="fas fa-plus me-1"></i>Add row</button>
            </div>
        </div>

        @if($window->canEditReport($report))
            <div class="d-flex flex-wrap gap-2 mb-5">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save draft</button>
                @if($window->canSubmitReport($report))
                    <button type="submit" name="submit_final" value="1" class="btn btn-success" onclick="return confirm('Submit this weekly briefing? You can still edit until the deadline if submission is allowed.');"><i class="fas fa-paper-plane me-1"></i>Submit</button>
                @endif
            </div>
        @endif
    </form>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<style>
    #weekly-briefing-page .wb-quill-editor .ql-editor { min-height: 120px; }
    #weekly-briefing-page #wb-major-happenings-table td { vertical-align: top; }
    #weekly-briefing-page #wb-major-happenings-table .wb-quill-editor .ql-toolbar { flex-wrap: wrap; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    var form = document.getElementById('weekly-briefing-form');
    if (!form || typeof Quill === 'undefined') return;

    var editors = [];

    function initPair(idx) {
        var dh = document.getElementById('wb-desc-h-' + idx);
        var sh = document.getElementById('wb-strat-h-' + idx);
        var de = document.getElementById('wb-desc-' + idx);
        var se = document.getElementById('wb-strat-' + idx);
        if (!dh || !sh || !de || !se) return;

        var qd = new Quill('#wb-desc-' + idx, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] } });
        var qs = new Quill('#wb-strat-' + idx, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] } });
        if (dh.value) { qd.root.innerHTML = dh.value; }
        if (sh.value) { qs.root.innerHTML = sh.value; }
        editors.push({ quill: qd, hidden: dh });
        editors.push({ quill: qs, hidden: sh });
    }

    for (var i = 0; i < 3; i++) initPair(i);

    form.addEventListener('submit', function () {
        editors.forEach(function (pair) {
            pair.hidden.value = pair.quill.root.innerHTML;
        });
    });

    var tbody = document.querySelector('#bottleneck-table tbody');
    var addBtn = document.getElementById('wb-add-bottleneck');
    if (addBtn && tbody) {
        addBtn.addEventListener('click', function () {
            var n = tbody.querySelectorAll('tr.bottleneck-row').length;
            var tr = document.createElement('tr');
            tr.className = 'bottleneck-row';
            tr.innerHTML = '<td><textarea class="form-control" name="section2[' + n + '][issue]" rows="3"></textarea></td>' +
                '<td><textarea class="form-control" name="section2[' + n + '][impact_risk]" rows="3"></textarea></td>' +
                '<td><textarea class="form-control" name="section2[' + n + '][required_action]" rows="3"></textarea></td>' +
                '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-danger wb-remove-row">×</button></td>';
            tbody.appendChild(tr);
        });
        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.wb-remove-row')) {
                var tr = e.target.closest('tr.bottleneck-row');
                if (tr && tbody.querySelectorAll('tr.bottleneck-row').length > 1) tr.remove();
            }
        });
    }
})();
</script>
@endpush
