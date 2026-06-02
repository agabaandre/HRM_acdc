@extends('layouts.app')

@section('title', 'Weekly brief')
@section('header', 'Weekly brief')

@section('content')
@php
    $directorReviewKeySet = $directorReviewKeySet ?? [];
    $hubCanViewAllReports = $hubCanViewAllReports ?? false;
    $tab = $tab ?? 'this_week';
    $filingIsoYear = $filingIsoYear ?? \Carbon\Carbon::now()->isoWeekYear();
    $filingIsoWeek = $filingIsoWeek ?? \Carbon\Carbon::now()->isoWeek();
    $filingWeekHumanRange = $filingWeekHumanRange ?? \App\Models\WeeklyBriefingReport::humanIsoWeekRangeInline((int) $filingIsoYear, (int) $filingIsoWeek);
    $allReportsDefaultYear = $allReportsDefaultYear ?? (int) $filingIsoYear;
    $allReportsDefaultWeek = $allReportsDefaultWeek ?? (int) $filingIsoWeek;
    $filingSubmissionDeadline = $filingSubmissionDeadline ?? \App\Models\WeeklyBriefingReport::syntheticDeadlineForIsoWeek(
        \App\Models\WeeklyBriefingSetting::current(),
        (int) $filingIsoYear,
        (int) $filingIsoWeek
    );
@endphp
<div class="container-fluid py-3">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-success fw-bold"><i class="fas fa-newspaper me-2"></i>Weekly brief</h4>
            <small class="text-muted d-block">@if(! empty($hubShowsDirectorateOversight))You are viewing submission status for divisions in your directorate; open <strong>Director review</strong> on submitted briefs to review and mark complete.@elseif(\App\Services\DivisionWeeklyBriefGate::mayActAsDivisionAdminAssistant())You may file weekly briefs for divisions where you are the <strong>admin assistant</strong> (on behalf of the division head).@else Contributors edit assigned units.@endif</small>
            <small class="text-muted d-block"><strong>Active reporting week:</strong> {{ $filingWeekHumanRange }}</small>
            <small class="text-muted"><strong><i class="fas fa-calendar-check me-1"></i>Submission deadline</strong> {{ $filingSubmissionDeadline->format('D, M j, Y') }} at {{ $filingSubmissionDeadline->format('g:i A') }}</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($filingWeekReports->count() === 1)
                @php $only = $filingWeekReports->first(); @endphp
                <a href="{{ route('weekly-briefing.edit', $only) }}" class="btn btn-success"><i class="fas fa-edit me-1"></i> Continue reporting week</a>
                <a href="{{ route('weekly-briefing.pdf', $only) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf me-1"></i> PDF</a>
            @elseif($filingWeekReports->count() > 1)
                <div class="dropdown">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">Continue reporting week</button>
                    <ul class="dropdown-menu">
                        @foreach($filingWeekReports as $r)
                            <li><a class="dropdown-item" href="{{ route('weekly-briefing.edit', $r) }}">{{ $r->contributionEntityLabel() }} ({{ $r->status }})</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @foreach($startUrls as $key => $url)
                <a href="{{ $url }}" class="btn btn-outline-success btn-sm">Start {{ \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($key) }}</a>
            @endforeach
            @if(\App\Services\DivisionWeeklyBriefGate::mayAccessCompiledBriefingExports())
                <a href="{{ route('weekly-briefing.compiled-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-archive me-1"></i> Compiled PDF</a>
                <a href="{{ route('weekly-briefing.completion-summary-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-clipboard-list me-1"></i> Completion summary</a>
            @endif
            @if(count($wbDirectorCombinedOptions ?? []) > 0)
                @if(count($wbDirectorCombinedOptions) === 1)
                    @php $o0 = $wbDirectorCombinedOptions[0]; @endphp
                    <a href="{{ route('weekly-briefing.directorate-combined-pdf', ['year' => $wbNowY, 'week' => $wbNowW, 'directorate_id' => $o0['directorate_id']]) }}" class="btn btn-outline-info" target="_blank"><i class="fas fa-layer-group me-1"></i> Director report — my directorate</a>
                @else
                    <div class="dropdown">
                        <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-layer-group me-1"></i> Director report — my directorates</button>
                        <ul class="dropdown-menu">
                            @foreach($wbDirectorCombinedOptions as $o)
                                <li>
                                    <a class="dropdown-item" target="_blank" href="{{ route('weekly-briefing.directorate-combined-pdf', ['year' => $wbNowY, 'week' => $wbNowW, 'directorate_id' => $o['directorate_id']]) }}">{{ $o['label'] }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    </div>

    @php
        $thisWeekTabQuery = array_filter([
            'tab' => 'this_week',
            'tw_status' => $twStatus ?? '',
            'tw_search' => $twSearch ?? '',
        ], fn ($v) => $v !== null && $v !== '');
        $allTabQuery = array_filter([
            'tab' => 'all',
            'year' => $filterYear ?? $allReportsDefaultYear,
            'week' => isset($filterWeek) && $filterWeek !== null ? $filterWeek : $allReportsDefaultWeek,
            'status' => $filterStatus ?? '',
            'search' => $filterSearch ?? '',
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <ul class="nav nav-tabs mb-0">
        <li class="nav-item">
            <a class="nav-link @if($tab === 'this_week') active @endif fw-semibold" href="{{ route('weekly-briefing.index', $thisWeekTabQuery) }}">This reporting week</a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if($tab === 'all') active @endif fw-semibold" href="{{ route('weekly-briefing.index', $allTabQuery) }}">All reports</a>
        </li>
    </ul>

    <div class="card shadow-sm border-top-0 rounded-top-0">
        @if($tab === 'this_week')
            <div class="card-body border-bottom bg-light py-3">
                <p class="small text-muted mb-2"><strong>Active reporting week:</strong> {{ $filingWeekHumanRange }}</p>
                <form method="get" action="{{ route('weekly-briefing.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="this_week">
                    <div class="col-md-3">
                        <label class="form-label small mb-0 text-muted">Status</label>
                        <select name="tw_status" class="form-select form-select-sm">
                            <option value="" @selected(($twStatus ?? '') === '')>All statuses</option>
                            <option value="not_started" @selected(($twStatus ?? '') === 'not_started')>Not started</option>
                            <option value="draft" @selected(($twStatus ?? '') === 'draft')>Draft</option>
                            <option value="submitted" @selected(($twStatus ?? '') === 'submitted')>Submitted</option>
                            <option value="locked" @selected(($twStatus ?? '') === 'locked')>Locked</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0 text-muted">Division / unit</label>
                        <input type="search" name="tw_search" class="form-control form-control-sm" value="{{ $twSearch ?? '' }}" placeholder="Search by unit, contributor, directorate…" autocomplete="off">
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
                        <a href="{{ route('weekly-briefing.index', ['tab' => 'this_week']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                @if(($configuredUnitCount ?? 0) === 0)
                    <p class="text-muted p-4 mb-0">No reporting units are configured for your access.</p>
                @elseif(($thisWeekPaginator->total() ?? 0) === 0)
                    <p class="text-muted p-4 mb-0">No rows match the current filters.</p>
                @else
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-bottom bg-white small text-muted">
                        <span>Showing <strong>{{ $thisWeekPaginator->firstItem() }}</strong>–<strong>{{ $thisWeekPaginator->lastItem() }}</strong> of <strong>{{ $thisWeekPaginator->total() }}</strong> divisions / reporting units</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:3rem" class="text-center">#</th>
                                    <th>Division / reporting unit</th>
                                    <th>Directorate</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($thisWeekPaginator as $row)
                                    @php
                                        $k = $row['key'];
                                        $r = $row['report'];
                                        $canFile = ! empty($filingKeySet[$k]);
                                        $canDirReview = ! empty($directorReviewKeySet[$k]);
                                        $canDirectorReview = $r && $canDirReview && \App\Services\DivisionWeeklyBriefGate::mayDirectorReviewReportOnHub($r);
                                        $canDirectorView = $r && $canDirReview && \App\Services\DivisionWeeklyBriefGate::mayDirectorAccessReportOnHub($r);
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted">{{ $thisWeekPaginator->firstItem() + $loop->index }}</td>
                                        <td>
                                            <div class="fw-medium">{{ $row['label'] ?? \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($k) }}</div>
                                            @if(! empty($row['contributor']))
                                                @php $st = $row['contributor']->staff; @endphp
                                                @if($st)
                                                    <div class="small text-muted">{{ $st->name }}</div>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="align-top">
                                            @include('weekly-briefing.partials.directorate-cell', ['dd' => $row['directorate_display'] ?? []])
                                        </td>
                                        <td>
                                            @if($r)
                                                <span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span>
                                                @if($r->requiresDirectorReview())
                                                    <div class="small mt-1 {{ $r->isDirectorReviewed() ? 'text-success' : 'text-muted' }}">{{ $r->directorReviewSummaryLine() }}</div>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-dark">not started</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if($r)
                                                @if($hubCanViewAllReports)
                                                    <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                                    <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                                @else
                                                    @if($canFile)
                                                        <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                    @elseif($canDirectorReview)
                                                        <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-info">Director review</a>
                                                    @elseif($canDirectorView)
                                                        <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                                    @endif
                                                    @if($canFile || $canDirectorView || $canDirectorReview)
                                                        <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                                    @endif
                                                @endif
                                            @elseif($canFile && ! $hubCanViewAllReports)
                                                <a href="{{ route('weekly-briefing.create', ['contribution_key' => $k]) }}" class="btn btn-sm btn-success">Start</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($thisWeekPaginator->hasPages())
                        <div class="card-body border-top py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span class="small text-muted">Page {{ $thisWeekPaginator->currentPage() }} of {{ $thisWeekPaginator->lastPage() }}</span>
                            <nav aria-label="Weekly brief divisions pagination" class="ms-auto">{{ $thisWeekPaginator->withQueryString()->links() }}</nav>
                        </div>
                    @endif
                @endif
            </div>
        @else
            <div class="card-body border-bottom bg-light py-3">
                <form method="get" action="{{ route('weekly-briefing.index') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="all">
                    <div class="col-md-2">
                        <label class="form-label small mb-0 text-muted">ISO year</label>
                        <select name="year" class="form-select form-select-sm">
                            @foreach($yearOptions as $yOpt)
                                <option value="{{ $yOpt }}" @selected((int)($filterYear ?? $filingIsoYear) === (int) $yOpt)>{{ $yOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-0 text-muted">Week (ISO)</label>
                        <select name="week" class="form-select form-select-sm">
                            <option value="">Any week</option>
                            @for($w = 1; $w <= 53; $w++)
                                @php
                                    $weekOptionYear = (int) ($filterYear ?? $allReportsDefaultYear);
                                    $weekOptionLabel = \App\Models\WeeklyBriefingReport::isoWeekFilterOptionLabel($weekOptionYear, $w);
                                @endphp
                                <option value="{{ $w }}" @selected(isset($filterWeek) && (int) $filterWeek === $w)>{{ $weekOptionLabel }}</option>
                            @endfor
                        </select>
                        @if(! empty($filterWeekRangeLabel))
                            <small class="text-muted d-block mt-1">Showing: {{ $filterWeekRangeLabel }}</small>
                        @endif
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0 text-muted">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="" @selected(($filterStatus ?? '') === '')>All</option>
                            <option value="draft" @selected(($filterStatus ?? '') === 'draft')>Draft</option>
                            <option value="submitted" @selected(($filterStatus ?? '') === 'submitted')>Submitted</option>
                            <option value="locked" @selected(($filterStatus ?? '') === 'locked')>Locked</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0 text-muted">Reporting unit</label>
                        <input type="search" name="search" class="form-control form-control-sm" value="{{ $filterSearch ?? '' }}" placeholder="Search by unit, contributor, directorate…" autocomplete="off">
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
                        <a href="{{ route('weekly-briefing.index', ['tab' => 'all', 'year' => $allReportsDefaultYear, 'week' => $allReportsDefaultWeek]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:3rem" class="text-center">#</th>
                                <th>Reporting week</th>
                                <th>Reporting unit</th>
                                <th>Directorate</th>
                                <th>Week start → end</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports ?? [] as $r)
                                @php
                                    $listKey = (int) ($r->division_id ?? 0) > 0
                                        ? \App\Models\WeeklyBriefingContributor::contributionKeyForDivision((int) $r->division_id)
                                        : (string) $r->contribution_key;
                                    $canFile = ! empty($filingKeySet[$listKey]);
                                    $canDirReview = ! empty($directorReviewKeySet[$listKey]);
                                    $canDirectorReview = $canDirReview && \App\Services\DivisionWeeklyBriefGate::mayDirectorReviewReportOnHub($r);
                                    $canDirectorView = $canDirReview && \App\Services\DivisionWeeklyBriefGate::mayDirectorAccessReportOnHub($r);
                                @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $reports->firstItem() + $loop->index }}</td>
                                    <td><span class="small text-muted">W{{ $r->report_iso_week }}/{{ $r->report_iso_week_year }}</span><br>{{ $r->isoWeekDateRangeLabel(false, true) }}</td>
                                    <td>{{ $r->contributionEntityLabel() }}</td>
                                    <td class="align-top small">@include('weekly-briefing.partials.directorate-cell', ['dd' => $r->hubDirectorateDisplayRow()])</td>
                                    <td class="small">{{ $r->isoWeekStartEndLabel(true) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span>
                                        @if($r->requiresDirectorReview())
                                            <span class="small mt-1 d-block {{ $r->isDirectorReviewed() ? 'text-success' : 'text-muted' }}">{{ $r->directorReviewSummaryLine() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($hubCanViewAllReports)
                                            <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                            <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                        @else
                                            @if($canFile)
                                                <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            @elseif($canDirectorReview)
                                                <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-info">Director review</a>
                                            @elseif($canDirectorView)
                                                <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                            @endif
                                            @if($canFile || $canDirectorView || $canDirectorReview)
                                                <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No reports match the current filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($reports->hasPages())
                    <div class="card-body border-top py-2">{{ $reports->withQueryString()->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
