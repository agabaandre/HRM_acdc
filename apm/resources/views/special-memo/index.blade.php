@extends('layouts.app')

@section('title', 'Special Memos')

@section('header', 'Special Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('special-memo.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(get_staff_pending_action_count('special-memo') > 0)
            <span class="badge bg-danger ms-1">{{ get_staff_pending_action_count('special-memo') }}</span>
        @endif
    </a>
    <a href="{{ route('special-memo.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> Special Memo Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <form action="{{ route('special-memo.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="request_type_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-category me-1 text-success"></i> Request Type</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-category"></i></span>
                        <select name="request_type_id" id="request_type_id" class="form-select">
                            <option value="">All Request Types</option>
                            @foreach($requestTypes as $requestType)
                                <option value="{{ $requestType->id }}" {{ request('request_type_id') == $requestType->id ? 'selected' : '' }}>
                                    {{ $requestType->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-user me-1 text-success"></i> Staff</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-user"></i></span>
                        <select name="staff_id" id="staff_id" class="form-select">
                                    <option value="">All Staff</option>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->fname }} {{ $member->lname }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                </div>
                <div class="col-md-2">
                    <label for="division_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-building me-1 text-success"></i> Division</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-building"></i></span>
                        <select name="division_id" id="division_id" class="form-select">
                                    <option value="">All Divisions</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                    {{ $division->division_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            </div>
                            <div class="col-md-2">
                    <label for="status" class="form-label fw-semibold mb-1"><i
                            class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-info-circle"></i></span>
                        <select name="status" id="" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                                </select>
                    </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
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
                    <i class="bx bx-file-alt me-2"></i> My Submitted Special Memos
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedMemos->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allMemos-tab" data-bs-toggle="tab" data-bs-target="#allMemos" type="button" role="tab" aria-controls="allMemos" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Special Memos
                        <span class="badge bg-primary text-white ms-2">{{ $allMemos->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="memoTabsContent">
            <!-- My Submitted Special Memos Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Special Memos
                            </h6>
                            <small class="text-muted">All special memos you have submitted</small>
                            </div>
                    </div>

                    @if($mySubmittedMemos && $mySubmittedMemos->count() > 0)
                    <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-success">
                                    <tr>
                                        <th>#</th>
                                        <th>Title</th>
                                        <th>Request Type</th>
                                    <th>Division</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                    @php $count = 1; @endphp
                                    @foreach($mySubmittedMemos as $memo)
                                        <tr>
                                            <td>{{ $count++ }}</td>
                                            <td>
                                                <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                                                <small class="text-muted">{{ $memo->workplan_activity_code ?? 'No Code' }}</small>
                                        </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <i class="bx bx-category me-1"></i>
                                                    {{ $memo->requestType->name ?? 'N/A' }}
                                            </span>
                                        </td>
                                            <td>{{ $memo->division->division_name ?? 'N/A' }}</td>
                                            <td>{{ $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') : 'N/A' }}</td>
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
                                                    <div class="text-center">
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
                                                    <a href="{{ route('special-memo.show', $memo) }}" 
                                                       class="btn btn-sm btn-outline-info" title="View">
                                                    <i class="bx bx-show"></i>
                                                    </a>
                                                    @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                                        <a href="{{ route('special-memo.edit', $memo) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Edit">
                                                            <i class="bx bx-edit"></i>
                                                    </a>
                                                @endif
                                                    @if($memo->overall_status === 'approved')
                                                        <a href="{{ route('special-memo.print', $memo) }}" 
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
                            <p class="mb-0">No submitted special memos found.</p>
                            <small>Your submitted special memos will appear here.</small>
                        </div>
                    @endif
                </div>
            </div>

            <!-- All Special Memos Tab -->
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="allMemos" role="tabpanel" aria-labelledby="allMemos-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Special Memos
                                </h6>
                                <small class="text-muted">All special memos in the system</small>
                            </div>
                        </div>
                        
                        @if($allMemos && $allMemos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Request Type</th>
                                            <th>Responsible Staff</th>
                                            <th>Division</th>
                                            <th>Date</th>
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
                                                    <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                                                    <small class="text-muted">{{ $memo->workplan_activity_code ?? 'No Code' }}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <i class="bx bx-category me-1"></i>
                                                        {{ $memo->requestType->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($memo->staff)
                                                        {{ $memo->staff->fname }} {{ $memo->staff->lname }}
                                                    @else
                                                        <span class="text-muted">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td>{{ $memo->division->division_name ?? 'N/A' }}</td>
                                                <td>{{ $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') : 'N/A' }}</td>
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
                                                        <div class="text-center">
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
                                                        <a href="{{ route('special-memo.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($memo->overall_status === 'draft' && $memo->staff_id === user_session('staff_id'))
                                                            <a href="{{ route('special-memo.edit', $memo) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
                                                            </a>
                                                        @endif
                                                        @if($memo->overall_status === 'approved')
                                                            <a href="{{ route('special-memo.print', $memo) }}" 
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
                                <p class="mb-0">No special memos found.</p>
                                <small>Special memos will appear here once they are created.</small>
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
    // Form is now properly wrapped, so no need for auto-submit
    // Users can use the Filter button to apply filters
    });
</script>
@endsection
