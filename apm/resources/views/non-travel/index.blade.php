@extends('layouts.app')

@section('title', 'Non-Travel Memos')

@section('header', 'Non-Travel Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('non-travel.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
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
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> Non-Travel Memo Management</h4>
            </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <form action="{{ route('non-travel.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Doc #" style="width: 100%;">
                </div>
                <div class="col-md-2">
                    <label for="category_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Category
                    </label>
                    <select name="category_id" id="category_id" class="form-select select2" style="width: 100%;">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                 {{-- @php
                    dd($divisions);
                @endphp --}}
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff
                    </label>
                    <select name="staff_id" id="staff_id" class="form-select select2" style="width: 100%;">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
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
                            <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold mb-1">
                        <i class="bx bx-info-circle me-1 text-success"></i> Status
                    </label>
                    <select name="status" id="status" class="form-select select2" style="width: 100%;">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                    </select>
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="submit" class="btn btn-success btn-sm" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-auto d-flex align-items-end">
                    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary btn-sm">
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
                    <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedMemos->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allMemos-tab" data-bs-toggle="tab" data-bs-target="#allMemos" type="button" role="tab" aria-controls="allMemos" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Non-Travel Memos
                        <span class="badge bg-primary text-white ms-2">{{ $allMemos->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="memoTabsContent">
            <!-- My Submitted Memos Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                            </h6>
                            <small class="text-muted">All non-travel memos you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('non-travel.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @if($mySubmittedMemos && $mySubmittedMemos->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th style="width: 5%;">#</th>
                                        <th style="width: 25%;">Title</th>
                                        <th style="width: 10%;">Category</th>
                                        <th style="width: 12%;">Division</th>
                                        <th style="width: 8%;">Fund Type</th>
                                        <th style="width: 10%;">Date</th>
                                        <th style="width: 10%;">Status</th>
                                        <th style="width: 10%;" class="text-center">Actions</th>
                                    </tr>
                </thead>
                <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($mySubmittedMemos as $memo)
                        <tr>
                                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="text-wrap" style="max-width: 250px;">
                                    <div class="fw-bold text-primary">{{ Str::limit($memo->activity_title, 60) }}</div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <i class="bx bx-category me-1"></i>
                                    {{ $memo->nonTravelMemoCategory->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <div class="text-wrap" style="max-width: 150px;">
                                    {{ Str::limit($memo->division->division_name ?? 'N/A', 20) }}
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-warning text-dark">
                                    <i class="bx bx-money me-1"></i>
                                    {{ $memo->fundType->name ?? 'N/A' }}
                                </span>
                            </td>
                                            <td>{{ $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('M d, Y') : 'N/A' }}</td>
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
                                    <a href="{{ route('non-travel.show', $memo) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                        <i class="bx bx-show"></i>
                                                    </a>
                                                    @if(($memo->overall_status == 'draft' || $memo->overall_status == 'returned') && $memo->staff_id == user_session('staff_id'))
                                    <a href="{{ route('non-travel.edit', $memo) }}"
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <form action="{{ route('non-travel.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this memo? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                                    @endif
                                                    @if($memo->overall_status === 'approved')
                                                        <a href="{{ route('non-travel.print', $memo) }}" 
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
                        @if($mySubmittedMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $mySubmittedMemos->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $mySubmittedMemos->appends(request()->query())->links() }}
                                                </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-file-alt fs-1 text-success opacity-50"></i>
                            <p class="mb-0">No submitted memos found.</p>
                            <small>Your submitted non-travel memos will appear here.</small>
                                            </div>
                    @endif
                                            </div>
                                        </div>

            <!-- All Non-Travel Memos Tab -->
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="allMemos" role="tabpanel" aria-labelledby="allMemos-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Non-Travel Memos
                                </h6>
                                <small class="text-muted">All non-travel memos in the system</small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('non-travel.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to Excel
                                </a>
                            </div>
                        </div>
                        
                        @if($allMemos && $allMemos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th style="width: 5%;">#</th>
                                            <th style="width: 20%;">Title</th>
                                            <th style="width: 8%;">Category</th>
                                            <th style="width: 12%;">Responsible Staff</th>
                                            <th style="width: 10%;">Division</th>
                                            <th style="width: 8%;">Fund Type</th>
                                            <th style="width: 8%;">Date</th>
                                            <th style="width: 9%;">Status</th>
                                            <th style="width: 10%;" class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = 1; @endphp
                                        @foreach($allMemos as $memo)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 200px;">
                                                        <div class="fw-bold text-primary">{{ Str::limit($memo->activity_title, 50) }}</div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <i class="bx bx-category me-1"></i>
                                                        {{ $memo->nonTravelMemoCategory->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 120px;">
                                                        @if($memo->staff)
                                                            {{ Str::limit($memo->staff->fname . ' ' . $memo->staff->lname, 15) }}
                                                        @else
                                                            <span class="text-muted">Not assigned</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="text-wrap" style="max-width: 120px;">
                                                        {{ Str::limit($memo->division->division_name ?? 'N/A', 15) }}
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="bx bx-money me-1"></i>
                                                        {{ $memo->fundType->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>{{ $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('M d, Y') : 'N/A' }}</td>
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
                                                        <a href="{{ route('non-travel.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if(($memo->overall_status == 'draft' || $memo->overall_status == 'returned') && $memo->staff_id == user_session('staff_id'))
                                                            <a href="{{ route('non-travel.edit', $memo) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
                                                            </a>
                                                            <form action="{{ route('non-travel.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this memo? This action cannot be undone.');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        @if($memo->overall_status === 'approved')
                                                            <a href="{{ route('non-travel.print', $memo) }}" 
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
                                <p class="mb-0">No non-travel memos found.</p>
                                <small>Non-travel memos will appear here once they are created.</small>
                            </div>
                        @endif
        </div>
    </div>
            @endif
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
    if (document.getElementById('category_id')) {
        document.getElementById('category_id').addEventListener('change', function() {
            this.form.submit();
        });
    }
    
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
    
    if (document.getElementById('status')) {
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
    }
    });
</script>
@endsection
