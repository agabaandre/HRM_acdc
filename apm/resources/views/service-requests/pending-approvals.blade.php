@extends('layouts.app')

@section('title', 'Service Request Pending Approvals')
@section('header', 'Service Request Pending Approvals')

@section('header-actions')
    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Service Requests
    </a>
@endsection

@section('content')
    @if(isset($error))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bx bx-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> {{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <div>
                    <h4 class="mb-0 text-success fw-bold">
                        <i class="bx bx-time me-2 text-success"></i> Service Request Approval Management
                    </h4>
                    <small class="text-muted">Showing service requests at your current approval level</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6 me-3">
                        <i class="bx bx-time me-1"></i>
                        {{ $pendingRequests->count() }} Pending
                    </div>
                    <a href="{{ route('service-requests.export.all', ['status' => 'pending']) }}" class="btn btn-outline-warning btn-sm">
                        <i class="bx bx-download me-1"></i> Export to Excel
                    </a>
                </div>
            </div>

            <div class="row g-3 align-items-end" id="requestFilters" autocomplete="off">
                <div class="col-md-3">
                    <label for="divisionFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-building me-1 text-success"></i> Division
                    </label>
                    <select name="division" id="divisionFilter" class="form-select select2">
                        <option value="">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->division_name }}" {{ request('division') == $division->division_name ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="staffFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff
                    </label>
                    <input type="text" name="staff" id="staffFilter" class="form-control" 
                           placeholder="Search by staff name..." value="{{ request('staff') }}">
                </div>
                <div class="col-md-2">
                    <label for="documentFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-hash me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document" id="documentFilter" class="form-control" 
                           placeholder="Search by document number..." value="{{ request('document') }}">
                </div>
                <div class="col-md-2">
                    <label for="titleFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Title
                    </label>
                    <input type="text" name="title" id="titleFilter" class="form-control" 
                           placeholder="Search by title..." value="{{ request('title') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="requestTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                        <i class="bx bx-time me-2"></i> Pending Approval
                        <span class="badge bg-warning text-white ms-2">{{ $pendingRequests->total() ?? 0 }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                        <i class="bx bx-check-circle me-2"></i> Approved by Me
                        <span class="badge bg-success text-white ms-2">{{ $approvedByMe->total() ?? 0 }}</span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="requestTabsContent">
                <!-- Pending Approvals Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="p-3">
                        @if($pendingRequests && $pendingRequests->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="pendingTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 120px;">Document Number</th>
                                            <th style="width: 280px;">Title</th>
                                            <th style="width: 120px;">Staff</th>
                                            <th style="width: 120px;">Division</th>
                                            <th style="width: 100px;">Request Date</th>
                                            <th style="width: 100px;">Total Budget</th>
                                            <th style="width: 150px;">Current Approver</th>
                                            <th class="text-center" style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = 1; @endphp
                                        @foreach($pendingRequests as $request)
                                            @php
                                                $workflowInfo = $getWorkflowInfo($request);
                                                $approvalLevel = $workflowInfo['approvalLevel'];
                                                $workflowRole = $workflowInfo['workflowRole'];
                                                $actorName = $workflowInfo['actorName'];
                                            @endphp
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                
                                                <td style="width: 120px;">
                                                    <div class="text-muted small">{{ $request->document_number ?? 'N/A' }}</div>
                                                </td>
                                                <td style="width: 280px;">
                                                    <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $request->title ?? 'N/A' }}">{{ $request->title ?? 'N/A' }}</div>
                                                </td>
                                                <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    <div>{{ $request->division->division_name ?? 'N/A' }}</div>
                                                </td>
                                                <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A' }}</td>
                                                <td>
                                                    <span class="fw-bold text-success">
                                                        ${{ number_format($request->new_total_budget ?? 0, 2) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="text-center">
                                                        <small class="text-muted d-block fw-bold">{{ $actorName }}</small>
                                                        <small class="text-muted d-block">{{ $workflowRole }}</small>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('service-requests.show', $request) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($request->overall_status === 'pending')
                                                            <a href="{{ route('service-requests.show', $request) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="View Status">
                                                                <i class="bx bx-info-circle"></i>
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
                            @if($pendingRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingRequests->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $pendingRequests->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-5">
                                <i class="bx bx-time fs-1 text-warning opacity-50"></i>
                                <h5 class="text-muted mt-3">No Pending Service Requests</h5>
                                <p class="mb-0">No service requests are currently pending your approval.</p>
                                <small>Service requests requiring your action will appear here.</small>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Approved by Me Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <div class="p-3">
                        @if($approvedByMe && $approvedByMe->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="approvedTable">
                                    <thead class="table-success">
                                        <tr>
                                            <th>#</th>
                                            <th>Request Number</th>
                                            <th>Title</th>
                                            <th>Staff</th>
                                            <th>Division</th>
                                            <th>Request Date</th>
                                            <th>Total Budget</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = 1; @endphp
                                        @foreach($approvedByMe as $request)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td>
                                                    <div class="fw-bold text-primary">{{ $request->request_number }}</div>
                                                </td>
                                                <td style="width: 120px;">
                                                    <div class="text-muted small">{{ $request->document_number ?? 'N/A' }}</div>
                                                </td>
                                                <td style="width: 280px;">
                                                    <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $request->title ?? 'N/A' }}">{{ $request->title ?? 'N/A' }}</div>
                                                </td>
                                                <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    <div>{{ $request->division->division_name ?? 'N/A' }}</div>
                                                </td>
                                                <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A' }}</td>
                                                <td>
                                                    <span class="fw-bold text-success">
                                                        ${{ number_format($request->new_total_budget ?? 0, 2) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        {{ strtoupper($request->overall_status) }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('service-requests.show', $request) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($request->overall_status === 'approved')
                                                            <a href="{{ route('service-requests.show', $request) }}" 
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
                            @if($approvedByMe instanceof \Illuminate\Pagination\LengthAwarePaginator && $approvedByMe->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $approvedByMe->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-5">
                                <i class="bx bx-check-circle fs-1 text-success opacity-50"></i>
                                <p class="mb-0">No approved service requests found.</p>
                                <small>Service requests you have approved will appear here.</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2 for filters
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2').select2({
            placeholder: 'Select an option',
            allowClear: true
        });
    }

    // Filter functionality
    const applyFilters = () => {
        const divisionFilter = document.getElementById('divisionFilter').value;
        const staffFilter = document.getElementById('staffFilter').value;
        const titleFilter = document.getElementById('titleFilter').value;

        const rows = document.querySelectorAll('#pendingTable tbody tr');
        
        rows.forEach(row => {
            let show = true;
            
            // Division filter
            if (divisionFilter && !row.querySelector('td:nth-child(5)').textContent.includes(divisionFilter)) {
                show = false;
            }
            
            // Staff filter
            if (staffFilter && !row.querySelector('td:nth-child(4)').textContent.toLowerCase().includes(staffFilter.toLowerCase())) {
                show = false;
            }
            
            // Title filter
            if (titleFilter && !row.querySelector('td:nth-child(3)').textContent.toLowerCase().includes(titleFilter.toLowerCase())) {
                show = false;
            }
            
            row.style.display = show ? '' : 'none';
        });
    };

    // Apply filters button
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
});
</script>
@endpush
