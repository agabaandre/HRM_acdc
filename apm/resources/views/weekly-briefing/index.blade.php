@extends('layouts.app')

@section('title', 'Staff list weekly report')
@section('header', 'Staff list weekly report')

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
            <h4 class="mb-0 text-success fw-bold"><i class="fas fa-newspaper me-2"></i>Weekly briefing</h4>
            <small class="text-muted">Reporting units you may file for (ISO week {{ \Carbon\Carbon::now()->isoWeek() }}, {{ \Carbon\Carbon::now()->isoWeekYear() }}).</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($currentWeekReports->count() === 1)
                @php $only = $currentWeekReports->first(); @endphp
                <a href="{{ route('weekly-briefing.edit', $only) }}" class="btn btn-success"><i class="fas fa-edit me-1"></i> Continue this week</a>
                <a href="{{ route('weekly-briefing.pdf', $only) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf me-1"></i> PDF</a>
            @elseif($currentWeekReports->count() > 1)
                <div class="dropdown">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">Continue this week</button>
                    <ul class="dropdown-menu">
                        @foreach($currentWeekReports as $r)
                            <li><a class="dropdown-item" href="{{ route('weekly-briefing.edit', $r) }}">{{ $r->contributionEntityLabel() }} ({{ $r->status }})</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @foreach($startUrls as $key => $url)
                <a href="{{ $url }}" class="btn btn-outline-success btn-sm">Start {{ \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($key) }}</a>
            @endforeach
            @php
                $wbRole = (int) (user_session('role') ?? user_session('user_role') ?? 0);
                $wbPerms = user_session('permissions', []) ?? [];
                $wbCompiledAccess = $wbRole === 10 || in_array(87, $wbPerms, true) || in_array(88, $wbPerms, true);
                $wbNowY = \Carbon\Carbon::now()->isoWeekYear();
                $wbNowW = \Carbon\Carbon::now()->isoWeek();
            @endphp
            @if($wbCompiledAccess)
                <a href="{{ route('weekly-briefing.compiled-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-primary" target="_blank"><i class="fas fa-file-archive me-1"></i> Compiled PDF</a>
                <a href="{{ route('weekly-briefing.completion-summary-pdf', ['year' => $wbNowY, 'week' => $wbNowW]) }}" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-clipboard-list me-1"></i> Completion summary</a>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h6 class="fw-bold">Schedule (from settings)</h6>
            <p class="small text-muted mb-0">
                Submission weekday: <strong>{{ ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][$settings->submission_weekday] ?? $settings->submission_weekday }}</strong>
                · Close: <strong>{{ $settings->submission_close_time }}</strong>
                · HoD reminder: <strong>{{ $settings->hod_reminder_time }}</strong>
                · Summary send: <strong>{{ $settings->summary_send_time }}</strong>
            </p>
        </div>
    </div>

    @if($currentWeekReports->count() > 1 || ! empty($startUrls))
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light fw-semibold">This ISO week (W{{ \Carbon\Carbon::now()->isoWeek() }})</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Reporting unit</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach($currentWeekReports as $r)
                            <tr>
                                <td>{{ $r->contributionEntityLabel() }}</td>
                                <td><span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach($startUrls as $key => $url)
                            <tr>
                                <td>{{ \App\Models\WeeklyBriefingContributor::presentationLabelForContributionKey($key) }}</td>
                                <td><span class="badge bg-light text-dark">not started</span></td>
                                <td class="text-end"><a href="{{ $url }}" class="btn btn-sm btn-success">Start</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-header bg-light fw-semibold">Your reports ({{ $year }})</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Week</th>
                            <th>Reporting unit</th>
                            <th>Period (Mon)</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $r)
                            <tr>
                                <td>W{{ $r->report_iso_week }}</td>
                                <td>{{ $r->contributionEntityLabel() }}</td>
                                <td>{{ $r->period_start?->format('Y-m-d') }}</td>
                                <td><span class="badge bg-{{ $r->status === 'submitted' ? 'success' : ($r->status === 'locked' ? 'secondary' : 'warning') }}">{{ $r->status }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('weekly-briefing.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <a href="{{ route('weekly-briefing.pdf', $r) }}" class="btn btn-sm btn-outline-secondary" target="_blank">PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No reports for this year yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $reports->withQueryString()->links() }}</div>
        </div>
    </div>
</div>
@endsection
