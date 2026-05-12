@extends('layouts.app')

@section('title', 'Division Weekly Brief')
@section('header', 'Division Weekly Brief')

@section('content')
<div class="container-fluid py-3">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 text-success fw-bold"><i class="fas fa-newspaper me-2"></i>Division Weekly Brief</h4>
            <small class="text-muted">Reporting units (ISO week {{ \Carbon\Carbon::now()->isoWeek() }}, {{ \Carbon\Carbon::now()->isoWeekYear() }}). Start and edit are only available for units you are assigned in settings.</small>
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
                @php $wbNowY = \Carbon\Carbon::now()->isoWeekYear(); $wbNowW = \Carbon\Carbon::now()->isoWeek(); @endphp
                <a href="{{ route('weekly-briefing.compiled-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-archive me-1"></i> Compiled PDF</a>
                <a href="{{ route('weekly-briefing.completion-summary-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-clipboard-list me-1"></i> Completion summary</a>
            @endif
        </div>
    </div>

    @if(count($weekRows) > 0)
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-semibold">This ISO week (W{{ \Carbon\Carbon::now()->isoWeek() }})</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:3rem" class="text-center">#</th>
                            <th>Reporting unit</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($weekRows as $i => $row)
                            @php
                                $k = $row['key'];
                                $r = $row['report'];
                                $canFile = ! empty($filingKeySet[$k]);
                            @endphp
                            <tr>
                                <td class="text-center text-muted">{{ $i + 1 }}</td>
                                <td>{{ \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($k) }}</td>
                                <td>
                                    @if($r)
                                        <span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span>
                                    @else
                                        <span class="badge bg-light text-dark">not started</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($r)
                                        @if($canFile)
                                            <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
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
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-light fw-semibold">Reports ({{ $year }})</div>
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
                        @forelse($reports as $r)
                            @php $canFile = ! empty($filingKeySet[$r->contribution_key]); @endphp
                            <tr>
                                <td class="text-center text-muted">{{ ($reports->firstItem() ?? 1) + $loop->index }}</td>
                                <td>W{{ $r->report_iso_week }}</td>
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
                            <tr><td colspan="6" class="text-center text-muted py-4">No reports for this year yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $reports->withQueryString()->links() }}</div>
        </div>
    </div>
</div>
@endsection
