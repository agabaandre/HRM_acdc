@extends('layouts.app')

@section('title', 'Change Requests')

@section('header', 'Change Requests')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('change-requests.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(get_pending_change_request_count(user_session('staff_id')) > 0)
            <span class="badge bg-danger ms-1">{{ get_pending_change_request_count(user_session('staff_id')) }}</span>
        @endif
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
/* 9-column layout for change requests */
.table th:nth-child(1) { width: 3%; }   /* # */
.table th:nth-child(2) { width: 16%; }  /* Document # */
.table th:nth-child(3) { width: 20%; }  /* Title */
.table th:nth-child(4) { width: 12%; }  /* Parent Memo */
.table th:nth-child(5) { width: 15%; }  /* Division */
.table th:nth-child(6) { width: 7%; }   /* Date Range */
.table th:nth-child(7) { width: 10%; }  /* Changes */
.table th:nth-child(8) { width: 8%; }   /* Status */
.table th:nth-child(9) { width: 11%; }  /* Actions */
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
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-edit me-2 text-success"></i> Change Request Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="changeRequestFilters" autocomplete="off">
            <form action="{{ route('change-requests.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Doc #">
                </div>
                <div class="col-md-2">
                    <label for="memo_type" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Memo Type
                    </label>
                    <select name="memo_type" id="memo_type" class="form-select select2" style="width: 100%;">
                        <option value="">All Memo Types</option>
                        <option value="App\Models\Activity" {{ request('memo_type') == 'App\Models\Activity' ? 'selected' : '' }}>Activity</option>
                        <option value="App\Models\SpecialMemo" {{ request('memo_type') == 'App\Models\SpecialMemo' ? 'selected' : '' }}>Special Memo</option>
                        <option value="App\Models\NonTravelMemo" {{ request('memo_type') == 'App\Models\NonTravelMemo' ? 'selected' : '' }}>Non-Travel Memo</option>
                        <option value="App\Models\RequestArf" {{ request('memo_type') == 'App\Models\RequestArf' ? 'selected' : '' }}>Request ARF</option>
                        <option value="App\Models\ServiceRequest" {{ request('memo_type') == 'App\Models\ServiceRequest' ? 'selected' : '' }}>Service Request</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff
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
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                {{ $label }}
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
                    <a href="{{ route('change-requests.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
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
        <ul class="nav nav-tabs nav-fill" id="changeRequestTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="myChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#myChangeRequests" type="button" role="tab" aria-controls="myChangeRequests" aria-selected="true">
                    <i class="bx bx-edit me-2"></i> My Change Requests
                    <span class="badge bg-success text-white ms-2">{{ $changeRequests->count() ?? 0 }}</span>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="changeRequestTabsContent">
            <div class="tab-pane fade show active" id="myChangeRequests" role="tabpanel" aria-labelledby="myChangeRequests-tab">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center">#</th>
                                <th class="text-center">Document #</th>
                                <th class="text-center">Title</th>
                                <th class="text-center">Parent Memo</th>
                                <th class="text-center">Division</th>
                                <th class="text-center">Date Range</th>
                                <th class="text-center">Changes</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                <tbody>
                    @forelse($changeRequests as $index => $changeRequest)
                        <tr>
                            <td class="text-center fw-bold">{{ $changeRequests->firstItem() + $index }}</td>
                            <td class="text-center">
                                @if($changeRequest->document_number)
                                    <span class="badge bg-primary">{{ $changeRequest->document_number }}</span>
                                @else
                                    <span class="text-muted">Pending</span>
                                @endif
                            </td>
                            <td class="table-title-cell">
                                <div class="fw-semibold text-dark">{{ $changeRequest->activity_title }}</div>
                                @if($changeRequest->supporting_reasons)
                                    <small class="text-muted">{{ Str::limit($changeRequest->supporting_reasons, 50) }}</small>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($changeRequest->parentMemo)
                                    <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                                    <br>
                                    <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold">{{ $changeRequest->division->division_name ?? 'N/A' }}</span>
                            </td>
                            <td class="text-center">
                                <div class="small">
                                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d') }}</div>
                                    <div class="text-muted">to</div>
                                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}</div>
                                </div>
                            </td>
                            <td class="text-center">
                                @if($changeRequest->hasAnyChanges())
                                    <div class="d-flex flex-wrap gap-1 justify-content-center">
                                        @if($changeRequest->has_budget_id_changed)
                                            <span class="badge bg-warning text-dark">Budget</span>
                                        @endif
                                        @if($changeRequest->has_activity_title_changed)
                                            <span class="badge bg-warning text-dark">Title</span>
                                        @endif
                                        @if($changeRequest->has_location_changed)
                                            <span class="badge bg-warning text-dark">Location</span>
                                        @endif
                                        @if($changeRequest->has_internal_participants_changed)
                                            <span class="badge bg-warning text-dark">Participants</span>
                                        @endif
                                        @if($changeRequest->has_request_type_id_changed)
                                            <span class="badge bg-warning text-dark">Type</span>
                                        @endif
                                        @if($changeRequest->has_fund_type_id_changed)
                                            <span class="badge bg-warning text-dark">Fund</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">No changes</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @switch($changeRequest->overall_status)
                                    @case('draft')
                                        <span class="badge bg-secondary">Draft</span>
                                        @break
                                    @case('submitted')
                                        <span class="badge bg-warning text-dark">Submitted</span>
                                        @break
                                    @case('approved')
                                        <span class="badge bg-success">Approved</span>
                                        @break
                                    @case('rejected')
                                        <span class="badge bg-danger">Rejected</span>
                                        @break
                                    @default
                                        <span class="badge bg-light text-dark">{{ ucfirst($changeRequest->overall_status) }}</span>
                                @endswitch
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('change-requests.show', $changeRequest) }}" 
                                       class="btn btn-sm btn-outline-primary" 
                                       title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    @if($changeRequest->overall_status === 'draft')
                                        <a href="{{ route('change-requests.edit', $changeRequest) }}" 
                                           class="btn btn-sm btn-outline-warning" 
                                           title="Edit">
                                            <i class="bx bx-edit"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-info-circle fs-1 d-block mb-2"></i>
                                    <h5>No Change Requests Found</h5>
                                    <p>No change requests match your current filters.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                    </table>
                </div>
                
                @if($changeRequests->hasPages())
                    <div class="card-footer bg-white border-top-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing {{ $changeRequests->firstItem() }} to {{ $changeRequests->lastItem() }} of {{ $changeRequests->total() }} results
                            </div>
                            <div>
                                {{ $changeRequests->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Auto-submit form on filter change
    $('#statusFilter, #division_id, #staff_id').on('change', function() {
        $(this).closest('form').submit();
    });
});
</script>
@endpush
