@extends('layouts.app')

@section('title', 'View Matrix')

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
</style>
@endsection

@section('header', 'Matrix Details')

@section('header-actions')
<div class="d-flex gap-2">
    
        @if( $matrix->overall_status=='draft' || $matrix->overall_status=='returned')
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> Add Activity
        </a>
        @endif

        @if( $matrix->overall_status=='approved'|| $matrix->overall_status=='pending')
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> Add Single Memo 
        </a>
        @endif

        @if(still_with_creator($matrix))
            <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning btn-sm shadow-sm">
                <i class="bx bx-edit me-1"></i> Edit Matrix
            </a>
        @endif
       <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary btn-sm">
       <i class="bx bx-arrow-back me-1"></i> Back
    </a>
</div>
@endsection

@section('content')

@include('matrices.partials.matrix-metadata')
   
<div class="col-md-12">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bx bx-calendar-event me-2 text-primary"></i>Activities
                    </h5>
                    <small class="text-muted d-block mt-1">
                        {{ $matrix->activities->count() }} activities in this matrix
                    </small>
                </div>
                <div class="col-md-6">
                    <div class="row g-2">
                        <!-- General Search -->
                        <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="bx bx-search text-muted"></i>
                            </span>
                                <input type="text" id="general-search" class="form-control" 
                                       placeholder="Search activities..." 
                                       value="{{ request('search') }}">
                            </div>
                        </div>
                        
                        <!-- Document Number Filter -->
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bx bx-hash text-muted"></i>
                                </span>
                                <input type="text" id="document-search" class="form-control" 
                                       placeholder="Document #" 
                                   value="{{ request('document_number') }}">
                        </div>
                        </div>
                        
                        <!-- Search Buttons -->
                        <div class="col-md-2">
                            <div class="btn-group w-100" role="group">
                                <button type="button" id="search-btn" class="btn btn-primary">
                            <i class="bx bx-search"></i>
                        </button>
                                <button type="button" id="clear-search-btn" class="btn btn-outline-secondary">
                                <i class="bx bx-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Page Size Selector -->
                    <div class="row mt-2">
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <label for="pageSizeSelect" class="form-label me-2 mb-0 text-muted small">Show:</label>
                                <select id="pageSizeSelect" class="form-select form-select-sm" style="width: 120px;">
                                    <option value="10" selected>10 per page</option>
                                    <option value="20">20 per page</option>
                                    <option value="50">50 per page</option>
                                    <option value="100">100 per page</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="d-flex justify-content-end align-items-center">
                                <small class="text-muted" id="showingRange">Loading...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <!-- Loading indicator -->
            <div id="activities-loading" class="text-center py-5" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading activities...</p>
            </div>

            <!-- Error message -->
            <div id="activities-error" class="alert alert-danger m-3" style="display: none;">
                <i class="bx bx-error-circle me-2"></i>
                <span id="activities-error-message">Failed to load activities. Please try again.</span>
            </div>
            
            <!-- Search status -->
            <div id="search-status" class="alert alert-info m-3" style="display: none;">
                <i class="bx bx-search me-2"></i>
                <span id="search-message">Searching...</span>
            </div>

            <!-- Top Pagination -->
            <div class="p-3 bg-light border-top" id="activities-top-pagination" style="display: none;">
                <div class="d-flex justify-content-center">
                    <!-- Top pagination will be loaded here via AJAX -->
                </div>
            </div>

            <!-- Activities table -->
            <div id="activities-table-container">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                             @if(can_take_action($matrix) && get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft' && $matrix->approval_level != 5)
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                            @endif
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">#</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 8%;">Document #</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 25%;">Title</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 12%;">Date Range</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 12%;">Responsible Person</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center" style="width: 8%;">Participants</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center" style="width: 8%;">Fund Type</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center" style="width: 8%;">Budget (Est./Avail.)</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center" style="width: 8%;">Status</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center" style="width: 8%;">Actions</th>
                        </tr>
                    </thead>
                        <tbody id="activities-tbody">
                            <!-- Activities will be loaded here via AJAX -->
                    </tbody>
                </table>
                                    </div>
                                    </div>

            <!-- Finance Officer Notice -->
            @if(can_take_action($matrix) && is_finance_officer($matrix))
                <div class="p-4 border-top bg-warning bg-opacity-10">
                    <div class="d-flex align-items-center">
                        <i class="bx bx-info-circle text-warning me-2 fs-4"></i>
                        <div>
                            <span class="fw-semibold text-warning">Finance Officer Notice:</span>
                            <span class="text-muted">As a Finance Officer, you must approve activities individually to enter the available budget for each activity. Bulk approval is not available for your approval level.</span>
                                    </div>
            </div>
                </div>
            @endif

            <!-- Approve Selected Activities Button -->
            <div class="p-4 border-top bg-light" id="approveSelectedSection" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bx bx-check-circle text-success me-2 fs-4"></i>
                        <span class="text-muted fw-semibold" id="selectedCount">0 activities selected</span>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-success btn-lg shadow-sm" id="approveSelectedBtn" data-bs-toggle="modal" data-bs-target="#approveSelectedModal">
                            <i class="bx bx-check me-2"></i> Pass Selected Activities
                        </button>
                        {{-- <button type="button" class="btn btn-danger btn-lg shadow-sm" id="rejectSelectedBtn" data-bs-toggle="modal" data-bs-target="#rejectSelectedModal">
                            <i class="bx bx-x me-2"></i> N
                        </button> --}}
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div class="p-4 bg-light border-top" id="activities-pagination">
                <div class="d-flex justify-content-center">
                    <!-- Pagination will be loaded here via AJAX -->
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Single Memos Section -->
@if($singleMemos->count() > 0)
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">
                            <i class="bx bx-file-text me-2 text-primary"></i>Single Memos
                        </h5>
                        <small class="text-muted d-block mt-1">
                            <span id="single-memos-count">{{ $singleMemos->total() }}</span> single memos in this matrix
                        </small>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-2">
                            <!-- General Search -->
                            <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bx bx-search text-muted"></i>
                                </span>
                                    <input type="text" id="single-memo-general-search" class="form-control" 
                                           placeholder="Search single memos..." 
                                       value="{{ request('single_memo_search') }}">
                            </div>
                            </div>
                            
                            <!-- Document Number Filter -->
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text bg-white">
                                        <i class="bx bx-hash text-muted"></i>
                                    </span>
                                    <input type="text" id="single-memo-document-search" class="form-control" 
                                           placeholder="Document #" 
                                           value="{{ request('single_memo_document_number') }}">
                                </div>
                            </div>
                            
                            <!-- Search Buttons -->
                            <div class="col-md-2">
                                <div class="btn-group w-100" role="group">
                                    <button type="button" id="single-memo-search-btn" class="btn btn-primary">
                                <i class="bx bx-search"></i>
                            </button>
                                    <button type="button" id="single-memo-clear-search-btn" class="btn btn-outline-secondary">
                                    <i class="bx bx-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Page Size Selector -->
                        <div class="row mt-2">
                            <div class="col-md-3">
                                <div class="d-flex align-items-center">
                                    <label for="single-memo-pageSizeSelect" class="form-label me-2 mb-0 text-muted small">Show:</label>
                                    <select id="single-memo-pageSizeSelect" class="form-select form-select-sm" style="width: 120px;">
                                        <option value="10" selected>10 per page</option>
                                        <option value="20">20 per page</option>
                                        <option value="50">50 per page</option>
                                        <option value="100">100 per page</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="d-flex justify-content-end align-items-center">
                                    <small class="text-muted" id="single-memo-showingRange">Loading...</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Loading indicator -->
                <div id="single-memos-loading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading single memos...</p>
                </div>

                <!-- Error message -->
                <div id="single-memos-error" class="alert alert-danger m-3" style="display: none;">
                    <i class="bx bx-error-circle me-2"></i>
                    <span id="single-memos-error-message">Failed to load single memos. Please try again.</span>
                </div>
                
                <!-- Search status -->
                <div id="single-memo-search-status" class="alert alert-info m-3" style="display: none;">
                    <i class="bx bx-search me-2"></i>
                    <span id="single-memo-search-message">Searching...</span>
                </div>

                <!-- Top Pagination for Single Memos -->
                <div class="p-3 bg-light border-top" id="single-memos-top-pagination" style="display: none;">
                    <div class="d-flex justify-content-center">
                        <!-- Top pagination will be loaded here via AJAX -->
                    </div>
                </div>

                <!-- Single memos table -->
                <div id="single-memos-table-container">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 5%;">#</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 10%;">Document #</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 25%;">Title</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 12%;">Date Range</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 15%;">Responsible Person</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 8%;">Participants</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 10%;">Fund Type</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 10%;">Budget (Est./Avail.)</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 10%;">Status</th>
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 5%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="single-memos-tbody">
                            <!-- Dynamic content will be loaded here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="single-memos-pagination" class="p-4 bg-light border-top">
                    <!-- Dynamic pagination will be loaded here -->
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Static single memos table for reference (will be replaced by AJAX) -->
<div style="display: none;">
    <table>
                        <tbody>
                            @php $memoCount = 1; @endphp
                            @forelse($singleMemos as $memo)
                                <tr style="background-color: {{ $memo->overall_status !== 'approved' ? '#fff3cd' : '#d5f5de' }};">
                                    <td class="px-3 py-3 text-center">
                                        <span class="badge bg-secondary rounded-pill">{{ $memoCount }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="badge bg-info text-dark">
                                            {{ $memo->document_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-wrap" style="max-width: 250px;">
                                        {{ $memo->activity_title }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="small text-wrap" style="max-width: 120px;">
                                            <div class="fw-bold text-primary">{{ \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') }}</div>
                                            <div class="text-muted">to {{ \Carbon\Carbon::parse($memo->date_to)->format('M d, Y') }}</div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="text-wrap" style="max-width: 120px;">
                                            @if($memo->responsiblePerson)
                                                <div class="fw-semibold">{{ $memo->responsiblePerson->fname }} {{ $memo->responsiblePerson->lname }}</div>
                                                <small class="text-muted">{{ $memo->responsiblePerson->job_name ?? 'N/A' }}</small>
                                            @else
                                                <span class="text-muted">Not assigned</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="badge bg-info rounded-pill">{{ $memo->total_participants }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="badge bg-info rounded-pill">{{ $memo->fundType->name }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        @php
                                            $budget = is_array($memo->budget_breakdown) ? $memo->budget_breakdown : json_decode($memo->budget_breakdown , true);
                                            $totalBudget = 0;

                                            if (is_array($budget)) {
                                                // Always recalculate from individual items to avoid JSON grand_total issues
                                                foreach ($budget as $key => $entries) {
                                                    if ($key === 'grand_total') continue;

                                                    if (is_array($entries)) {
                                                        foreach ($entries as $item) {
                                                            $unitCost = floatval($item['unit_cost'] ?? 0);
                                                            $units = floatval($item['units'] ?? 0);
                                                            $days = floatval($item['days'] ?? 1);
                                                            
                                                            // Use days when greater than 1, otherwise just unit_cost * units
                                                            if ($days > 1) {
                                                                $itemTotal = $unitCost * $units * $days;
                                                            } else {
                                                                $itemTotal = $unitCost * $units;
                                                            }
                                                            
                                                            $totalBudget += $itemTotal;
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
                                        <span class="fw-bold text-success">{{ number_format($totalBudget, 2) }} USD</span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="badge bg-{{ $memo->overall_status === 'approved' ? 'success' : 'warning' }} rounded-pill">
                                            {{ ucfirst($memo->overall_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-center">
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" class="btn btn-outline-primary btn-sm" title="View Single Memo">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            @if($memo->overall_status == 'draft' && 
                                                ($memo->responsible_person_id == user_session('staff_id') || 
                                                 $memo->staff_id == user_session('staff_id') || 
                                                 $matrix->division->division_head == user_session('staff_id') ||
                                                 $matrix->staff_id == user_session('staff_id')))
                                                @if($matrix->overall_status == 'draft')
                                                    <button type="button" class="btn btn-outline-info btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#copyActivityModal" 
                                                            data-activity-id="{{ $memo->id }}"
                                                            data-activity-title="{{ $memo->activity_title }}"
                                                            title="Copy Activity">
                                                        <i class="bx bx-copy"></i> Copy
                                                    </button>
                                                @endif
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteSingleMemoModal" 
                                                        data-memo-id="{{ $memo->id }}"
                                                        data-memo-title="{{ $memo->activity_title }}"
                                                        title="Delete Single Memo">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @php $memoCount++; @endphp
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="bx bx-file-text fs-1 text-muted mb-3 d-block"></i>
                                        <div class="text-muted">No single memos found for this matrix.</div>
                                        <small class="text-muted">Single memos will appear here once they are added.</small>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
</div>
@endif

<!-- Division Schedule and Approval Trail Section -->
<div class="row mt-4">
    <div class="col-lg-7">
        @if(count($matrix->division_staff) > 0)
            @include('matrices.partials.participants-schedule', ['divisionStaff' => $divisionStaff ?? $matrix->division_staff])
        @else
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="bx bx-calendar-x fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Division Schedule Available</h5>
                    <p class="text-muted mb-0">Staff schedules for {{ $matrix->quarter }} {{ $matrix->year }} will appear here once they are added.</p>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <!-- Approval Actions Section -->
        @if(can_take_action($matrix) || (can_division_head_edit($matrix) && $matrix->overall_status === 'returned'))
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bx bx-check-circle me-2 text-primary"></i>Approval Actions
                    </h5>
                </div>
                <div class="card-body">
                    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
                </div>
            </div>
        @endif
         @if(($matrix->activities->count() > 0 && still_with_creator($matrix)))
                <button type="button w-100" class="btn btn-success w-100 text-white" data-bs-toggle="modal" data-bs-target="#submitMatrixModal">
                    @if(can_division_head_edit($matrix))
                        <i class="fa fa-envelope"></i> Resubmit Matrix for Approval
                    @else
                        <i class="fa fa-envelope"></i> Submit Matrix for Approval
                    @endif
                </button>
        @endif



        <!-- Approval Trail Section -->
        @if(count($matrix->matrixApprovalTrails) > 0)
            @include('matrices.partials.approval-trail',['trails'=>$matrix->matrixApprovalTrails])
        @else
            <div class="card shadow-sm border-0">
                <div class="card-body text-center ">
                    <i class="bx bx-history fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Approval History</h5>
                    <p class="text-muted mb-0">Approval trail will appear here once actions are taken on this matrix.</p>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Action Buttons Section -->


<!-- Submit Matrix Confirmation Modal -->
<div class="modal fade" id="submitMatrixModal" tabindex="-1" aria-labelledby="submitMatrixModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
           
                <h5 class="modal-title text-white" id="submitMatrixModalLabel">
                    <i class="bx bx-save me-2"></i> 
                    @if(can_division_head_edit($matrix))
                        Resubmit Matrix for Approval
                    @else
                        Submit Matrix for Approval
                    @endif
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if(can_division_head_edit($matrix))
                    <p class="mb-3">Are you sure you want to resubmit this matrix for approval?</p>
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Note:</strong> As the Head of Division, you are resubmitting this matrix after it was returned. Please add any comments about the changes made.
                    </div>
                @else
                <p class="mb-3">Are you sure you want to submit this matrix for approval?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> Once submitted, you will not be able to make further changes to this matrix unless it's returned.
                </div>
                @endif
                
                @if(can_division_head_edit($matrix))
                    <div class="mb-3">
                        <label for="hodComment" class="form-label">
                            <strong>Comments (Optional):</strong>
                        </label>
                        <textarea class="form-control" id="hodComment" name="hod_comment" rows="3" 
                                  placeholder="Add any comments about the changes made to the matrix..."></textarea>
                    </div>
                @elseif($matrix->overall_status === 'returned')
                    <div class="mb-3">
                        <label for="focalPersonComment" class="form-label">
                            <strong>Comments (Optional):</strong>
                        </label>
                        <textarea class="form-control" id="focalPersonComment" name="focal_person_comment" rows="3" 
                                  placeholder="Add any comments about the changes made to address the return feedback..."></textarea>
                    </div>
                @endif
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>Activities Count:</strong><br>
                        <span class="text-muted" id="activities-count">
                            <i class="bx bx-loader-alt bx-spin"></i> Loading...
                        </span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <strong>Total Budget:</strong><br>
                        <span class="text-primary fw-bold fs-6" id="total-budget">
                            <i class="bx bx-loader-alt bx-spin"></i> Loading...
                        </span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                @if(can_division_head_edit($matrix))
                    <button type="button" class="btn btn-success" id="submitWithCommentBtn">
                        <i class="fa fa-envelope"></i> Yes, Resubmit Matrix
                    </button>
                @elseif($matrix->overall_status === 'returned')
                    <button type="button" class="btn btn-success" id="submitWithFocalCommentBtn">
                        <i class="fa fa-envelope"></i> Yes, Submit Matrix
                    </button>
                @else
                <a href="{{ route('matrices.request_approval', $matrix) }}" class="btn btn-success">
                        <i class="fa fa-envelope"></i> Yes, Submit Matrix
                </a>
                @endif
            </div>
        </div>
    </div>
</div>



<!-- Approve Selected Activities Confirmation Modal -->
<div class="modal fade" id="approveSelectedModal" tabindex="-1" aria-labelledby="approveSelectedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title text-white" id="approveSelectedModalLabel">
                    <i class="bx bx-check me-2"></i> Pass Selected Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to pass the selected activities?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This action will mark all selected activities as passed.
                </div>
                <div id="selectedActivitiesList" class="mt-3">
                    <!-- Selected activities will be listed here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form action="{{ route('matrices.activities.batch.status') }}" method="POST" id="approveSelectedForm">
                    @csrf
                    <input type="hidden" name="matrix_id" value="{{ $matrix->id }}">
                    <input type="hidden" name="action" value="passed">
                    <input type="hidden" name="activity_ids[]" id="selectedActivityIds">
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check me-1"></i> Yes, Pass Activities
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Selected Activities Confirmation Modal -->
<div class="modal fade" id="rejectSelectedModal" tabindex="-1" aria-labelledby="rejectSelectedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="rejectSelectedModalLabel">
                    <i class="bx bx-x me-2"></i> Don't Pass Selected Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to reject the selected activities?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This action will mark all selected activities as rejected.
                </div>
                <div id="rejectSelectedActivitiesList" class="mt-3">
                    <!-- Selected activities will be listed here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form action="{{ route('matrices.activities.batch.status') }}" method="POST" id="rejectSelectedForm">
                    @csrf
                    <input type="hidden" name="matrix_id" value="{{ $matrix->id }}">
                    <input type="hidden" name="action" value="rejected">
                    <input type="hidden" name="activity_ids[]" id="rejectSelectedActivityIds">
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x me-1"></i> Yes, Reject Activities
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Staff Activities Modal -->
<div class="modal fade" id="staffActivitiesModal" tabindex="-1" aria-labelledby="staffActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #119A48 !important;">
                <h5 class="modal-title text-white" id="staffActivitiesModalLabel">
                    <i class="bx bx-user me-2"></i> Staff Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h4 id="staffNameDisplay" class="mb-2" style="color: #119A48 !important;"></h4>
                    <p class="text-muted">Activity details for {{ $matrix->quarter }} {{ $matrix->year }}</p>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs" id="staffActivitiesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="my-division-tab" data-bs-toggle="tab" data-bs-target="#my-division" type="button" role="tab" aria-controls="my-division" aria-selected="true" style="border-color: #119A48 !important; color: #119A48 !important;">
                            <i class="bx bx-building me-1"></i> My Division
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="other-divisions-tab" data-bs-toggle="tab" data-bs-target="#other-divisions" type="button" role="tab" aria-controls="other-divisions" aria-selected="false">
                            <i class="bx bx-globe me-1"></i> Other Divisions
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content mt-3" id="staffActivitiesTabContent">
                    <!-- My Division Tab -->
                    <div class="tab-pane fade show active" id="my-division" role="tabpanel" aria-labelledby="my-division-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity Title</th>
                                        <th>Focal Person</th>
                                        <th>Division</th>
                                        <th class="text-center">Days</th>
                                    </tr>
                                </thead>
                                <tbody id="myDivisionActivities">
                                    <!-- Content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Other Divisions Tab -->
                    <div class="tab-pane fade" id="other-divisions" role="tabpanel" aria-labelledby="other-divisions-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity Title</th>
                                        <th>Focal Person</th>
                                        <th>Division</th>
                                        <th class="text-center">Days</th>
                                    </tr>
                                </thead>
                                <tbody id="otherDivisionsActivities">
                                    <!-- Content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Activity Confirmation Modal -->
<div class="modal fade" id="deleteActivityModal" tabindex="-1" aria-labelledby="deleteActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="deleteActivityModalLabel">
                    <i class="bx bx-trash me-2"></i> Delete Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this activity?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All data associated with this activity will be permanently deleted.
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong>Activity Title:</strong><br>
                        <span class="text-muted" id="deleteActivityTitle">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form id="deleteActivityForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Yes, Delete Activity
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Single Memo Confirmation Modal -->
<div class="modal fade" id="deleteSingleMemoModal" tabindex="-1" aria-labelledby="deleteSingleMemoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="deleteSingleMemoModalLabel">
                    <i class="bx bx-trash me-2"></i> Delete Single Memo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this single memo?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All data associated with this single memo will be permanently deleted.
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong>Single Memo Title:</strong><br>
                        <span class="text-muted" id="deleteSingleMemoTitle">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form id="deleteSingleMemoForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Yes, Delete Single Memo
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>


#staffActivitiesModal .nav-tabs .nav-link:hover {
    border-color: #119A48 !important;
    color: #119A48 !important;
}

#staffActivitiesModal .nav-tabs {
    border-bottom-color: #119A48 !important;
}
</style>

@push('scripts')
<script>
// Global variables for AJAX functionality
let currentPage = 1;
let isLoading = false;
let currentSearchTerm = '';
let pageSize = 10;

// Single memos AJAX variables
let singleMemoCurrentPage = 1;
let singleMemoIsLoading = false;
let singleMemoCurrentSearchTerm = '';
let singleMemoPageSize = 10;

// Generate activity URL
function getActivityUrl(activityId) {
    return '{{ url("matrices/" . $matrix->id . "/activities") }}/' + activityId;
}

// Check if checkbox column should be shown
function canShowCheckbox() {
    return {{ can_take_action($matrix) && get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft' ? 'true' : 'false' }};
}

// Check if current user is a finance officer
function isLevel5Approver() {
    return {{ is_finance_officer($matrix) ? 'true' : 'false' }};
}

// Check if delete button should be shown
function canShowDeleteButton() {
    return {{ ($matrix->overall_status == 'draft' || $matrix->overall_status == 'returned') ? 'true' : 'false' }};
}

// Load activities via AJAX
function loadActivities(page = 1, search = '', documentNumber = '') {
    if (isLoading) return;
    
    isLoading = true;
    currentPage = page;
    currentSearchTerm = search;
    
    // Show loading indicator
    const loadingElement = document.getElementById('activities-loading');
    const errorElement = document.getElementById('activities-error');
    const tableContainer = document.getElementById('activities-table-container');
    
    if (loadingElement) {
        loadingElement.style.display = 'block';
    }
    if (errorElement) {
        errorElement.style.display = 'none';
    }
    if (tableContainer) {
        tableContainer.style.display = 'none';
    }
    hideSearchStatus();
    
    // Build URL with parameters
    const url = new URL('{{ route("matrices.activities-for-approver", $matrix) }}', window.location.origin);
    if (page > 1) url.searchParams.set('page', page);
    if (search) url.searchParams.set('search', search);
    if (documentNumber) url.searchParams.set('document_number', documentNumber);
    url.searchParams.set('per_page', pageSize);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Hide loading indicator
            const loadingElement = document.getElementById('activities-loading');
            const tableContainer = document.getElementById('activities-table-container');
            
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
            if (tableContainer) {
                tableContainer.style.display = 'block';
            }
            
            // Show search results count if searching
            if (search || documentNumber) {
                const totalResults = data.pagination.total;
                const currentPage = data.pagination.current_page;
                const perPage = data.pagination.per_page;
                const from = data.pagination.from;
                const to = data.pagination.to;
                
                if (totalResults === 0) {
                    showSearchStatus('No results found for your search. Try different keywords.');
                } else {
                    showSearchStatus(`Found ${totalResults} result${totalResults !== 1 ? 's' : ''} (showing ${from}-${to} of ${totalResults})`);
                }
            }
            
            // Render activities
            renderActivities(data.activities.data);
            
            // Render pagination
            renderPagination(data.pagination, data.activities);
            
            // Update showing range
            updateShowingRange(data.pagination);
            
            // Update workflow info display
            updateWorkflowInfo(data.user_workflow_definition);
            
        })
        .catch(error => {
            console.error('Error loading activities:', error);
            
            // Hide loading indicator and show error
            const loadingElement = document.getElementById('activities-loading');
            const errorElement = document.getElementById('activities-error');
            const errorMessageElement = document.getElementById('activities-error-message');
            
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
            if (errorElement) {
                errorElement.style.display = 'block';
            }
            if (errorMessageElement) {
                errorMessageElement.textContent = error.message || 'Failed to load activities. Please try again.';
            }
        })
        .finally(() => {
            isLoading = false;
        });
}

// Render activities in the table
function renderActivities(activities) {
    const tbody = document.getElementById('activities-tbody');
    let html = '';
    
    if (activities.length === 0) {
        html = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    <i class="bx bx-calendar-x fs-1 text-muted mb-3 d-block"></i>
                    <div class="text-muted">No activities found for this matrix.</div>
                    <small class="text-muted">Activities will appear here once they are added.</small>
                </td>
            </tr>
        `;
    } else {
        activities.forEach((activity, index) => {
            const count = ((currentPage - 1) * pageSize) + index + 1;
            const budget = calculateBudget(activity.budget_breakdown);
            const canApprove = activity.can_approve || false;
            const allowPrint = activity.allow_print || false;
            const userHasPassed = activity.user_has_passed || false;
            const status = getActivityStatus(activity);
            
            html += '<tr>';
            
            if (canShowCheckbox() && !isLevel5Approver()) {
                html += '<td class="px-3 py-3">';
                // Only show checkbox if user can approve AND hasn't passed this activity
                if (canApprove && !userHasPassed) {
                    html += `<input type="checkbox" class="form-check-input activity-checkbox" value="${activity.id}" data-activity-title="${activity.activity_title}">`;
                }
                html += '</td>';
            }
            html += '<td class="px-3 py-3">';
            html += `<span class="badge bg-secondary rounded-pill">${count}</span>`;
            html += '</td>';
            
            html += '<td class="px-3 py-3">';
            html += `<span class="badge bg-info text-dark">${activity.document_number || 'N/A'}</span>`;
            html += '</td>';
            
            html += '<td class="px-3 py-3 text-wrap" style="max-width: 250px;">';
            html += activity.activity_title;
            html += '</td>';
            
            html += '<td class="px-3 py-3">';
            html += '<div class="small text-wrap" style="max-width: 120px;">';
            html += `<div class="fw-bold text-primary">${formatDate(activity.date_from)}</div>`;
            html += `<div class="text-muted">to ${formatDate(activity.date_to)}</div>`;
            html += '</div>';
            html += '</td>';
            
            html += '<td class="px-3 py-3">';
            html += '<div class="text-wrap" style="max-width: 120px;">';
            if (activity.responsible_person) {
                html += `<div class="fw-semibold">${activity.responsible_person.fname} ${activity.responsible_person.lname}</div>`;
                html += `<small class="text-muted">${activity.responsible_person.job_name || 'N/A'}</small>`;
            } else {
                html += '<span class="text-muted">Not assigned</span>';
            }
            html += '</div>';
            html += '</td>';
            
            html += '<td class="px-3 py-3 text-center">';
            html += `<span class="badge bg-info rounded-pill">${activity.total_participants}</span>`;
            html += '</td>';
            
            html += '<td class="px-3 py-3 text-center">';
            html += `<span class="badge bg-info rounded-pill">${activity.fund_type ? activity.fund_type.name : 'N/A'}</span>`;
            html += '</td>';
            
            html += '<td class="px-3 py-3 text-center">';
            html += `<div class="budget-display">`;
            html += `<div class="fw-bold text-success">${formatCurrency(budget)} USD</div>`;
            if (activity.available_budget) {
                html += `<div class="budget-available">Available: ${formatCurrency(activity.available_budget)} USD</div>`;
            }
            html += `</div></td>`;
            
            html += '<td class="px-3 py-3 text-center">';
            html += `<span class="badge bg-${status.badgeClass} rounded-pill">${status.text}</span>`;
            // Add indicator if user has passed this activity
            if (userHasPassed && !allowPrint) {
                html += ' <i class="bx bx-check-circle text-success ms-1" title="You have already approved this activity"></i>';
            }
            html += '</td>';
            
            html += '<td class="px-3 py-3 text-center">';
            html += '<div class="btn-group" role="group">';
            html += `<a href="${getActivityUrl(activity.id)}" class="btn btn-outline-primary btn-sm" title="View Activity">`;
            html += '<i class="bx bx-show"></i>';
            html += '</a>';
            
            // Add copy button for draft activities
            if (activity.overall_status === 'draft' && canShowDeleteButton()) {
                html += '<button type="button" class="btn btn-outline-info btn-sm" ';
                html += 'data-bs-toggle="modal" data-bs-target="#copyActivityModal" ';
                html += `data-activity-id="${activity.id}" data-activity-title="${activity.activity_title}" `;
                html += 'title="Copy Activity">';
                html += '<i class="bx bx-copy"></i>';
                html += '</button>';
            }
            
            if (canShowDeleteButton()) {
                html += '<button type="button" class="btn btn-outline-danger btn-sm" ';
                html += 'data-bs-toggle="modal" data-bs-target="#deleteActivityModal" ';
                html += `data-activity-id="${activity.id}" data-activity-title="${activity.activity_title}" `;
                html += 'title="Delete Activity">';
                html += '<i class="bx bx-trash"></i>';
                html += '</button>';
            }
            
            html += '</div>';
            html += '</td>';
            html += '</tr>';
        });
    }
    
    tbody.innerHTML = html;
    
    // Reinitialize checkbox functionality
    initializeCheckboxes();
}

// Calculate budget from breakdown
function calculateBudget(budgetBreakdown) {
    if (!budgetBreakdown) return 0;
    
    const budget = typeof budgetBreakdown === 'string' ? JSON.parse(budgetBreakdown) : budgetBreakdown;
    let totalBudget = 0;
    
    if (Array.isArray(budget)) {
        return totalBudget;
    }
    
    Object.keys(budget).forEach(key => {
        if (key === 'grand_total') return;
        
        const entries = budget[key];
        if (Array.isArray(entries)) {
            entries.forEach(item => {
                const unitCost = parseFloat(item.unit_cost || 0);
                const units = parseFloat(item.units || 0);
                const days = parseFloat(item.days || 1);
                
                if (days > 1) {
                    totalBudget += unitCost * units * days;
                } else {
                    totalBudget += unitCost * units;
                }
            });
        }
    });
    
    return totalBudget;
}

// Get activity status
function getActivityStatus(activity) {
    // Check if matrix is approved - if so, show overall_status instead of trail status
    const matrixStatus = '{{ $matrix->overall_status }}';
    
    if (matrixStatus === 'approved') {
        // When matrix is approved, show the activity's overall_status
        const overallStatus = activity.overall_status || 'pending';
        switch (overallStatus) {
            case 'approved':
                return { text: 'Approved', badgeClass: 'success' };
            case 'pending':
                return { text: 'Pending', badgeClass: 'secondary' };
            case 'returned':
                return { text: 'Returned', badgeClass: 'warning' };
            case 'rejected':
                return { text: 'Rejected', badgeClass: 'danger' };
            default:
                return { text: overallStatus.charAt(0).toUpperCase() + overallStatus.slice(1), badgeClass: 'info' };
        }
    }
    
    // Original logic for non-approved matrices
    if (activity.allow_print) {
        return { text: 'Passed', badgeClass: 'success' };
    } else if (activity.has_passed_at_current_level) {
        // User has passed at current approval level
        return { text: 'Passed', badgeClass: 'success' };
    } else if (activity.my_current_level_action) {
        // Only show actions from current approval level
        const action = activity.my_current_level_action.action;
        if (action === 'approved') {
            return { text: 'Approved', badgeClass: 'success' };
        } else if (action === 'rejected') {
            return { text: 'Rejected', badgeClass: 'danger' };
        } else if (action === 'returned') {
            return { text: 'Returned', badgeClass: 'warning' };
        } else {
            return { text: action.charAt(0).toUpperCase() + action.slice(1), badgeClass: 'info' };
        }
    } else {
        return { text: 'Pending', badgeClass: 'secondary' };
    }
}

// Format date
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Format currency with dollar sign
function formatCurrency(amount) {
    return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
}

// Render pagination
function renderPagination(pagination, activities) {
    const container = document.getElementById('activities-pagination');
    const topContainer = document.getElementById('activities-top-pagination');
    let html = '';
    
    if (pagination.last_page > 1) {
        html += '<nav><ul class="pagination">';
        
        // Get current search values
        const currentGeneralSearch = document.getElementById('general-search').value.trim();
        const currentDocumentSearch = document.getElementById('document-search').value.trim();
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivities(${pagination.current_page - 1}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">Previous</a></li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="loadActivities(${i}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">${i}</a></li>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivities(${pagination.current_page + 1}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">Next</a></li>`;
        }
        
        html += '</ul></nav>';
    }
    
    // Render both top and bottom pagination
    container.innerHTML = html;
    if (topContainer) {
        topContainer.innerHTML = html;
        // Show top pagination if there are multiple pages
        topContainer.style.display = pagination.last_page > 1 ? 'block' : 'none';
    }
}

// Update workflow info display
function updateWorkflowInfo(workflowDefinition) {
    // You can add logic here to display workflow information
    console.log('User workflow definition:', workflowDefinition);
}

// Initialize checkboxes functionality
function initializeCheckboxes() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const activityCheckboxes = document.querySelectorAll('.activity-checkbox');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            activityCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateApproveSection();
        });
    }
    
    activityCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateApproveSection();
        });
    });
    
    updateSelectAllState();
    updateApproveSection();
}

// Update select all checkbox state
function updateSelectAllState() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const activityCheckboxes = document.querySelectorAll('.activity-checkbox');
    const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
    const totalBoxes = activityCheckboxes.length;
    
    if (selectAllCheckbox) {
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === totalBoxes) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
}

    // Update approve section visibility and content
    function updateApproveSection() {
        const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
        const selectedIds = [];
        const selectedTitles = [];

        checkedBoxes.forEach(checkbox => {
            selectedIds.push(checkbox.value);
            selectedTitles.push(checkbox.dataset.activityTitle);
        });

        const approveSelectedSection = document.getElementById('approveSelectedSection');
        const selectedCount = document.getElementById('selectedCount');
        const selectedActivitiesList = document.getElementById('selectedActivitiesList');
        const selectedActivityIds = document.getElementById('selectedActivityIds');
        const rejectSelectedActivitiesList = document.getElementById('rejectSelectedActivitiesList');
        const rejectSelectedActivityIds = document.getElementById('rejectSelectedActivityIds');

        if (selectedIds.length > 0 && !isLevel5Approver()) {
            if (approveSelectedSection) approveSelectedSection.style.display = 'block';
            if (selectedCount) selectedCount.textContent = `${selectedIds.length} activities selected`;
            if (selectedActivityIds) selectedActivityIds.value = selectedIds.join(',');
            if (rejectSelectedActivityIds) rejectSelectedActivityIds.value = selectedIds.join(',');
            
            // Update both modal contents
            updateModalContent(selectedActivitiesList, selectedTitles);
            updateModalContent(rejectSelectedActivitiesList, selectedTitles);
        } else {
            if (approveSelectedSection) approveSelectedSection.style.display = 'none';
            if (selectedCount) selectedCount.textContent = '0 activities selected';
            if (selectedActivityIds) selectedActivityIds.value = '';
            if (rejectSelectedActivityIds) rejectSelectedActivityIds.value = '';
        }
    }

// Function to update modal content
function updateModalContent(container, titles) {
    if (container) {
        container.innerHTML = '';
        titles.forEach(title => {
            container.innerHTML += `
                <div class="small text-muted">
                    <i class="bx bx-check-circle text-success me-1"></i>
                    ${title}
                </div>
            `;
        });
    }
}

// Handle search functionality
function handleSearch() {
    const generalSearch = document.getElementById('general-search').value.trim();
    const documentSearch = document.getElementById('document-search').value.trim();
    
    // Show search status
    if (generalSearch || documentSearch) {
        showSearchStatus('Searching...');
    }
    
    // Combine search terms
    const searchTerm = generalSearch || documentSearch;
    loadActivities(1, searchTerm, documentSearch);
}

// Show search status
function showSearchStatus(message) {
    const searchStatus = document.getElementById('search-status');
    const searchMessage = document.getElementById('search-message');
    
    if (searchStatus && searchMessage) {
        searchMessage.textContent = message;
        searchStatus.style.display = 'block';
    }
}

// Hide search status
function hideSearchStatus() {
    const searchStatus = document.getElementById('search-status');
    if (searchStatus) {
        searchStatus.style.display = 'none';
    }
}

// Clear search functionality
function clearSearch() {
    document.getElementById('general-search').value = '';
    document.getElementById('document-search').value = '';
    hideSearchStatus();
    loadActivities(1, '', '');
}

// Update showing range
function updateShowingRange(pagination) {
    const start = pagination.total > 0 ? pagination.from : 0;
    const end = pagination.to || 0;
    const total = pagination.total || 0;
    document.getElementById('showingRange').textContent = `Showing ${start}-${end} of ${total} activities`;
}

// ===== SINGLE MEMOS AJAX FUNCTIONS =====

// Generate single memo URL
function getSingleMemoUrl(memoId) {
    return '{{ url("single-memos") }}/' + memoId;
}

// Load single memos via AJAX
function loadSingleMemos(page = 1, search = '', documentNumber = '') {
    if (singleMemoIsLoading) return;
    
    singleMemoIsLoading = true;
    singleMemoCurrentPage = page;
    singleMemoCurrentSearchTerm = search;
    
    // Show loading indicator
    const loadingElement = document.getElementById('single-memos-loading');
    const errorElement = document.getElementById('single-memos-error');
    const tableContainer = document.getElementById('single-memos-table-container');
    
    if (loadingElement) {
        loadingElement.style.display = 'block';
    }
    if (errorElement) {
        errorElement.style.display = 'none';
    }
    if (tableContainer) {
        tableContainer.style.display = 'none';
    }
    hideSingleMemoSearchStatus();
    
    // Build URL with parameters
    const url = new URL('{{ route("matrices.single-memos-for-approver", $matrix) }}', window.location.origin);
    if (page > 1) url.searchParams.set('page', page);
    if (search) url.searchParams.set('search', search);
    if (documentNumber) url.searchParams.set('document_number', documentNumber);
    url.searchParams.set('per_page', singleMemoPageSize);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Hide loading indicator
            const loadingElement = document.getElementById('single-memos-loading');
            const tableContainer = document.getElementById('single-memos-table-container');
            
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
            if (tableContainer) {
                tableContainer.style.display = 'block';
            }
            
            // Show search results count if searching
            if (search || documentNumber) {
                const totalResults = data.pagination.total;
                const currentPage = data.pagination.current_page;
                const perPage = data.pagination.per_page;
                const from = data.pagination.from;
                const to = data.pagination.to;
                
                if (totalResults === 0) {
                    showSingleMemoSearchStatus('No results found for your search. Try different keywords.');
                } else {
                    showSingleMemoSearchStatus(`Found ${totalResults} result${totalResults !== 1 ? 's' : ''} (showing ${from}-${to} of ${totalResults})`);
                }
            }
            
            // Render single memos
            renderSingleMemos(data.single_memos.data);
            
            // Render pagination
            renderSingleMemoPagination(data.pagination, data.single_memos.data);
            
            // Update showing range
            updateSingleMemoShowingRange(data.pagination);
            
            // Update count
            document.getElementById('single-memos-count').textContent = data.pagination.total;
            
        })
        .catch(error => {
            console.error('Error loading single memos:', error);
            
            // Hide loading indicator and show error
            const loadingElement = document.getElementById('single-memos-loading');
            const errorElement = document.getElementById('single-memos-error');
            const errorMessageElement = document.getElementById('single-memos-error-message');
            
            if (loadingElement) {
                loadingElement.style.display = 'none';
            }
            if (errorElement) {
                errorElement.style.display = 'block';
            }
            if (errorMessageElement) {
                errorMessageElement.textContent = error.message || 'Failed to load single memos. Please try again.';
            }
        })
        .finally(() => {
            singleMemoIsLoading = false;
        });
}

// Render single memos
function renderSingleMemos(singleMemos) {
    const tbody = document.getElementById('single-memos-tbody');
    let html = '';
    
    if (singleMemos.length === 0) {
        html = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <i class="bx bx-file-text fs-1 text-muted mb-3 d-block"></i>
                    <div class="text-muted">No single memos found for this matrix.</div>
                    <small class="text-muted">Single memos will appear here once they are added.</small>
                </td>
            </tr>
        `;
    } else {
        singleMemos.forEach((memo, index) => {
            const count = ((singleMemoCurrentPage - 1) * singleMemoPageSize) + index + 1;
            const budget = calculateBudget(memo.budget_breakdown);
            const status = getSingleMemoStatus(memo);
            const rowColor = memo.overall_status !== 'approved' ? '#fff3cd' : '#d5f5de';
            
            html += `
                <tr style="background-color: ${rowColor};">
                    <td class="px-3 py-3 text-center">
                        <span class="badge bg-secondary rounded-pill">${count}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="badge bg-info text-dark">${memo.document_number || 'N/A'}</span>
                    </td>
                    <td class="px-3 py-3 text-wrap" style="max-width: 250px;">
                        <div class="fw-semibold text-dark">${memo.activity_title}</div>
                        <small class="text-muted">
                            <i class="bx bx-user me-1"></i>
                            ${memo.responsible_person ? memo.responsible_person.fname + ' ' + memo.responsible_person.lname : 'N/A'}
                        </small>
                    </td>
                    <td class="px-3 py-3">
                        <div class="small text-wrap" style="max-width: 120px;">
                            <div class="fw-bold text-primary">${formatDate(memo.date_from)}</div>
                            <div class="text-muted">to ${formatDate(memo.date_to)}</div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <div class="text-wrap" style="max-width: 120px;">
                            ${memo.responsible_person ? `
                                <div class="fw-semibold">${memo.responsible_person.fname} ${memo.responsible_person.lname}</div>
                                <small class="text-muted">${memo.responsible_person.job_name || 'N/A'}</small>
                            ` : '<span class="text-muted">Not assigned</span>'}
                        </div>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="badge bg-info rounded-pill">${memo.total_participants || 0}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="badge bg-info rounded-pill">${memo.fund_type ? memo.fund_type.name : 'N/A'}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <div class="budget-display">
                            <div class="fw-bold text-success">${formatCurrency(budget)} USD</div>
                            ${memo.available_budget ? `
                                <div class="budget-available">Available: ${formatCurrency(memo.available_budget)} USD</div>
                            ` : ''}
                        </div>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <span class="badge bg-${status.badgeClass} rounded-pill">${status.text}</span>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <div class="btn-group" role="group">
                            <a href="${getSingleMemoUrl(memo.id)}" class="btn btn-outline-primary btn-sm" title="View Single Memo">
                                <i class="bx bx-show"></i>
                            </a>
                            ${canShowSingleMemoDeleteButton(memo) ? `
                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#deleteSingleMemoModal" 
                                        data-memo-id="${memo.id}"
                                        data-memo-title="${memo.activity_title}"
                                        title="Delete Single Memo">
                                    <i class="bx bx-trash"></i>
                                </button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });
    }
    
    tbody.innerHTML = html;
}

// Get single memo status
function getSingleMemoStatus(memo) {
    // Use overall_status directly for single memos
    const status = memo.overall_status || 'pending';
    
    switch (status.toLowerCase()) {
        case 'approved':
        case 'passed':
            return { text: 'Approved', badgeClass: 'success' };
        case 'rejected':
            return { text: 'Rejected', badgeClass: 'danger' };
        case 'returned':
            return { text: 'Returned', badgeClass: 'warning' };
        case 'draft':
            return { text: 'Draft', badgeClass: 'secondary' };
        case 'pending':
        default:
            return { text: 'Pending', badgeClass: 'info' };
    }
}

// Check if delete button should be shown for single memos
function canShowSingleMemoDeleteButton(memo) {
    const currentUserId = {{ user_session('staff_id') ?? 'null' }};
    const matrixDivisionHead = {{ $matrix->division->division_head ?? 'null' }};
    const matrixFocalPerson = {{ $matrix->staff_id ?? 'null' }};
    
    // Check if single memo is in draft status
    if (!memo || memo.overall_status !== 'draft') {
        return false;
    }
    
    // Check if user is the responsible person, staff member, division head, or focal person
    if (memo.responsible_person_id == currentUserId || 
        memo.staff_id == currentUserId || 
        matrixDivisionHead == currentUserId ||
        matrixFocalPerson == currentUserId) {
        return true;
    }
    
    return false;
}

// Render single memo pagination
function renderSingleMemoPagination(pagination, singleMemos) {
    const container = document.getElementById('single-memos-pagination');
    const topContainer = document.getElementById('single-memos-top-pagination');
    let html = '';
    
    if (pagination.last_page > 1) {
        html += '<div class="d-flex justify-content-center"><ul class="pagination">';
        
        // Get current search values
        const currentGeneralSearch = document.getElementById('single-memo-general-search').value.trim();
        const currentDocumentSearch = document.getElementById('single-memo-document-search').value.trim();
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSingleMemos(${pagination.current_page - 1}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">Previous</a></li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.last_page, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="loadSingleMemos(${i}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">${i}</a></li>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.last_page) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadSingleMemos(${pagination.current_page + 1}, '${currentGeneralSearch}', '${currentDocumentSearch}'); return false;">Next</a></li>`;
        }
        
        html += '</ul></div>';
    }
    
    // Render both top and bottom pagination
    container.innerHTML = html;
    if (topContainer) {
        topContainer.innerHTML = html;
        // Show top pagination if there are multiple pages
        topContainer.style.display = pagination.last_page > 1 ? 'block' : 'none';
    }
}

// Update single memo showing range
function updateSingleMemoShowingRange(pagination) {
    const start = pagination.total > 0 ? pagination.from : 0;
    const end = pagination.to || 0;
    const total = pagination.total || 0;
    document.getElementById('single-memo-showingRange').textContent = `Showing ${start}-${end} of ${total} single memos`;
}

// Single memo search functionality
function handleSingleMemoSearch() {
    const generalSearch = document.getElementById('single-memo-general-search').value.trim();
    const documentSearch = document.getElementById('single-memo-document-search').value.trim();
    
    // Show search status
    if (generalSearch || documentSearch) {
        showSingleMemoSearchStatus('Searching...');
    }
    
    // Combine search terms
    const searchTerm = generalSearch || documentSearch;
    loadSingleMemos(1, searchTerm, documentSearch);
}

// Show single memo search status
function showSingleMemoSearchStatus(message) {
    const searchStatus = document.getElementById('single-memo-search-status');
    const searchMessage = document.getElementById('single-memo-search-message');
    
    if (searchStatus && searchMessage) {
        searchMessage.textContent = message;
        searchStatus.style.display = 'block';
    }
}

// Hide single memo search status
function hideSingleMemoSearchStatus() {
    const searchStatus = document.getElementById('single-memo-search-status');
    if (searchStatus) {
        searchStatus.style.display = 'none';
    }
}

// Clear single memo search functionality
function clearSingleMemoSearch() {
    document.getElementById('single-memo-general-search').value = '';
    document.getElementById('single-memo-document-search').value = '';
    hideSingleMemoSearchStatus();
    loadSingleMemos(1, '', '');
}

// Load matrix budget information
function loadMatrixBudgets() {
    fetch(`{{ route('matrices.budgets', $matrix->id) }}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update intramural budget
                const intramuralElement = document.getElementById('intramural-budget');
                if (intramuralElement) {
                    intramuralElement.innerHTML = formatCurrency(data.data.intramural_budget);
                }
                
                // Update extramural budget
                const extramuralElement = document.getElementById('extramural-budget');
                if (extramuralElement) {
                    extramuralElement.innerHTML = formatCurrency(data.data.extramural_budget);
                }
                
                // Update total budget
                const totalBudgetElement = document.getElementById('total-budget');
                if (totalBudgetElement) {
                    totalBudgetElement.innerHTML = formatCurrency(data.data.total_budget);
                }
                
                // Update activities count
                const activitiesCountElement = document.getElementById('activities-count');
                if (activitiesCountElement) {
                    activitiesCountElement.innerHTML = data.data.activities_count + ' activities';
                }
            } else {
                console.error('Error loading matrix budgets:', data.message);
                // Show error state
                const elements = ['intramural-budget', 'extramural-budget', 'total-budget', 'activities-count'];
                elements.forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.innerHTML = '<span class="text-danger">Error loading</span>';
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading matrix budgets:', error);
            // Show error state
            const elements = ['intramural-budget', 'extramural-budget', 'total-budget', 'activities-count'];
            elements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<span class="text-danger">Error loading</span>';
                }
            });
        });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load activities on page load
    loadActivities(1, '{{ request("search") }}', '{{ request("document_number") }}');
    
    // Load single memos on page load
    loadSingleMemos(1, '{{ request("single_memo_search") }}', '{{ request("single_memo_document_number") }}');
    
    // Add event listeners for search functionality
    const searchBtn = document.getElementById('search-btn');
    const clearBtn = document.getElementById('clear-search-btn');
    const generalSearch = document.getElementById('general-search');
    const documentSearch = document.getElementById('document-search');
    const pageSizeSelect = document.getElementById('pageSizeSelect');
    
    // Single memo search elements
    const singleMemoSearchBtn = document.getElementById('single-memo-search-btn');
    const singleMemoClearBtn = document.getElementById('single-memo-clear-search-btn');
    const singleMemoGeneralSearch = document.getElementById('single-memo-general-search');
    const singleMemoDocumentSearch = document.getElementById('single-memo-document-search');
    const singleMemoPageSizeSelect = document.getElementById('single-memo-pageSizeSelect');
    
    if (searchBtn) {
        searchBtn.addEventListener('click', handleSearch);
    }
    
    if (clearBtn) {
        clearBtn.addEventListener('click', clearSearch);
    }
    
    // Add Enter key support for search inputs
    if (generalSearch) {
        generalSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });
    }
    
    if (documentSearch) {
        documentSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleSearch();
            }
        });
    }
    
    // Add real-time search with debouncing
    let searchTimeout;
    if (generalSearch) {
        generalSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                handleSearch();
            }, 500); // 500ms delay
        });
    }
    
    if (documentSearch) {
        documentSearch.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                handleSearch();
            }, 500); // 500ms delay
        });
    }
    
    // Page size change
    if (pageSizeSelect) {
        pageSizeSelect.addEventListener('change', function() {
            pageSize = parseInt(this.value);
            loadActivities(1, currentSearchTerm, document.getElementById('document-search').value);
        });
    }
    
    // Single memo search event listeners
    if (singleMemoSearchBtn) {
        singleMemoSearchBtn.addEventListener('click', handleSingleMemoSearch);
    }
    
    if (singleMemoClearBtn) {
        singleMemoClearBtn.addEventListener('click', clearSingleMemoSearch);
    }
    
    // Add Enter key support for single memo search inputs
    if (singleMemoGeneralSearch) {
        singleMemoGeneralSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleSingleMemoSearch();
            }
        });
    }
    
    if (singleMemoDocumentSearch) {
        singleMemoDocumentSearch.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleSingleMemoSearch();
            }
        });
    }
    
    // Add real-time search with debouncing for single memos
    let singleMemoSearchTimeout;
    if (singleMemoGeneralSearch) {
        singleMemoGeneralSearch.addEventListener('input', function() {
            clearTimeout(singleMemoSearchTimeout);
            singleMemoSearchTimeout = setTimeout(() => {
                handleSingleMemoSearch();
            }, 500); // 500ms delay
        });
    }
    
    if (singleMemoDocumentSearch) {
        singleMemoDocumentSearch.addEventListener('input', function() {
            clearTimeout(singleMemoSearchTimeout);
            singleMemoSearchTimeout = setTimeout(() => {
                handleSingleMemoSearch();
            }, 500); // 500ms delay
        });
    }
    
    // Single memo page size change
    if (singleMemoPageSizeSelect) {
        singleMemoPageSizeSelect.addEventListener('change', function() {
            singleMemoPageSize = parseInt(this.value);
            loadSingleMemos(1, singleMemoCurrentSearchTerm, document.getElementById('single-memo-document-search').value);
        });
    }
    
    // Load matrix budgets after all other initialization is complete
    setTimeout(() => {
        loadMatrixBudgets();
    }, 500);
});

document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const activityCheckboxes = document.querySelectorAll('.activity-checkbox');
    const approveSelectedSection = document.getElementById('approveSelectedSection');
    
    // Copy Activity Modal functionality
    const copyActivityModal = document.getElementById('copyActivityModal');
    if (copyActivityModal) {
        copyActivityModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const activityId = button.getAttribute('data-activity-id');
            const activityTitle = button.getAttribute('data-activity-title');
            
            // Update modal content
            const modalTitle = copyActivityModal.querySelector('#copy-activity-title');
            if (modalTitle) {
                modalTitle.textContent = activityTitle;
            }
            
            // Set up confirm button
            const confirmButton = copyActivityModal.querySelector('#confirm-copy-activity');
            if (confirmButton) {
                confirmButton.onclick = function() {
                    // Redirect to copy URL
                    const copyUrl = '{{ url("matrices/" . $matrix->id . "/activities") }}/' + activityId + '/copy';
                    window.location.href = copyUrl;
                };
            }
        });
    }
    const selectedCount = document.getElementById('selectedCount');
    const selectedActivitiesList = document.getElementById('selectedActivitiesList');
    const selectedActivityIds = document.getElementById('selectedActivityIds');
    const rejectSelectedActivitiesList = document.getElementById('rejectSelectedActivitiesList');
    const rejectSelectedActivityIds = document.getElementById('rejectSelectedActivityIds');

    // Select All functionality
    if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        activityCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateApproveSection();
    });
    }

    // Individual checkbox functionality
    activityCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateApproveSection();
        });
    });

    // Update select all checkbox state
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
        const totalBoxes = activityCheckboxes.length;
        
        if (selectAllCheckbox) {
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === totalBoxes) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
            }
        }
    }

    // Update approve section visibility and content
    function updateApproveSection() {
        const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
        const selectedIds = [];
        const selectedTitles = [];

        checkedBoxes.forEach(checkbox => {
            selectedIds.push(checkbox.value);
            selectedTitles.push(checkbox.dataset.activityTitle);
        });

        if (selectedIds.length > 0 && !isLevel5Approver()) {
            approveSelectedSection.style.display = 'block';
            selectedCount.textContent = `${selectedIds.length} activities selected`;
            selectedActivityIds.value = selectedIds.join(',');
            rejectSelectedActivityIds.value = selectedIds.join(',');
            
            // Update both modal contents
            updateModalContent(selectedActivitiesList, selectedTitles);
            updateModalContent(rejectSelectedActivitiesList, selectedTitles);
        } else {
            approveSelectedSection.style.display = 'none';
            selectedCount.textContent = '0 activities selected';
            selectedActivityIds.value = '';
            rejectSelectedActivityIds.value = '';
        }
    }

    // Function to update modal content
    function updateModalContent(container, titles) {
        container.innerHTML = '';
        titles.forEach(title => {
            container.innerHTML += `
                <div class="small text-muted">
                    <i class="bx bx-check-circle text-success me-1"></i>
                    ${title}
                </div>
            `;
        });
    }

    // Initialize the state
    updateSelectAllState();
    updateApproveSection();
});

// Staff Activities Modal Function
function showStaffActivities(staffId, staffName) {
    // Set staff name in modal
    document.getElementById('staffNameDisplay').textContent = staffName;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('staffActivitiesModal'));
    modal.show();
    
    // Load activities data
    loadStaffActivities(staffId);
}

function loadStaffActivities(staffId) {
    // Show loading state
    document.getElementById('myDivisionActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="bx bx-loader-alt bx-spin me-2"></i>Loading...</td></tr>';
    document.getElementById('otherDivisionsActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="bx bx-loader-alt bx-spin me-2"></i>Loading...</td></tr>';
    
    const url = `${window.location.origin}/staff/apm/staff/${staffId}/activities?matrix_id={{ $matrix->id }}`;
    console.log('Fetching from URL:', url);
    
    // Fetch activities data via AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Populate My Division tab
            populateActivitiesTable('myDivisionActivities', data.my_division || []);
            
            // Populate Other Divisions tab
            populateActivitiesTable('otherDivisionsActivities', data.other_divisions || []);
        })
        .catch(error => {
            console.error('Error loading staff activities:', error);
            document.getElementById('myDivisionActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading data</td></tr>';
            document.getElementById('otherDivisionsActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading data</td></tr>';
        });
}

function populateActivitiesTable(tableId, activities) {
    const tbody = document.getElementById(tableId);
    
    if (activities.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No activities found</td></tr>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        html += `
            <tr>
                <td class="fw-semibold">${activity.activity_title || 'N/A'}</td>
                <td>${activity.focal_person || 'N/A'}</td>
                <td>${activity.division_name || 'N/A'}</td>
                <td class="text-center">
                    <span class="badge bg-primary rounded-pill">${activity.days || 0}</span>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// Delete Activity Modal Function
document.addEventListener('DOMContentLoaded', function() {
    const deleteActivityModal = document.getElementById('deleteActivityModal');
    if (deleteActivityModal) {
        deleteActivityModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const activityId = button.getAttribute('data-activity-id');
            const activityTitle = button.getAttribute('data-activity-title');
            
            // Update modal content
            document.getElementById('deleteActivityTitle').textContent = activityTitle;
            
            // Update form action
            const form = document.getElementById('deleteActivityForm');
            form.action = `{{ url('matrices/' . $matrix->id . '/activities') }}/${activityId}`;
        });
    }

    // Delete Single Memo Modal Function
    const deleteSingleMemoModal = document.getElementById('deleteSingleMemoModal');
    if (deleteSingleMemoModal) {
        deleteSingleMemoModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const memoId = button.getAttribute('data-memo-id');
            const memoTitle = button.getAttribute('data-memo-title');
            
            // Update modal content
            document.getElementById('deleteSingleMemoTitle').textContent = memoTitle;
            
            // Update form action
            const form = document.getElementById('deleteSingleMemoForm');
            form.action = `{{ url('single-memos') }}/${memoId}`;
        });
    }

    // Handle HOD submission with comment
    const submitWithCommentBtn = document.getElementById('submitWithCommentBtn');
    if (submitWithCommentBtn) {
        submitWithCommentBtn.addEventListener('click', function() {
            const comment = document.getElementById('hodComment').value;
            const matrixId = {{ $matrix->id }};
            
            // Create a form to submit with comment
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ route('matrices.request_approval', $matrix) }}`;
            
            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            // Add comment if provided
            if (comment.trim()) {
                const commentInput = document.createElement('input');
                commentInput.type = 'hidden';
                commentInput.name = 'hod_comment';
                commentInput.value = comment.trim();
                form.appendChild(commentInput);
            }
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Handle focal person submission with comment (when matrix is returned)
    const submitWithFocalCommentBtn = document.getElementById('submitWithFocalCommentBtn');
    if (submitWithFocalCommentBtn) {
        submitWithFocalCommentBtn.addEventListener('click', function() {
            const comment = document.getElementById('focalPersonComment').value;
            const matrixId = {{ $matrix->id }};
            
            // Create a form to submit with comment
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ route('matrices.request_approval', $matrix) }}`;
            
            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            // Add comment if provided
            if (comment.trim()) {
                const commentInput = document.createElement('input');
                commentInput.type = 'hidden';
                commentInput.name = 'focal_person_comment';
                commentInput.value = comment.trim();
                form.appendChild(commentInput);
            }
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
        });
    }
});
</script>

<style>
/* Search enhancements */
#search-status {
    border-left: 4px solid #0d6efd;
}

/* Budget display enhancements */
.budget-display {
    line-height: 1.2;
}

.budget-available {
    font-size: 0.85em;
    color: #6c757d;
    margin-top: 2px;
}

.search-input-group .input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.search-input-group .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Search button hover effects */
#search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#clear-search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Page size selector styling */
#pageSizeSelect {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: all 0.15s ease-in-out;
}

#pageSizeSelect:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

#showingRange {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Budget styling for total budget in matrix information */
#total-budget {
    background: linear-gradient(135deg, #f9f0ff 0%, #efdbff 100%);
    padding: 0.4rem 0.6rem;
    border-radius: 0.4rem;
    border: 1px solid #722ed1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    display: inline-block;
    font-size: 0.9rem;
    color: #531dab !important;
}

#total-budget:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
</style>

@endpush

<!-- Copy Activity Modal -->
<div class="modal fade" id="copyActivityModal" tabindex="-1" aria-labelledby="copyActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyActivityModalLabel">
                    <i class="bx bx-copy me-2"></i>Copy Activity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to copy this activity?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This will create a copy of the activity with "(Copy)" added to the title. The copied activity will be in draft status and you can edit it as needed.
                </div>
                <div class="mt-3">
                    <strong>Activity Title:</strong><br>
                    <span id="copy-activity-title" class="text-muted"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-info" id="confirm-copy-activity">
                    <i class="bx bx-copy me-1"></i> Copy Activity
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
