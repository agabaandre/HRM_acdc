@extends('layouts.app')

@section('title', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')
@section('header', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')

@section('header-actions')
@endsection

@section('content')
<style>
.table-responsive {
    font-size: 0.875rem;
}
.table th, .table td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
}
.table th {
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}
.text-wrap {
    word-wrap: break-word;
    word-break: break-word;
}
.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}
.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>
    @if(isset($matrix))
        <!-- Matrix-specific activities view -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3 px-4 bg-light rounded-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                    <h4 class="mb-0 text-success fw-bold">
                        <i class="bx bx-task me-2 text-success"></i> 
                        Activities for {{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}
                    </h4>
                    <div>
                        <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to Matrix
                        </a>
                        @if($matrix->overall_status !== 'approved')
                            <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm">
                                <i class="bx bx-plus me-1"></i> Add Activity
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Matrix activities list -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-task me-2"></i> Matrix Activities
                            </h6>
                            <small class="text-muted">{{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}</small>
                        </div>
                    </div>
                    
                    @if($activities && $activities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Activity Title</th>
                                        <th>Responsible Person</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $actCount = 1; @endphp
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td>{{ $actCount++ }}</td>
                                            <td>
                                                <strong>{{ $activity->activity_title ?? 'Untitled Activity' }}</strong>
                                                @if($activity->is_single_memo)
                                                    <span class="badge bg-warning text-dark ms-2">Single Memo</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->responsiblePerson)
                                                    {{ $activity->responsiblePerson->fname }} {{ $activity->responsiblePerson->lname }}
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->date_from && $activity->date_to)
                                                    {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                                @else
                                                    <span class="text-muted">Dates not set</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $activity->status === 'PASSED' ? 'bg-success' : ($activity->status === 'pending' ? 'bg-warning' : 'bg-secondary') }}">
                                                    {{ strtoupper($activity->status ?? 'draft') }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @if($matrix->overall_status !== 'approved')
                                                        <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="bx bx-edit"></i>
                                                        </a>
                                                    @endif
                                                    @if($activity->status === 'PASSED' && $matrix->overall_status === 'approved')
                                                        <a href="{{ route('matrices.activities.memo-pdf', [$matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-success" title="Print PDF" target="_blank">
                                                            <i class="bx bx-printer"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        @if($activities instanceof \Illuminate\Pagination\LengthAwarePaginator && $activities->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $activities->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-task fs-1 text-primary opacity-50"></i>
                            <p class="mb-0">No activities found for this matrix.</p>
                            @if($matrix->overall_status !== 'approved')
                                <small>Click "Add Activity" to create the first activity.</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <!-- Main activities page view -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3 px-4 bg-light rounded-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                    <h4 class="mb-0 text-success fw-bold"><i class="bx bx-task me-2 text-success"></i> Activity Details</h4>
                </div>

                <div class="row g-3 align-items-end" id="activityFilters" autocomplete="off">
                    <form action="{{ route('activities.index') }}" method="GET" class="row g-3 align-items-end w-100">
                    <div class="col-md-2">
                        <label for="document_number" class="form-label fw-semibold mb-1">
                            <i class="bx bx-file me-1 text-success"></i> Document #
                        </label>
                        <input type="text" name="document_number" id="document_number" class="form-control" 
                               value="{{ request('document_number') }}" placeholder="Enter document number">
                    </div>
                    <div class="col-md-2">
                        <label for="staff_id" class="form-label fw-semibold mb-1">
                            <i class="bx bx-user me-1 text-success"></i> Responsible Person
                        </label>
                        <select name="staff_id" id="staff_id" class="form-select select2" style="width: 100%;">
                            <option value="">All Staff</option>
                            @foreach($staff as $staffMember)
                                <option value="{{ $staffMember->staff_id }}" {{ request('staff_id') == $staffMember->staff_id ? 'selected' : '' }}>
                                    {{ $staffMember->fname }} {{ $staffMember->lname }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="year" class="form-label fw-semibold mb-1">
                            <i class="bx bx-calendar me-1 text-success"></i> Year
                        </label>
                        <select name="year" id="year" class="form-select select2" style="width: 100%;">
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="quarter" class="form-label fw-semibold mb-1">
                            <i class="bx bx-time-five me-1 text-success"></i> Quarter
                        </label>
                        <select name="quarter" id="quarter" class="form-select select2" style="width: 100%;">
                            @foreach($quarters as $quarter)
                                <option value="{{ $quarter }}" {{ $selectedQuarter == $quarter ? 'selected' : '' }}>
                                    {{ $quarter }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="division_id" class="form-label fw-semibold mb-1">
                            <i class="bx bx-building me-1 text-success"></i> Division
                        </label>
                        <select name="division_id" id="division_id" class="form-select select2" style="width: 100%;">
                            <option value="">All Divisions</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ $selectedDivisionId == $division->id ? 'selected' : '' }}>
                                    {{ $division->division_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success btn-sm w-100" id="applyFilters">
                            <i class="bx bx-search-alt-2 me-1"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a href="{{ route('activities.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bx bx-reset me-1"></i> Reset
                        </a>
                    </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
        <div class="card-body p-0">
                <!-- Bootstrap Tabs Navigation -->
                <ul class="nav nav-tabs nav-fill" id="activitiesTabs" role="tablist">
                    @if(in_array(87, user_session('permissions', [])))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-activities-tab" data-bs-toggle="tab" data-bs-target="#all-activities" type="button" role="tab" aria-controls="all-activities" aria-selected="true">
                            <i class="bx bx-grid me-2"></i> All Activities
                            <span class="badge bg-primary text-white ms-2">{{ $allActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                    @endif
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ !in_array(87, user_session('permissions', [])) ? 'active' : '' }}" id="my-division-tab" data-bs-toggle="tab" data-bs-target="#my-division" type="button" role="tab" aria-controls="my-division" aria-selected="{{ !in_array(87, user_session('permissions', [])) ? 'true' : 'false' }}">
                            <i class="bx bx-home me-2"></i> My Division Activities
                            <span class="badge bg-success text-white ms-2">{{ $myDivisionActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shared-activities-tab" data-bs-toggle="tab" data-bs-target="#shared-activities" type="button" role="tab" aria-controls="shared-activities" aria-selected="false">
                            <i class="bx bx-share me-2"></i> Shared Activities
                            <span class="badge bg-info text-white ms-2">{{ $sharedActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="activitiesTabsContent">
                    <!-- All Activities Tab -->
                    @if(in_array(87, user_session('permissions', [])))
                    <div class="tab-pane fade show active" id="all-activities" role="tabpanel" aria-labelledby="all-activities-tab">
                        <div class="p-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <h6 class="mb-0 text-primary fw-bold">
                                        <i class="bx bx-grid me-2"></i> All Activities
                                    </h6>
                                    <small class="text-muted">All activities across all divisions for {{ $selectedQuarter }} {{ $selectedYear }}</small>
                                </div>
                            </div>
                            
                            @if($allActivities && $allActivities->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-primary">
                                            <tr>
                                                <th style="width: 5%;">#</th>
                                                <th style="width: 20%;">Activity Title</th>
                                                <th style="width: 8%;">Matrix</th>
                                                <th style="width: 12%;">Division</th>
                                                <th style="width: 10%;">Document #</th>
                                                <th style="width: 10%;">Responsible Person</th>
                                                <th style="width: 10%;">Date Range</th>
                                                <th style="width: 8%;">Fund Type</th>
                                                <th style="width: 7%;">Status</th>
                                                <th style="width: 10%;" class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @php $actCount = 1; @endphp
                                            @foreach($allActivities as $activity)
                                                <tr>
                                                    <td>{{ $actCount++ }}</td>
                                                    <td>
                                                        <div class="text-wrap" style="max-width: 200px;">
                                                            <strong>{{ Str::limit($activity->activity_title ?? 'Untitled Activity', 50) }}</strong>
                                                            @if($activity->is_single_memo)
                                                                <span class="badge bg-warning text-dark ms-1">SM</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('matrices.show', $activity->matrix) }}" class="text-decoration-none">
                                                            {{ $activity->matrix->year }} {{ $activity->matrix->quarter }}
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <div class="text-wrap" style="max-width: 150px;">
                                                            {{ Str::limit($activity->matrix->division->division_name ?? 'N/A', 20) }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-white">
                                                            {{ $activity->document_number ?? 'N/A' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="text-wrap" style="max-width: 120px;">
                                                            @if($activity->responsiblePerson)
                                                                {{ Str::limit($activity->responsiblePerson->fname . ' ' . $activity->responsiblePerson->lname, 15) }}
                                                            @else
                                                                <span class="text-muted">Not assigned</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($activity->date_from && $activity->date_to)
                                                            {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                            {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                                        @else
                                                            <span class="text-muted">Dates not set</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bx bx-money me-1"></i>
                                                            {{ $activity->fundType->name ?? 'N/A' }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {{ $activity->matrix->overall_status === 'approved' ? 'bg-success' : ($activity->overall_status === 'pending' ? 'bg-warning' : 'bg-secondary') }}">
                                                            {{ strtoupper($activity->matrix->overall_status ?? 'draft') }}
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group">
                                                            <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                                               class="btn btn-sm btn-outline-info" title="View">
                                                                <i class="bx bx-show"></i>
                                                            </a>
                                                            @if($activity->matrix->overall_status !== 'approved')
                                                                <a href="{{ route('matrices.activities.edit', [$activity->matrix, $activity]) }}" 
                                                                   class="btn btn-sm btn-outline-warning" title="Edit">
                                                                    <i class="bx bx-edit"></i>
                                                                </a>
                                                            @endif
                                                            @if($activity->overall_status === 'approved' && $activity->matrix->overall_status === 'approved')
                                                                <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}?print=pdf" 
                                                                   class="btn btn-sm btn-outline-success" title="Print PDF" target="_blank">
                                                                    <i class="bx bx-printer"></i>
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                @if($allActivities instanceof \Illuminate\Pagination\LengthAwarePaginator && $allActivities->hasPages())
                                    <div class="d-flex justify-content-center mt-3">
                                        {{ $allActivities->appends(request()->query())->links() }}
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-4 text-muted">
                                    <i class="bx bx-task fs-1 text-primary opacity-50"></i>
                                    <p class="mb-0">No activities found.</p>
                                    <small>Activities will appear here once they are created in matrices.</small>
                            </div>
                        @endif
                        </div>
                    </div>
                @endif
                
                <!-- My Division Activities Tab -->
                <div class="tab-pane fade {{ !in_array(87, user_session('permissions', [])) ? 'show active' : '' }}" id="my-division" role="tabpanel" aria-labelledby="my-division-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-home me-2"></i> My Division Activities
                                </h6>
                                <small class="text-muted">Activities in your division for {{ $selectedQuarter }} {{ $selectedYear }}</small>
                            </div>
                        </div>
                        
                        @if($myDivisionActivities && $myDivisionActivities->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                                    <thead class="table-success">
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 25%;">Activity Title</th>
                                            <th style="width: 10%;">Matrix</th>
                                            <th style="width: 12%;">Document #</th>
                                            <th style="width: 15%;">Responsible Person</th>
                                            <th style="width: 12%;">Date Range</th>
                                            <th style="width: 8%;">Fund Type</th>
                                            <th style="width: 8%;">Status</th>
                                            <th style="width: 10%;" class="text-center">Actions</th>
                                        </tr>
                </thead>
                <tbody>
                                        @php $actCount = 1; @endphp
                                        @foreach($myDivisionActivities as $activity)
                                            <tr>
                                                <td>{{ $actCount++ }}</td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 250px;">
                                                        <strong>{{ Str::limit($activity->activity_title ?? 'Untitled Activity', 60) }}</strong>
                                                        @if($activity->is_single_memo)
                                                            <span class="badge bg-warning text-dark ms-1">SM</span>
                                                        @endif
                                                    </div>
                            </td>
                            <td>
                                                    <a href="{{ route('matrices.show', $activity->matrix) }}" class="text-decoration-none">
                                                        {{ $activity->matrix->year }} {{ $activity->matrix->quarter }}
                                                    </a>
                            </td>
                            <td>
                                                    <span class="badge bg-info text-white">
                                                        {{ $activity->document_number ?? 'N/A' }}
                                                    </span>
                            </td>
                            <td>
                                                    <div class="text-wrap" style="max-width: 150px;">
                                                        @if($activity->responsiblePerson)
                                                            {{ Str::limit($activity->responsiblePerson->fname . ' ' . $activity->responsiblePerson->lname, 20) }}
                                                        @else
                                                            <span class="text-muted">Not assigned</span>
                                                        @endif
                                                    </div>
                            </td>
                            <td>
                                                    @if($activity->date_from && $activity->date_to)
                                                        {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                        {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                @else
                                                        <span class="text-muted">Dates not set</span>
                                @endif
                            </td>
                            <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bx bx-money me-1"></i>
                                                        {{ $activity->fundType->name ?? 'N/A' }}
                                                    </span>
                            </td>
                            <td>
                                                    <span class="badge {{ $activity->matrix->overall_status === 'approved' ? 'bg-success' : ($activity->matrix->overall_status === 'pending' ? 'bg-warning' : 'bg-secondary') }}">
                                                        {{ strtoupper($activity->matrix->overall_status ?? 'draft') }}
                                                    </span>
                            </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                                        @if($activity->matrix->overall_status !== 'approved')
                                                            <a href="{{ route('matrices.activities.edit', [$activity->matrix, $activity]) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                                        @endif
                                                        @if($activity->matrix->overall_status === 'approved' && $activity->matrix->overall_status === 'approved')
                                                            <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}?print=pdf" 
                                                               class="btn btn-sm btn-outline-success" title="Print PDF" target="_blank">
                                                                <i class="bx bx-printer"></i>
                                                            </a>
                                    @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            @if($myDivisionActivities instanceof \Illuminate\Pagination\LengthAwarePaginator && $myDivisionActivities->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $myDivisionActivities->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-home fs-1 text-success opacity-50"></i>
                                <p class="mb-0">No activities found in your division.</p>
                                <small>Activities will appear here once they are created in your division matrices.</small>
                                            </div>
                        @endif
                                            </div>
                                        </div>

                <!-- Shared Activities Tab -->
                <div class="tab-pane fade" id="shared-activities" role="tabpanel" aria-labelledby="shared-activities-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-info fw-bold">
                                    <i class="bx bx-share me-2"></i> Shared Activities
                                </h6>
                                <small class="text-muted">Activities you're added to in other divisions for {{ $selectedQuarter }} {{ $selectedYear }}</small>
                                    </div>
                                </div>
                        
                        @if($sharedActivities && $sharedActivities->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-info">
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 20%;">Activity Title</th>
                                            <th style="width: 8%;">Matrix</th>
                                            <th style="width: 12%;">Division</th>
                                            <th style="width: 10%;">Document #</th>
                                            <th style="width: 10%;">Date Range</th>
                                            <th style="width: 8%;">Fund Type</th>
                                            <th style="width: 7%;">Status</th>
                                            <th style="width: 10%;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $actCount = 1; @endphp
                                        @foreach($sharedActivities as $activity)
                                            <tr>
                                                <td>{{ $actCount++ }}</td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 200px;">
                                                        <strong>{{ Str::limit($activity->activity_title ?? 'Untitled Activity', 50) }}</strong>
                                                        @if($activity->is_single_memo)
                                                            <span class="badge bg-warning text-dark ms-1">SM</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="{{ route('matrices.show', $activity->matrix) }}" class="text-decoration-none">
                                                        {{ $activity->matrix->year }} {{ $activity->matrix->quarter }}
                                                    </a>
                                                </td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 150px;">
                                                        {{ Str::limit($activity->matrix->division->division_name ?? 'N/A', 20) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-white">
                                                        {{ $activity->document_number ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($activity->date_from && $activity->date_to)
                                                        {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                        {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                                    @else
                                                        <span class="text-muted">Dates not set</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bx bx-money me-1"></i>
                                                        {{ $activity->fundType->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge {{ $activity->matrix->overall_status === 'approved' ? 'bg-success' : ($activity->matrix->overall_status === 'pending' ? 'bg-warning' : 'bg-secondary') }}">
                                                        {{ strtoupper($activity->matrix->overall_status ?? 'draft') }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($activity->matrix->overall_status === 'approved' && $activity->matrix->overall_status === 'approved')
                                                            <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}?print=pdf" 
                                                               class="btn btn-sm btn-outline-success" title="Print PDF" target="_blank">
                                                                <i class="bx bx-printer"></i>
                                                            </a>
                                                        @endif
                                </div>
                            </td>
                        </tr>
                                        @endforeach
                </tbody>
            </table>
        </div>
                            
                            <!-- Pagination -->
                            @if($sharedActivities instanceof \Illuminate\Pagination\LengthAwarePaginator && $sharedActivities->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $sharedActivities->appends(request()->query())->links() }}
    </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-share fs-1 text-info opacity-50"></i>
                                <p class="mb-0">No shared activities found.</p>
                                <small>Activities you're added to in other divisions will appear here.</small>
        </div>
    @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filters change
    if (document.getElementById('year')) {
        document.getElementById('year').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (document.getElementById('quarter')) {
        document.getElementById('quarter').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    // Document number filter - submit on Enter key
    if (document.getElementById('document_number')) {
        document.getElementById('document_number').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
});
</script>
@endsection
