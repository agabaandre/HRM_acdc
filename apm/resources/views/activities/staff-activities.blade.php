@extends('layouts.app')

@section('title', $staff->fname . ' ' . $staff->lname . ' - Activities')

@section('styles')
<style>
    .avatar-sm {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        transition: background-color 0.2s ease;
    }
    
    .badge.rounded-pill {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }
    
    .card-footer .row > div {
        padding: 0.5rem;
    }
    
    .card-footer .d-flex {
        min-height: 60px;
    }
    
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .text-muted {
        color: #6c757d !important;
    }
    
    .fw-semibold {
        font-weight: 600 !important;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        border-radius: 0.5rem;
    }
    
    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
    
    .card {
        border-radius: 0.75rem;
        overflow: hidden;
    }
    
    .badge.rounded-pill {
        font-weight: 500;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
    
    .nav-tabs .nav-link {
        border-radius: 0.5rem 0.5rem 0 0;
        border: none;
        color: #6c757d;
        font-weight: 500;
    }
    
    .nav-tabs .nav-link.active {
        background-color: #119A48;
        color: white;
        border-color: #119A48;
    }
    
    .nav-tabs .nav-link:hover {
        border-color: #119A48;
        color: #119A48;
    }
    
    .card-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;
    }
</style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">
                                <i class="bx bx-user me-2 text-success"></i>
                                {{ $staff->title }} {{ $staff->fname }} {{ $staff->lname }}
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="bx bx-briefcase me-1"></i>
                                {{ $staff->job_name ?? 'Not specified' }}
                                @if($staff->duty_station_name)
                                    • {{ $staff->duty_station_name }}
                                @endif
                            </p>
                            <small class="text-muted">
                                <i class="bx bx-calendar me-1"></i>
                                {{ strtoupper($quarter) }} {{ $year }} Activities
                            </small>
                        </div>
                        <div class="text-end">
                            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary">
                                <i class="bx bx-arrow-back me-1"></i>
                                Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-0">
                                <i class="bx bx-filter me-2 text-success"></i>
                                Filter Activities
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <form method="GET" action="{{ route('staff.activities', ['staff_id' => $staff->staff_id, 'matrix' => $matrix->id]) }}" class="d-flex gap-2">
                                <select name="status" class="form-select form-select-sm" style="width: auto;">
                                    <option value="">All Status</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                                </select>
                                <select name="activity_type" class="form-select form-select-sm" style="width: auto;">
                                    <option value="">All Types</option>
                                    <option value="regular" {{ request('activity_type') == 'regular' ? 'selected' : '' }}>Regular Activities</option>
                                    <option value="single_memo" {{ request('activity_type') == 'single_memo' ? 'selected' : '' }}>Single Memos</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bx bx-search"></i>
                                </button>
                                @if(request('status') || request('activity_type'))
                                    <a href="{{ route('staff.activities', ['staff_id' => $staff->staff_id, 'matrix' => $matrix->id]) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bx bx-x"></i>
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activities Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs nav-fill" id="activitiesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="my-division-tab" data-bs-toggle="tab" 
                                    data-bs-target="#my-division" type="button" role="tab" 
                                    aria-controls="my-division" aria-selected="true">
                                <i class="bx bx-building me-2"></i>
                                My Division Activities
                                <span class="badge bg-primary ms-2">{{ $myDivisionActivities->count() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="other-divisions-tab" data-bs-toggle="tab" 
                                    data-bs-target="#other-divisions" type="button" role="tab" 
                                    aria-controls="other-divisions" aria-selected="false">
                                <i class="bx bx-group me-2"></i>
                                Other Division Activities
                                <span class="badge bg-info ms-2">{{ $otherDivisionActivities->count() }}</span>
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="activitiesTabsContent">
                        <!-- My Division Activities Tab -->
                        <div class="tab-pane fade show active" id="my-division" role="tabpanel" 
                             aria-labelledby="my-division-tab">
                            <div class="p-4">
                                @php
                                    // Calculate total days for my division activities
                                    $myDivisionTotalDays = 0;
                                    foreach($myDivisionActivities as $activity) {
                                        $participantSchedule = $activity->participantSchedules
                                            ->where('participant_id', $staff->staff_id)
                                            ->where('is_home_division', true)
                                            ->first();
                                        if($participantSchedule) {
                                            $myDivisionTotalDays += $participantSchedule->participant_days;
                                        }
                                    }
                                @endphp
                                
                                @if($myDivisionActivities->count() > 0)
                                    <div class="alert alert-info mb-3">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <strong>Total Division Days: {{ $myDivisionTotalDays }}</strong>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold">Activity Name</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold">Division</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Start Date</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">End Date</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Days</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Status</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($myDivisionActivities as $activity)
                                                    @php
                                                        $startDate = \Carbon\Carbon::parse($activity->date_from);
                                                        $endDate = \Carbon\Carbon::parse($activity->date_to);
                                                        
                                                        // Get participant days from participant_schedules table for home division
                                                        $participantSchedule = $activity->participantSchedules
                                                            ->where('participant_id', $staff->staff_id)
                                                            ->where('is_home_division', true)
                                                            ->first();
                                                        $days = $participantSchedule ? $participantSchedule->participant_days : 0;
                                                    @endphp
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="fw-semibold text-wrap" style="max-width: 250px;">
                                                                {{ $activity->activity_title }}
                                                            </div>
                                                            @if($activity->is_single_memo)
                                                                <small class="badge bg-warning text-dark mt-1">Single Memo</small>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="text-muted">{{ $activity->matrix->division->division_name ?? 'N/A' }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="text-muted">{{ $startDate->format('M d, Y') }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="text-muted">{{ $endDate->format('M d, Y') }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="badge bg-primary">{{ $days }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            @if($activity->is_single_memo)
                                                                {{-- Single Memo: Show overall status --}}
                                                                <span class="badge bg-{{ $activity->overall_status === 'approved' ? 'success' : ($activity->overall_status === 'pending' ? 'warning' : 'secondary') }}">
                                                                    {{ ucfirst($activity->overall_status) }}
                                                                </span>
                                                            @else
                                                                {{-- Matrix Activity: Show matrix status --}}
                                                                <span class="badge bg-{{ $activity->matrix->status === 'approved' ? 'success' : ($activity->matrix->status === 'pending' ? 'warning' : 'secondary') }}">
                                                                    {{ ucfirst($activity->matrix->status ?? 'N/A') }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            @if($activity->is_single_memo)
                                                                <a href="{{ route('activities.single-memos.show', $activity) }}" 
                                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                                    <i class="bx bx-show me-1"></i>
                                                                    Preview
                                                                </a>
                                                            @else
                                                                <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                                    <i class="bx bx-show me-1"></i>
                                                                    Preview
                                                                </a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5">
                                        <i class="bx bx-calendar-x fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">No Division Activities</h5>
                                        <p class="text-muted mb-0">This staff member has no activities in their division for {{ strtoupper($quarter) }} {{ $year }}.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Other Division Activities Tab -->
                        <div class="tab-pane fade" id="other-divisions" role="tabpanel" 
                             aria-labelledby="other-divisions-tab">
                            <div class="p-4">
                                @php
                                    // Calculate total days for other division activities
                                    $otherDivisionTotalDays = 0;
                                    foreach($otherDivisionActivities as $activity) {
                                        $participantSchedule = $activity->participantSchedules
                                            ->where('participant_id', $staff->staff_id)
                                            ->where('is_home_division', false)
                                            ->first();
                                        if($participantSchedule) {
                                            $otherDivisionTotalDays += $participantSchedule->participant_days;
                                        }
                                    }
                                @endphp
                                
                                @if($otherDivisionActivities->count() > 0)
                                    <div class="alert alert-warning mb-3">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <strong>Total Other Division Days: {{ $otherDivisionTotalDays }}</strong>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold">Activity Name</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold">Division</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Start Date</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">End Date</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Days</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Status</th>
                                                    <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($otherDivisionActivities as $activity)
                                                    @php
                                                        $startDate = \Carbon\Carbon::parse($activity->date_from);
                                                        $endDate = \Carbon\Carbon::parse($activity->date_to);
                                                        
                                                        // Get participant days from participant schedules for other divisions
                                                        $participantSchedule = $activity->participantSchedules
                                                            ->where('participant_id', $staff->staff_id)
                                                            ->where('is_home_division', false)
                                                            ->first();
                                                        $days = $participantSchedule ? $participantSchedule->participant_days : 0;
                                                    @endphp
                                                    <tr>
                                                        <td class="px-3 py-3">
                                                            <div class="fw-semibold text-wrap" style="max-width: 250px;">
                                                                {{ $activity->activity_title }}
                                                            </div>
                                                            @if($activity->is_single_memo)
                                                                <small class="badge bg-warning text-dark mt-1">Single Memo</small>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-3">
                                                            <span class="text-muted">{{ $activity->matrix->division->division_name ?? 'N/A' }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="text-muted">{{ $startDate->format('M d, Y') }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="text-muted">{{ $endDate->format('M d, Y') }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            <span class="badge bg-info">{{ $days }}</span>
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            @if($activity->is_single_memo)
                                                                {{-- Single Memo: Show overall status --}}
                                                                <span class="badge bg-{{ $activity->overall_status === 'approved' ? 'success' : ($activity->overall_status === 'pending' ? 'warning' : 'secondary') }}">
                                                                    {{ ucfirst($activity->overall_status) }}
                                                                </span>
                                                            @else
                                                                {{-- Matrix Activity: Show matrix status --}}
                                                                <span class="badge bg-{{ $activity->matrix->status === 'approved' ? 'success' : ($activity->matrix->status === 'pending' ? 'warning' : 'secondary') }}">
                                                                    {{ ucfirst($activity->matrix->status ?? 'N/A') }}
                                                                </span>
                                                            @endif
                                                        </td>
                                                        <td class="px-3 py-3 text-center">
                                                            @if($activity->is_single_memo)
                                                                <a href="{{ route('activities.single-memos.show', $activity) }}" 
                                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                                    <i class="bx bx-show me-1"></i>
                                                                    Preview
                                                                </a>
                                                            @else
                                                                <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                                                    <i class="bx bx-show me-1"></i>
                                                                    Preview
                                                                </a>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="text-center py-5">
                                        <i class="bx bx-calendar-x fs-1 text-muted mb-3"></i>
                                        <h5 class="text-muted">No Other Division Activities</h5>
                                        <p class="text-muted mb-0">This staff member has no activities in other divisions for {{ strtoupper($quarter) }} {{ $year }}.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection