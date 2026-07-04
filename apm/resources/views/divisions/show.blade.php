@extends('layouts.app')

@section('title', 'Division Details')

@section('header', 'Division Details')

@section('header-actions')
    <a wire:navigate href="{{ route('divisions.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Divisions
    </a>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        $isActive = $division->is_active ?? null;
        $staffRoles = [
            ['relation' => 'divisionHead', 'label' => 'Division Head', 'icon' => 'bx-user-circle', 'accent' => 'primary'],
            ['relation' => 'focalPerson', 'label' => 'Focal Person', 'icon' => 'bx-user-voice', 'accent' => 'info'],
            ['relation' => 'adminAssistant', 'label' => 'Admin Assistant', 'icon' => 'bx-support', 'accent' => 'success'],
            ['relation' => 'financeOfficer', 'label' => 'Finance Officer', 'icon' => 'bx-dollar-circle', 'accent' => 'warning'],
        ];
    @endphp

    {{-- Summary strip --}}
    <div class="card shadow-sm border-0 division-summary-card mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                            ID {{ $division->id }}
                        </span>
                        @if ($division->division_short_name)
                            <span class="badge bg-dark px-3 py-2">{{ $division->division_short_name }}</span>
                        @endif
                        @if ($division->category)
                            <span class="badge bg-secondary px-3 py-2">{{ $division->category }}</span>
                        @endif
                        @if ($isActive === true)
                            <span class="badge bg-success px-3 py-2">Active</span>
                        @elseif ($isActive === false)
                            <span class="badge bg-danger px-3 py-2">Inactive</span>
                        @else
                            <span class="badge bg-secondary px-3 py-2">Status not set</span>
                        @endif
                    </div>
                    <h4 class="mb-1 fw-semibold">{{ $division->division_name }}</h4>
                    @if ($division->directorate)
                        <p class="text-muted mb-0 small">
                            <i class="bx bx-buildings me-1"></i>
                            <a wire:navigate href="{{ route('directorates.show', $division->directorate) }}" class="text-decoration-none">
                                {{ $division->directorate->name }}
                            </a>
                        </p>
                    @endif
                </div>
                <div class="col-lg-4 text-lg-end">
                    <p class="text-muted small mb-0">
                        <i class="bx bx-info-circle me-1"></i>Divisions are managed in the main system
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Basic + additional --}}
        <div class="col-lg-5">
            <div class="card shadow-sm h-100 division-detail-card">
                <div class="card-header division-card-header division-card-header--primary">
                    <h6 class="mb-0"><i class="bx bx-info-circle me-2"></i>Basic Information</h6>
                </div>
                <div class="card-body p-4">
                    <dl class="row g-3 mb-0 division-dl">
                        <div class="col-sm-4">
                            <dt>Division ID</dt>
                            <dd class="text-primary fw-semibold">{{ $division->id }}</dd>
                        </div>
                        <div class="col-sm-8">
                            <dt>Short name</dt>
                            <dd>
                                @if ($division->division_short_name)
                                    <span class="badge bg-primary">{{ $division->division_short_name }}</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>
                        </div>
                        <div class="col-12">
                            <dt>Division name</dt>
                            <dd class="fw-semibold mb-0">{{ $division->division_name }}</dd>
                        </div>
                        <div class="col-sm-6">
                            <dt>Category</dt>
                            <dd>
                                @if ($division->category)
                                    <span class="badge bg-secondary">{{ $division->category }}</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>
                        </div>
                        <div class="col-sm-6">
                            <dt>Status</dt>
                            <dd>
                                @if ($isActive === true)
                                    <span class="badge bg-success">Active</span>
                                @elseif ($isActive === false)
                                    <span class="badge bg-danger">Inactive</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm mt-4 division-detail-card">
                <div class="card-header division-card-header division-card-header--info">
                    <h6 class="mb-0"><i class="bx bx-detail me-2"></i>Additional Information</h6>
                </div>
                <div class="card-body p-4">
                    <dl class="row g-3 mb-0 division-dl">
                        <div class="col-sm-6">
                            <dt>Directorate</dt>
                            <dd>
                                @if ($division->directorate)
                                    <a wire:navigate href="{{ route('directorates.show', $division->directorate) }}" class="fw-semibold text-decoration-none">
                                        {{ $division->directorate->name }}
                                    </a>
                                    <div class="text-muted small">ID {{ $division->directorate_id }}</div>
                                @elseif ($division->directorate_id)
                                    <span class="badge bg-info text-dark">ID {{ $division->directorate_id }}</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>
                        </div>
                        <div class="col-sm-6">
                            <dt>Director (staff ID)</dt>
                            <dd>
                                @if ($division->director_id)
                                    <span class="fw-semibold">{{ $division->director_id }}</span>
                                @else
                                    <span class="text-muted">Not specified</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($division->head_oic_id || $division->head_oic_start_date || $division->head_oic_end_date)
                        <hr class="my-4">
                        <h6 class="text-muted text-uppercase small fw-semibold mb-3">Head OIC</h6>
                        <dl class="row g-3 mb-0 division-dl">
                            <div class="col-sm-4">
                                <dt>OIC staff ID</dt>
                                <dd>{{ $division->head_oic_id ?: '—' }}</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Start</dt>
                                <dd>
                                    @if ($division->head_oic_start_date)
                                        {{ \Carbon\Carbon::parse($division->head_oic_start_date)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>End</dt>
                                <dd>
                                    @if ($division->head_oic_end_date)
                                        {{ \Carbon\Carbon::parse($division->head_oic_end_date)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @endif

                    @if ($division->director_oic_id || $division->director_oic_start_date || $division->director_oic_end_date)
                        <hr class="my-4">
                        <h6 class="text-muted text-uppercase small fw-semibold mb-3">Director OIC</h6>
                        <dl class="row g-3 mb-0 division-dl">
                            <div class="col-sm-4">
                                <dt>OIC staff ID</dt>
                                <dd>{{ $division->director_oic_id ?: '—' }}</dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>Start</dt>
                                <dd>
                                    @if ($division->director_oic_start_date)
                                        {{ \Carbon\Carbon::parse($division->director_oic_start_date)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="col-sm-4">
                                <dt>End</dt>
                                <dd>
                                    @if ($division->director_oic_end_date)
                                        {{ \Carbon\Carbon::parse($division->director_oic_end_date)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @endif
                </div>
            </div>
        </div>

        {{-- Staff assignments --}}
        <div class="col-lg-7">
            <div class="card shadow-sm h-100 division-detail-card">
                <div class="card-header division-card-header division-card-header--success">
                    <h6 class="mb-0"><i class="bx bx-group me-2"></i>Staff Assignments</h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach ($staffRoles as $role)
                            @php
                                $staff = $division->{$role['relation']};
                                $accent = $role['accent'];
                            @endphp
                            <div class="col-sm-6">
                                <div class="staff-role-card staff-role-card--{{ $accent }} h-100">
                                    <div class="staff-role-card__icon text-{{ $accent }}">
                                        <i class="bx {{ $role['icon'] }}"></i>
                                    </div>
                                    <div class="staff-role-card__body">
                                        <div class="staff-role-card__label">{{ $role['label'] }}</div>
                                        @if ($staff)
                                            <div class="staff-role-card__name">
                                                {{ trim(($staff->fname ?? '') . ' ' . ($staff->lname ?? '')) }}
                                            </div>
                                            <div class="staff-role-card__meta">
                                                {{ $staff->title ?? $staff->job_name ?? 'Staff' }}
                                                @if (! empty($staff->staff_id))
                                                    <span class="text-muted"> · ID {{ $staff->staff_id }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <div class="staff-role-card__empty text-muted">Not assigned</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .division-summary-card {
        background: linear-gradient(135deg, #f8fafc 0%, #fff 55%);
        border: 1px solid #e2e8f0 !important;
    }

    .division-detail-card {
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }

    .division-card-header {
        border-bottom: none;
        font-weight: 600;
        padding: 0.85rem 1.25rem;
        color: #fff;
    }

    .division-card-header--primary { background: #0d6efd; }
    .division-card-header--success { background: #198754; }
    .division-card-header--info { background: #0dcaf0; color: #052c33; }

    .division-dl dt {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 0.2rem;
    }

    .division-dl dd {
        margin-bottom: 0;
        font-size: 0.95rem;
        color: #1e293b;
    }

    .staff-role-card {
        display: flex;
        align-items: flex-start;
        gap: 0.85rem;
        padding: 1rem 1.1rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        border-left: 4px solid #cbd5e1;
        min-height: 5.5rem;
    }

    .staff-role-card--primary { border-left-color: #0d6efd; }
    .staff-role-card--info { border-left-color: #0dcaf0; }
    .staff-role-card--success { border-left-color: #198754; }
    .staff-role-card--warning { border-left-color: #ffc107; }

    .staff-role-card__icon {
        flex-shrink: 0;
        font-size: 1.75rem;
        line-height: 1;
        margin-top: 0.1rem;
    }

    .staff-role-card__label {
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 0.25rem;
    }

    .staff-role-card__name {
        font-weight: 600;
        font-size: 1rem;
        color: #0f172a;
        line-height: 1.3;
    }

    .staff-role-card__meta {
        font-size: 0.8rem;
        color: #64748b;
        margin-top: 0.15rem;
    }

    .staff-role-card__empty {
        font-size: 0.9rem;
        font-style: italic;
    }
</style>
@endpush
