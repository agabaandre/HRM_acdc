@extends('layouts.app')

@section('title', 'Edit Weekly brief')
@section('header', 'Edit Weekly brief')

@section('content')
<div class="container-fluid py-3" id="weekly-briefing-page">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        $unlockOverrideActive = $unlockOverrideActive ?? false;
    @endphp
    @if($unlockOverrideActive && $settings->report_unlock_override_until)
        <div class="alert alert-warning border shadow-sm mb-3">
            <strong><i class="fas fa-unlock-alt me-1"></i>Administrative unlock is active.</strong>
            Editing @if($report->status === \App\Models\WeeklyBriefingReport::STATUS_LOCKED)and submission of this locked briefing @endif are allowed until
            <strong>{{ $settings->report_unlock_override_until->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</strong> ({{ config('app.timezone') }}).
        </div>
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

    @php
        $anyEditsOpen = $formEditable;
    @endphp
    <div class="alert alert-{{ $anyEditsOpen ? 'info' : 'secondary' }} border shadow-sm mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <strong><i class="fas fa-calendar-check me-1"></i>Submission deadline</strong>
            <span class="ms-1">{{ $submissionDeadline->format('l, F j, Y') }} at {{ $submissionDeadline->format('g:i A') }}</span>
        </div>
        @if($report->status === \App\Models\WeeklyBriefingReport::STATUS_LOCKED && ! ($unlockOverrideActive ?? false))
            <span class="badge bg-dark">Locked</span>
        @elseif($report->status === \App\Models\WeeklyBriefingReport::STATUS_LOCKED && ($unlockOverrideActive ?? false))
            <span class="badge bg-warning text-dark">Locked — open for edits (admin unlock)</span>
        @elseif(! $anyEditsOpen)
            <span class="badge bg-danger">Closed — deadline passed</span>
        @else
            <span class="badge bg-success">Open for edits</span>
        @endif
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between gap-2">
            <div>
                <h5 class="mb-1">{{ $report->isoWeekDateRangeLabel(true) }}</h5>
                @if($report->directorate)
                    <div><span class="badge bg-info text-dark">{{ $report->directorate->name }}</span></div>
                @endif
                <div><span class="badge bg-secondary">{{ $report->contributionEntityLabel() }}</span></div>
                @if($report->division && str_starts_with((string) ($report->contribution_key ?? ''), 'dr-'))
                    <div class="small text-muted">APM division: {{ $report->division->division_name }}</div>
                @endif
                @if($report->submitted_by_staff_id && $report->submittedBy)
                    @php
                        $subName = trim((string) ($report->submittedBy->name ?? ''));
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
                @if($report->requiresDirectorReview())
                    <div class="small mt-2 {{ $report->isDirectorReviewed() ? 'text-success' : 'text-muted' }}">
                        {{ $report->directorReviewSummaryLine() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($report->requiresDirectorReview())
        <div class="card shadow-sm mb-3 border-primary">
            <div class="card-header fw-bold text-primary"><i class="fas fa-user-tie me-1"></i>Director review</div>
            <div class="card-body">
                <p class="small text-muted mb-2">This reporting unit is tied to a <strong>directorate</strong> with a director on the <code>directorates</code> table. The director may adjust the submitted content until the deadline; use <strong>Mark reviewed by director</strong> when your review is complete. All director saves and this action are recorded on the trail.</p>
                @if($report->isDirectorReviewed() && $report->director_reviewed_at)
                    <p class="small mb-2"><strong>Reviewed at:</strong> {{ $report->director_reviewed_at->format('M j, Y g:i A') }}
                        @if($report->directorReviewedBy)
                            @php $dn = trim((string) ($report->directorReviewedBy->name ?? '')); @endphp
                            · <strong>{{ $dn !== '' ? $dn : 'Staff #'.$report->director_reviewed_by_staff_id }}</strong>
                        @endif
                    </p>
                @endif
                @php $trail = $report->director_review_trail; @endphp
                @if(is_array($trail) && count($trail) > 0)
                    <h6 class="small fw-bold mb-1">Completion trail</h6>
                    <ol class="small mb-0 ps-3">
                        @foreach($trail as $entry)
                            @if(is_array($entry))
                                <li>{{ $entry['action'] ?? '—' }} — staff #{{ (int) ($entry['staff_id'] ?? 0) }} @ {{ $entry['at'] ?? '' }}</li>
                            @endif
                        @endforeach
                    </ol>
                @endif
                @if($canMarkDirectorReview && ! $report->isDirectorReviewed())
                    <form method="post" action="{{ route('weekly-briefing.director-review', $report) }}" class="mt-3 d-inline" onsubmit="return confirm('Record that you have reviewed this weekly briefing as directorate director?');">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="fas fa-check-circle me-1"></i>Mark reviewed by director</button>
                    </form>
                @elseif($report->isDirectorReviewed() && $canDirectorEdit)
                    <p class="small text-muted mb-0 mt-2">You can still edit content until the deadline; each save is added to the trail above.</p>
                @endif
            </div>
        </div>
    @endif

    <form id="weekly-briefing-form" method="post" action="{{ route('weekly-briefing.update', $report) }}">
        @csrf
        @method('PUT')

        <fieldset class="border-0 m-0 p-0" @disabled(! $formEditable)>
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
                                        <div class="wb-quill-wrap">
                                            <div id="wb-major-q-{{ $idx }}" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>
                                            <input type="hidden" name="section1[{{ $idx }}][major_happening]" id="wb-major-h-{{ $idx }}" value="{{ $row['major_happening'] ?? '' }}">
                                        </div>
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
                <h6 class="mb-0 fw-bold text-dark"><i class="bx bx-error-circle me-2 text-dark"></i>Section 2 — Key bottlenecks &amp; escalation</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="bottleneck-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width:28%">Issue</th>
                                <th style="width:22%">Impact / risk level</th>
                                <th style="width:40%">Required action / SMT guidance or escalation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($s2 as $idx => $row)
                                @php $btUid = 'e'.$idx; @endphp
                                <tr class="bottleneck-row" data-wb-bt-uid="{{ $btUid }}" data-wb-bt-row="{{ $idx }}">
                                    <td>
                                        <div class="wb-quill-wrap">
                                            <div id="wb-bt-issue-{{ $btUid }}" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>
                                            <input type="hidden" name="section2[{{ $idx }}][issue]" id="wb-bt-issue-h-{{ $btUid }}" value="{{ $row['issue'] ?? '' }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-quill-wrap">
                                            <div id="wb-bt-impact-{{ $btUid }}" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>
                                            <input type="hidden" name="section2[{{ $idx }}][impact_risk]" id="wb-bt-impact-h-{{ $btUid }}" value="{{ $row['impact_risk'] ?? '' }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="wb-quill-wrap">
                                            <div id="wb-bt-action-{{ $btUid }}" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>
                                            <input type="hidden" name="section2[{{ $idx }}][required_action]" id="wb-bt-action-h-{{ $btUid }}" value="{{ $row['required_action'] ?? '' }}">
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-danger wb-remove-row" title="Remove row">×</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="wb-add-bottleneck"><i class="fas fa-plus me-1"></i>Add more bottlenecks</button>
            </div>
        </div>

        @if($formEditable)
            <div class="d-flex flex-wrap gap-2 mb-5">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>@if($canDirectorEdit && ! $canContributorEdit)Save changes (director)@else Save draft @endif</button>
                @if($canContributorSubmit)
                    <button type="submit" name="submit_final" value="1" class="btn btn-success"
                        @if($unlockOverrideActive ?? false)
                            onclick="return confirm('Submit this weekly brief now? After submission, normal rules apply again when the unlock window ends.');"
                        @else
                            onclick="return confirm('Submit this weekly briefing? You can still edit until the deadline if submission is allowed.');"
                        @endif
                    ><i class="fas fa-paper-plane me-1"></i>Submit</button>
                @endif
            </div>
        @endif
        </fieldset>
    </form>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<style>
    #weekly-briefing-page .wb-quill-editor .ql-editor { min-height: 120px; }
    #weekly-briefing-page #wb-major-happenings-table td,
    #weekly-briefing-page #bottleneck-table td { vertical-align: top; }
    #weekly-briefing-page #wb-major-happenings-table .wb-quill-editor .ql-toolbar,
    #weekly-briefing-page #bottleneck-table .wb-quill-editor .ql-toolbar { flex-wrap: wrap; }
    #weekly-briefing-page fieldset:disabled { opacity: 0.65; pointer-events: none; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    var form = document.getElementById('weekly-briefing-form');
    if (!form || typeof Quill === 'undefined') return;
    @if(! $formEditable)
    return;
    @endif

    var toolbar = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];
    var quillOpts = { theme: 'snow', modules: { toolbar: toolbar } };
    var editors = [];
    var wbBtJsSeq = 1000;

    function bindQuill(editorId, hiddenEl) {
        var host = document.getElementById(editorId);
        if (!host || !hiddenEl || host.__quill) return;
        var q = new Quill('#' + editorId, quillOpts);
        if (hiddenEl.value) {
            q.root.innerHTML = hiddenEl.value;
        }
        editors.push({ quill: q, hidden: hiddenEl });
    }

    function initSection1Row(idx) {
        var mh = document.getElementById('wb-major-h-' + idx);
        var mq = document.getElementById('wb-major-q-' + idx);
        if (mh && mq) {
            bindQuill('wb-major-q-' + idx, mh);
        }
        bindQuill('wb-desc-' + idx, document.getElementById('wb-desc-h-' + idx));
        bindQuill('wb-strat-' + idx, document.getElementById('wb-strat-h-' + idx));
    }

    function initBottleneckRow(tr) {
        var uid = tr.getAttribute('data-wb-bt-uid');
        if (!uid) return;
        bindQuill('wb-bt-issue-' + uid, document.getElementById('wb-bt-issue-h-' + uid));
        bindQuill('wb-bt-impact-' + uid, document.getElementById('wb-bt-impact-h-' + uid));
        bindQuill('wb-bt-action-' + uid, document.getElementById('wb-bt-action-h-' + uid));
    }

    for (var i = 0; i < 3; i++) {
        initSection1Row(i);
    }

    var tbody = document.querySelector('#bottleneck-table tbody');
    if (tbody) {
        tbody.querySelectorAll('tr.bottleneck-row').forEach(initBottleneckRow);
    }

    form.addEventListener('submit', function () {
        editors.forEach(function (pair) {
            pair.hidden.value = pair.quill.root.innerHTML;
        });
    });

    var addBtn = document.getElementById('wb-add-bottleneck');
    if (addBtn && tbody) {
        addBtn.addEventListener('click', function () {
            var n = tbody.querySelectorAll('tr.bottleneck-row').length;
            var uid = 'j' + (++wbBtJsSeq);
            var tr = document.createElement('tr');
            tr.className = 'bottleneck-row';
            tr.setAttribute('data-wb-bt-uid', uid);
            tr.setAttribute('data-wb-bt-row', String(n));
            tr.innerHTML =
                '<td><div class="wb-quill-wrap"><div id="wb-bt-issue-' + uid + '" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>' +
                '<input type="hidden" name="section2[' + n + '][issue]" id="wb-bt-issue-h-' + uid + '" value=""></div></td>' +
                '<td><div class="wb-quill-wrap"><div id="wb-bt-impact-' + uid + '" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>' +
                '<input type="hidden" name="section2[' + n + '][impact_risk]" id="wb-bt-impact-h-' + uid + '" value=""></div></td>' +
                '<td><div class="wb-quill-wrap"><div id="wb-bt-action-' + uid + '" class="wb-quill-editor border rounded bg-white" style="min-height:120px;"></div>' +
                '<input type="hidden" name="section2[' + n + '][required_action]" id="wb-bt-action-h-' + uid + '" value=""></div></td>' +
                '<td class="text-nowrap"><button type="button" class="btn btn-sm btn-outline-danger wb-remove-row">×</button></td>';
            tbody.appendChild(tr);
            initBottleneckRow(tr);
        });
        tbody.addEventListener('click', function (e) {
            if (e.target.closest('.wb-remove-row')) {
                var tr = e.target.closest('tr.bottleneck-row');
                if (tr && tbody.querySelectorAll('tr.bottleneck-row').length > 1) {
                    tr.remove();
                    editors = editors.filter(function (p) {
                        return p.hidden && form.contains(p.hidden);
                    });
                }
            }
        });
    }
})();
</script>
@endpush
