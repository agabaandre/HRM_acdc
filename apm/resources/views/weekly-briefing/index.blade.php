@extends('layouts.app')

@section('title', 'Weekly brief')
@section('header', 'Weekly brief')

@section('content')
@php
    $directorReviewKeySet = $directorReviewKeySet ?? [];
    $tab = $tab ?? 'this_week';
    $cy = $cy ?? \Carbon\Carbon::now()->isoWeekYear();
    $cw = $cw ?? \Carbon\Carbon::now()->isoWeek();
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
            <small class="text-muted">Reporting units by ISO week. Contributors edit assigned units; division directors may open and review division briefs where they are the director in the divisions table.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($filingWeekReports->count() === 1)
                @php $only = $filingWeekReports->first(); @endphp
                <a href="{{ route('weekly-briefing.edit', $only) }}" class="btn btn-success"><i class="fas fa-edit me-1"></i> Continue this week</a>
                <a href="{{ route('weekly-briefing.pdf', $only) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf me-1"></i> PDF</a>
            @elseif($filingWeekReports->count() > 1)
                <div class="dropdown">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">Continue this week</button>
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
                    <a href="{{ route('weekly-briefing.directorate-combined-pdf', ['year' => $wbNowY, 'week' => $wbNowW, 'directorate_id' => $o0['directorate_id']]) }}" class="btn btn-outline-info" target="_blank"><i class="fas fa-layer-group me-1"></i> Director report — my divisions (W{{ $wbNowW }})</a>
                @else
                    <div class="dropdown">
                        <button class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-layer-group me-1"></i> Director report — my divisions (W{{ $wbNowW }})</button>
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
            'year' => $filterYear ?? $cy,
            'week' => isset($filterWeek) && $filterWeek !== null ? $filterWeek : null,
            'status' => $filterStatus ?? '',
            'search' => $filterSearch ?? '',
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <ul class="nav nav-tabs mb-0">
        <li class="nav-item">
            <a class="nav-link @if($tab === 'this_week') active @endif fw-semibold" href="{{ route('weekly-briefing.index', $thisWeekTabQuery) }}">This ISO week (W{{ $cw }})</a>
        </li>
        <li class="nav-item">
            <a class="nav-link @if($tab === 'all') active @endif fw-semibold" href="{{ route('weekly-briefing.index', $allTabQuery) }}">All reports</a>
        </li>
    </ul>

    <div class="card shadow-sm border-top-0 rounded-top-0">
        @if($tab === 'this_week')
            <div class="card-body border-bottom bg-light py-3">
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
                        <label class="form-label small mb-0 text-muted">Reporting unit</label>
                        <input type="search" name="tw_search" class="form-control form-control-sm" value="{{ $twSearch ?? '' }}" placeholder="Search by name…" autocomplete="off">
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
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:3rem" class="text-center">#</th>
                                    <th>Reporting unit</th>
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
                                    @endphp
                                    <tr>
                                        <td class="text-center text-muted">{{ $thisWeekPaginator->firstItem() + $loop->index }}</td>
                                        <td>{{ \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($k) }}</td>
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
                                                @if($canFile)
                                                    <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                                @elseif($canDirReview)
                                                    <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-info">Director review</a>
                                                @endif
                                                <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                            @elseif($canFile)
                                                <a href="{{ route('weekly-briefing.create', ['contribution_key' => $k]) }}" class="btn btn-sm btn-success">Start</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($thisWeekPaginator->hasPages())
                        <div class="card-body border-top py-2">{{ $thisWeekPaginator->withQueryString()->links() }}</div>
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
                                <option value="{{ $yOpt }}" @selected((int)($filterYear ?? $cy) === (int) $yOpt)>{{ $yOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0 text-muted">ISO week</label>
                        <select name="week" class="form-select form-select-sm">
                            <option value="">Any week</option>
                            @for($w = 1; $w <= 53; $w++)
                                <option value="{{ $w }}" @selected(isset($filterWeek) && (int) $filterWeek === $w)>W{{ $w }}</option>
                            @endfor
                        </select>
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
                        <input type="search" name="search" class="form-control form-control-sm" value="{{ $filterSearch ?? '' }}" placeholder="Search by name…" autocomplete="off">
                    </div>
                    <div class="col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Apply</button>
                        <a href="{{ route('weekly-briefing.index', ['tab' => 'all']) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:3rem" class="text-center">#</th>
                                <th>Week</th>
                                <th>Reporting unit</th>
                                <th>Period (Mon)</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reports ?? [] as $r)
                                @php $canFile = ! empty($filingKeySet[$r->contribution_key]); @endphp
                                <tr>
                                    <td class="text-center text-muted">{{ $reports->firstItem() + $loop->index }}</td>
                                    <td>W{{ $r->report_iso_week }} / {{ $r->report_iso_week_year }}</td>
                                    <td>{{ $r->contributionEntityLabel() }}</td>
                                    <td>{{ $r->period_start?->format('Y-m-d') }}</td>
                                    <td><span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span></td>
                                    <td class="text-end">
                                        @if($canFile)
                                            <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        @endif
                                        <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No reports match the current filters.</td></tr>
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
