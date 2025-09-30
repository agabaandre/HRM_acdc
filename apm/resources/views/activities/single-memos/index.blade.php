@extends('layouts.app')

@section('title', 'Single Memos')

@section('header', 'Single Memos')

@section('header-actions')

@endsection

@section('content')
<style>
    .table-title-cell {
        max-width: 300px;
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
        line-height: 1.4;
    }
    
    .table {
        table-layout: fixed;
    }
    
    /* Both tabs now use the same 9-column layout */
    .table th:nth-child(1) { width: 3%; }   /* # - reduced from 5% */
    .table th:nth-child(2) { width: 16%; }  /* Document # - increased by 4% from 12% */
    .table th:nth-child(3) { width: 20%; }  /* Title */
    .table th:nth-child(4) { width: 12%; }  /* Responsible Person */
    .table th:nth-child(5) { width: 15%; }  /* Division - with text wrapping */
    .table th:nth-child(6) { width: 7%; }   /* Date Range - reduced by 5% from 12% */
    .table th:nth-child(7) { width: 10%; }  /* Fund Type */
    .table th:nth-child(8) { width: 8%; }   /* Status */
    .table th:nth-child(9) { width: 11%; }  /* Actions - increased by 5% from 6% */
    
    .table td:nth-child(4), .table td:nth-child(5) { 
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
        line-height: 1.3;
    }
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-doc me-2 text-success"></i> Single Memo Management</h4>
                    </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <form action="{{ route('activities.single-memos.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Enter document number">
                </div>
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff/Responsible Person
                    </label>
                    <select name="staff_id" id="staff_id" class="form-select select2" style="width: 100%;">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->staff_id }}" {{ request('staff_id') == $member->staff_id ? 'selected' : '' }}>
                                {{ $member->fname }} {{ $member->lname }}
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
                            <option value="{{ $division->division_id }}" {{ request('division_id') == $division->division_id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold mb-1">
                        <i class="bx bx-info-circle me-1 text-success"></i> Status
                    </label>
                    <select name="status" id="statusFilter" class="form-select select2" style="width: 100%;">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
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
        <ul class="nav nav-tabs nav-fill" id="memoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-doc me-2"></i> My Division Single Memos
                    <span class="badge bg-success text-white ms-2">{{ $myMemos->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                <button class="nav-link" id="allMemos-tab" data-bs-toggle="tab" data-bs-target="#allMemos" type="button" role="tab" aria-controls="allMemos" aria-selected="false">
                    <i class="bx bx-grid me-2"></i> All Single Memos
                    <span class="badge bg-primary text-white ms-2">{{ $allMemos->total() }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                <button class="nav-link" id="sharedMemos-tab" data-bs-toggle="tab" data-bs-target="#sharedMemos" type="button" role="tab" aria-controls="sharedMemos" aria-selected="false">
                    <i class="bx bx-share me-2"></i> Shared Single Memos
                    <span class="badge bg-info text-white ms-2">{{ $sharedMemos->total() }}</span>
                    </button>
                </li>
            </ul>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif
        
        <!-- Tab Content -->
        <div class="tab-content" id="memoTabsContent">
            <!-- My Single Memos Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-doc me-2"></i> My Division Single Memos
                            </h6>
                            <small class="text-muted">Single memos from your division, sorted by most recent quarter and year</small>
                        </div>
                    </div>
                    
                    @php 
                        // $myMemos is already provided by the controller
                    @endphp
                    
                    @if($myMemos->count() > 0)
                <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                            <tr>
                                <th>#</th>
                                        <th>Document #</th>
                                <th>Title</th>
                                <th>Responsible Person</th>
                                <th>Division</th>
                                <th>Date Range</th>
                                        <th>Fund Type</th>
                                <th>Status</th>
                                        <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($myMemos as $memo)
                                <tr>
                                    <td>{{ $count++ }}</td>
                                    <td>
                                                <span class="badge bg-primary text-white">
                                                    <i class="bx bx-hash me-1"></i>
                                                    {{ $memo->document_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                            <td class="table-title-cell">
                                                <div class="fw-bold text-primary">{!! $memo->activity_title !!}</div>
                                                <small class="text-muted">{{ Str::limit(strip_tags($memo->background), 50) }}</small>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $memo->responsiblePerson->fname ?? 'N/A' }} {{ $memo->responsiblePerson->lname ?? '' }}</div>
                                                <small class="text-muted">{{ $memo->responsiblePerson->email ?? 'N/A' }}</small>
                                            </td>
                                            <td>{{ $memo->matrix->division->division_name ?? 'N/A' }}</td>
                                    <td>
                                        <small>
                                            {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                            <span class="text-muted">to</span><br>
                                            {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bx bx-money me-1"></i>
                                                    {{ $memo->fundType->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                                @php
                                                    $statusBadgeClass = [
                                                        'draft' => 'bg-secondary',
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                        'returned' => 'bg-info',
                                                    ];
                                                    $statusClass = $statusBadgeClass[$memo->overall_status] ?? 'bg-secondary';
                                                    
                                                    // Get workflow information
                                                    $approvalLevel = $memo->approval_level ?? 'N/A';
                                                    $workflowRole = $memo->workflow_definition ? ($memo->workflow_definition->role ?? 'N/A') : 'N/A';
                                                    $actorName = $memo->current_actor ? ($memo->current_actor->fname . ' ' . $memo->current_actor->lname) : 'N/A';
                                                @endphp
                                                
                                                @if($memo->overall_status === 'pending')
                                                    <!-- Structured display for pending status -->
                                                    <div class="text-start">
                                                        <span class="badge {{ $statusClass }} mb-1">
                                                            {{ strtoupper($memo->overall_status) }}
                                                        </span>
                                                        <br>
                                                        <small class="text-muted d-block">{{ $workflowRole }}</small>
                                                        @if($actorName !== 'N/A')
                                                            <small class="text-muted d-block">{{ $actorName }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <!-- Standard badge for other statuses -->
                                                    <span class="badge {{ $statusClass }}">
                                                        {{ strtoupper($memo->overall_status ?? 'draft') }}
                                                    </span>
                                                @endif
                                    </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            @if($memo->overall_status === 'draft')
                                                <a href="{{ route('activities.single-memos.edit', [$memo, $memo->matrix]) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                    @endif
                                                    @if($memo->overall_status === 'approved')
                                                        <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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
                        @if($myMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $myMemos->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $myMemos->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-file-doc fs-1 text-success opacity-50"></i>
                            <p class="mb-0">No single memos found.</p>
                            <small>Your single memos will appear here once they are created.</small>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- All Single Memos Tab -->
            <div class="tab-pane fade" id="allMemos" role="tabpanel" aria-labelledby="allMemos-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-grid me-2"></i> All Single Memos
                            </h6>
                            <small class="text-muted">All single memos in the system</small>
                        </div>
                    </div>
                    
                    @if($allMemos->count() > 0)
                <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                            <tr>
                                <th>#</th>
                                        <th>Document #</th>
                                <th>Title</th>
                                <th>Responsible Person</th>
                                <th>Division</th>
                                <th>Date Range</th>
                                        <th>Fund Type</th>
                                <th>Status</th>
                                        <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $count = 1; @endphp
                                    @foreach($allMemos as $memo)
                                <tr>
                                    <td>{{ $count++ }}</td>
                                    <td>
                                                <span class="badge bg-primary text-white">
                                                    <i class="bx bx-hash me-1"></i>
                                                    {{ $memo->document_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="table-title-cell">
                                                <div class="fw-bold text-primary">{!! $memo->activity_title !!}</div>
                                                <small class="text-muted">{{ Str::limit(strip_tags($memo->background), 50) }}</small>
                                    </td>
                                            <td>
                                                @if($memo->responsiblePerson)
                                                    <div class="fw-bold text-primary">{{ $memo->responsiblePerson->fname }} {{ $memo->responsiblePerson->lname }}</div>
                                                    <small class="text-muted">Responsible Person</small>
                                                @elseif($memo->staff)
                                                    <div class="fw-bold text-secondary">{{ $memo->staff->fname }} {{ $memo->staff->lname }}</div>
                                                    <small class="text-muted">Creator</small>
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                            <td>{{ $memo->matrix->division->division_name ?? 'N/A' }}</td>
                                    <td>
                                        <small>
                                            {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                            <span class="text-muted">to</span><br>
                                            {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bx bx-money me-1"></i>
                                                    {{ $memo->fundType->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                                @php
                                                    $statusBadgeClass = [
                                                        'draft' => 'bg-secondary',
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                        'returned' => 'bg-info',
                                                    ];
                                                    $statusClass = $statusBadgeClass[$memo->overall_status] ?? 'bg-secondary';
                                                    
                                                    // Get workflow information
                                                    $approvalLevel = $memo->approval_level ?? 'N/A';
                                                    $workflowRole = $memo->workflow_definition ? ($memo->workflow_definition->role ?? 'N/A') : 'N/A';
                                                    $actorName = $memo->current_actor ? ($memo->current_actor->fname . ' ' . $memo->current_actor->lname) : 'N/A';
                                                @endphp
                                                
                                                @if($memo->overall_status === 'pending')
                                                    <!-- Structured display for pending status -->
                                                    <div class="text-start">
                                                        <span class="badge {{ $statusClass }} mb-1">
                                                            {{ strtoupper($memo->overall_status) }}
                                                        </span>
                                                        <br>
                                                        <small class="text-muted d-block">Level {{ $approvalLevel }}</small>
                                                        <small class="text-muted d-block">{{ $workflowRole }}</small>
                                                        @if($actorName !== 'N/A')
                                                            <small class="text-muted d-block">{{ $actorName }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <!-- Standard badge for other statuses -->
                                                    <span class="badge {{ $statusClass }}">
                                                        {{ strtoupper($memo->overall_status ?? 'draft') }}
                                                    </span>
                                                @endif
                                    </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                                <a href="{{ route('activities.single-memos.edit', $memo,$memo->matrix) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                    @endif
                                                    @if($memo->overall_status === 'approved')
                                                        <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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
                        @if($allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $allMemos->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $allMemos->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-grid fs-1 text-primary opacity-50"></i>
                            <p class="mb-0">No single memos found.</p>
                            <small>Single memos will appear here once they are created.</small>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Shared Single Memos Tab -->
            <div class="tab-pane fade" id="sharedMemos" role="tabpanel" aria-labelledby="sharedMemos-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-share me-2"></i> Shared Single Memos
                            </h6>
                            <small class="text-muted">Single memos from other divisions where you're involved, sorted by most recent quarter and year</small>
                        </div>
                    </div>
                    
                    @if($sharedMemos->count() > 0)
                <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-info">
                            <tr>
                                <th>#</th>
                                        <th>Document #</th>
                                <th>Title</th>
                                <th>Responsible Person</th>
                                <th>Division</th>
                                <th>Date Range</th>
                                        <th>Fund Type</th>
                                <th>Status</th>
                                        <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $count = 1; @endphp
                                    @foreach($sharedMemos as $memo)
                                <tr>
                                    <td>{{ $count++ }}</td>
                                    <td>
                                                <span class="badge bg-primary text-white">
                                                    <i class="bx bx-hash me-1"></i>
                                                    {{ $memo->document_number ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="table-title-cell">
                                                <div class="fw-bold text-primary">{!! $memo->activity_title !!}</div>
                                                <small class="text-muted">{{ Str::limit(strip_tags($memo->background), 50) }}</small>
                                    </td>
                                            <td>
                                                @if($memo->responsiblePerson)
                                                    <div class="fw-bold text-primary">{{ $memo->responsiblePerson->fname }} {{ $memo->responsiblePerson->lname }}</div>
                                                    <small class="text-muted">Responsible Person</small>
                                                @else
                                                    <span class="text-muted">N/A</span>
                                                @endif
                                            </td>
                                            <td>{{ $memo->matrix->division->division_name ?? 'N/A' }}</td>
                                    <td>
                                        <small>
                                            {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                            <span class="text-muted">to</span><br>
                                            {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                                        </small>
                                    </td>
                                    <td>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bx bx-money me-1"></i>
                                                    {{ $memo->fundType->name ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                                @php
                                                    $statusClass = match($memo->overall_status) {
                                                        'draft' => 'bg-secondary',
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'returned' => 'bg-danger',
                                                        'cancelled' => 'bg-dark',
                                                        default => 'bg-secondary'
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }} text-white">
                                                    {{ ucfirst($memo->overall_status) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                                <a href="{{ route('activities.single-memos.edit', $memo,$memo->matrix) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                    @endif
                                                    @if($memo->overall_status === 'approved')
                                                        <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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
                        @if($sharedMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $sharedMemos->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $sharedMemos->appends(request()->query())->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-share fs-1 text-info opacity-50"></i>
                            <p class="mb-0">No shared single memos found.</p>
                            <small>Single memos from other divisions where you're involved will appear here.</small>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Document number filter - submit on Enter key
    if (document.getElementById('document_number')) {
        document.getElementById('document_number').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });
    }
    
    // Auto-submit for select filters
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
    if (document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').addEventListener('change', function() {
            this.form.submit();
        });
    }
});
</script>
@endsection

