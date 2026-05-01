@extends('layouts.app')

@section('title', 'Reports — Average time per document')
@section('header', 'Average time per document')

@push('styles')
<style>
.adt-report .metric-card {
    border-radius: 0.5rem;
    background: #fff;
    border: 1px solid #e9ecef !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.05);
}
.adt-report .table thead th { font-size: 0.78rem; text-transform: uppercase; letter-spacing: .03em; color: #6c757d; white-space: nowrap; }
.adt-report .table td { vertical-align: middle; font-size: 0.9rem; }
.adt-report .doc-title { max-width: 280px; word-break: break-word; }
</style>
@endpush

@section('content')
@php
    /** @var \App\Services\ApproverDocumentTimingService $timingService */
    $yearOpts = range((int) date('Y'), (int) date('Y') - 8);
@endphp

<div class="container-fluid adt-report pb-4">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <a wire:navigate href="{{ route('reports.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bx bx-arrow-back me-1"></i> Reports
        </a>
        <span class="text-muted small ms-auto">Receipt rules match the Approver Dashboard average-time calculation.</span>
    </div>

    @if(empty($reportFullAccess))
        <div class="alert alert-info py-2 small mb-3">
            You are viewing <strong>your own</strong> approval timing only. Administrators (role 10 or permissions 87 / 88) can filter by any approver.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card metric-card h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-semibold">Actions in scope</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['total_rows']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card metric-card h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-semibold">Average time</div>
                    <div class="fs-4 fw-bold text-dark">{{ $summary['avg_display'] }}</div>
                    @if($summary['avg_hours'] !== null)
                        <div class="small text-muted">{{ $summary['avg_hours'] }} hours (numeric)</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card metric-card h-100">
                <div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-semibold">Total person-hours</div>
                    <div class="fs-4 fw-bold text-dark">{{ number_format($summary['total_hours'], 1) }}</div>
                    <div class="small text-muted">Sum of hours elapsed at selected filters</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-light py-2 d-flex flex-wrap align-items-center gap-2">
            <i class="bx bx-filter-alt text-success"></i>
            <strong>Filters</strong>
            <a href="{{ route('reports.approver-document-timing.export', request()->query()) }}" class="btn btn-success btn-sm ms-auto">
                <i class="bx bx-download me-1"></i> Export CSV
            </a>
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('reports.approver-document-timing.index') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-0">Approver</label>
                    <select name="staff_id" class="form-select form-select-sm" {{ empty($reportFullAccess) ? 'disabled' : '' }}>
                        @if(!empty($reportFullAccess))
                            <option value="">All approvers with records</option>
                        @endif
                        @foreach($staffOptions as $s)
                            <option value="{{ $s->staff_id }}" {{ (int)($filters['staff_id'] ?? 0) === (int)$s->staff_id ? 'selected' : '' }}>
                                {{ trim(($s->title ? $s->title . ' ' : '') . $s->fname . ' ' . $s->lname) }} ({{ $s->staff_id }})
                            </option>
                        @endforeach
                    </select>
                    @if(empty($reportFullAccess))
                        <input type="hidden" name="staff_id" value="{{ (int) user_session('staff_id') }}">
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Division</label>
                    <select name="division_id" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($divisions as $d)
                            <option value="{{ $d->id }}" {{ (int)($filters['division_id'] ?? 0) === (int)$d->id ? 'selected' : '' }}>{{ $d->division_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Document type</label>
                    <select name="document_type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        @foreach($documentTypes as $dt)
                            <option value="{{ $dt }}" {{ ($filters['document_type'] ?? '') === $dt ? 'selected' : '' }}>{{ $dt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Year</label>
                    <select name="year" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @foreach($yearOpts as $y)
                            <option value="{{ $y }}" {{ (int)($filters['year'] ?? 0) === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Month</label>
                    <select name="month" class="form-select form-select-sm">
                        <option value="">Any</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (int)($filters['month'] ?? 0) === $m ? 'selected' : '' }}>{{ date('M', mktime(0,0,0,$m,1)) }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0">Search</label>
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Title, document #, approver…">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-sm"><i class="bx bx-search me-1"></i> Apply</button>
                    <a href="{{ route('reports.approver-document-timing.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white py-2 d-flex align-items-center gap-2">
            <i class="bx bx-table"></i>
            <span class="fw-semibold">Document timing trail</span>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Approver</th>
                        <th>Type</th>
                        <th>Document</th>
                        <th>Division</th>
                        <th>Workflow / role</th>
                        <th>Received</th>
                        <th>Acted</th>
                        <th>Elapsed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $r)
                        @php
                            $docUrl = $timingService->resolveDocumentUrl($r->model_type, (int) $r->model_id);
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $r->staff_name_snapshot ?: 'Staff #' . $r->staff_id }}</div>
                                <div class="small text-muted">ID {{ $r->staff_id }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $r->document_type_label }}</span></td>
                            <td class="doc-title">
                                <div class="fw-medium">{{ \Illuminate\Support\Str::limit($r->document_title ?? '—', 120) }}</div>
                                @if($r->document_number_snapshot)
                                    <div class="small text-muted">{{ $r->document_number_snapshot }}</div>
                                @endif
                            </td>
                            <td>{{ $r->division_name_snapshot ?? '—' }}</td>
                            <td>
                                <div class="small">{{ $r->workflow_name_snapshot ?? '—' }}</div>
                                <div class="small text-muted">{{ $r->workflow_role_snapshot ?? ('Level ' . ($r->approval_order ?? '—')) }}</div>
                            </td>
                            <td class="small text-nowrap">{{ $r->received_at?->format('Y-m-d H:i') }}</td>
                            <td class="small text-nowrap">{{ $r->acted_at?->format('Y-m-d H:i') }}</td>
                            <td class="fw-semibold">{{ number_format((float) $r->hours_elapsed, 2) }} h</td>
                            <td class="text-end">
                                @if($docUrl)
                                    <a href="{{ url($docUrl) }}" class="btn btn-sm btn-outline-success" wire:navigate>Open</a>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                No timing rows yet. Run the backfill job from Jobs management, then approve documents to capture new actions automatically.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())
            <div class="card-footer bg-white">{{ $records->links() }}</div>
        @endif
    </div>

    <p class="small text-muted mt-3 mb-0">
        Rows store the approver’s staff ID and name at action time. “Received” is the prior submission or prior approval timestamp at that workflow step, consistent with the dashboard metric.
    </p>
</div>
@endsection
