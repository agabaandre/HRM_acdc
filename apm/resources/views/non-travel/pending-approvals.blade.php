@extends('layouts.app')

@section('title', 'Non-Travel Memo Pending Approvals')
@section('header', 'Non-Travel Memo Pending Approvals')

@section('header-actions')
    <a href="{{ route('non-travel.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Non-Travel Memos
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
                        <i class="bx bx-time me-2 text-success"></i> Non-Travel Memo Approval Management
                    </h4>
                    <small class="text-muted">Showing non-travel memos at your current approval level</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6 me-3">
                        <i class="bx bx-time me-1"></i>
                        {{ $pendingMemos->count() }} Pending
                    </div>
                    <a href="{{ route('non-travel.export.all', ['status' => 'pending']) }}" class="btn btn-outline-warning btn-sm">
                        <i class="bx bx-download me-1"></i> Export to Excel
                    </a>
                </div>
            </div>

            <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
                <div class="col-12 mb-2">
                    <small class="text-muted">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>Note:</strong> These filters apply to non-travel memos currently at your approval level (excluding draft memos).
                    </small>
                </div>
                <div class="col-md-3">
                    <label for="categoryFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Category
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="categoryFilter">
                            <option value="">All Categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
               
                <div class="col-md-3">
                    <label for="divisionFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-building me-1 text-success"></i> Division
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="divisionFilter">
                            <option value="">All Divisions</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="documentFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-hash me-1 text-success"></i> Document #
                    </label>
                    <input type="text" id="documentFilter" class="form-control" 
                           placeholder="Search by document number...">
                </div>
                <div class="col-md-2">
                    <label for="staffFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff Member
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="staffFilter">
                            <option value="">All Staff</option>
                            @foreach ($pendingMemos as $memo)
                                @if($memo->staff)
                                    <option value="{{ $memo->staff->staff_id }}">{{ $memo->staff->fname }} {{ $memo->staff->lname }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Apply Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="memoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                        <i class="bx bx-time me-2"></i> Pending Approval
                        <span class="badge bg-warning text-white ms-2">{{ $pendingMemos->total() ?? 0 }}</span>
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
            <div class="tab-content" id="memoTabsContent">
                <!-- Pending Approval Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-warning fw-bold">
                                    <i class="bx bx-time me-2"></i> Pending Approval
                                </h6>
                                <small class="text-muted">Non-travel memos awaiting your approval</small>
                            </div>
                        </div>
                        
                        @if($pendingMemos && $pendingMemos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="pendingTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 120px;">Document Number</th>
                                            <th style="width: 280px;">Title</th>
                                            <th style="width: 100px;">Category</th>
                                            <th style="width: 120px;">Staff Member</th>
                                            <th style="width: 120px;">Division</th>
                                            <th style="width: 100px;">Date</th>
                                            <th style="width: 150px;">Status</th>
                                            <th class="text-center" style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($pendingMemos->currentPage() - 1) * $pendingMemos->perPage() + 1; @endphp
                                        @foreach($pendingMemos as $memo)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td style="width: 120px;">
                                                    <div class="text-muted small">{{ $memo->document_number ?? 'N/A' }}</div>
                                                </td>
                                                <td style="width: 280px;">
                                                    <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $memo->activity_title }}">{{ $memo->activity_title }}</div>
                                                </td>
                                                <td style="width: 120px; word-wrap: break-word; white-space: normal;">
                                                    <span class="badge bg-info text-dark">
                                                        <i class="bx bx-category me-1"></i>
                                                        {{ $memo->nonTravelMemoCategory->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    @if($memo->staff)
                                                        <div>{{ $memo->staff->fname }} {{ $memo->staff->lname }}</div>
                                                    @else
                                                        <span class="text-muted">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    <div>{{ $memo->division->division_name ?? 'N/A' }}</div>
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
                                                        
                                                        // Get workflow information using helper function
                                                        $workflowInfo = $getWorkflowInfo($memo);
                                                        $approvalLevel = $workflowInfo['approvalLevel'];
                                                        $workflowRole = $workflowInfo['workflowRole'];
                                                        $actorName = $workflowInfo['actorName'];
                                                    @endphp
                                                    
                                                    @if($memo->overall_status === 'pending')
                                                        <!-- Structured display for pending status -->
                                                        <div class="text-center">
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
                                                        @if($memo->overall_status === 'pending')
                                                            <a href="{{ route('non-travel.status', $memo) }}" 
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
                            @if($pendingMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingMemos->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $pendingMemos->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-time fs-1 text-warning opacity-50"></i>
                                <p class="mb-0">No pending non-travel memos found.</p>
                                <small>Non-travel memos awaiting your approval will appear here.</small>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Approved by Me Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-check-circle me-2"></i> Approved by Me
                                </h6>
                                <small class="text-muted">Non-travel memos you have approved</small>
                            </div>
                        </div>
                        
                        @if($approvedByMe && $approvedByMe->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="approvedTable">
                                    <thead class="table-success">
                                        <tr>
                                            <th style="width: 40px;">#</th>
                                            <th style="width: 120px;">Document Number</th>
                                            <th style="width: 280px;">Title</th>
                                            <th style="width: 100px;">Category</th>
                                            <th style="width: 120px;">Staff Member</th>
                                            <th style="width: 120px;">Division</th>
                                            <th style="width: 100px;">Date</th>
                                            <th style="width: 150px;">Status</th>
                                            <th class="text-center" style="width: 100px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                                        @foreach($approvedByMe as $memo)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td style="width: 120px;">
                                                    <div class="text-muted small">{{ $memo->document_number ?? 'N/A' }}</div>
                                                </td>
                                                <td style="width: 280px;">
                                                    <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $memo->activity_title }}">{{ $memo->activity_title }}</div>
                                                </td>
                                                <td style="width: 120px; word-wrap: break-word; white-space: normal;">
                                                    <span class="badge bg-info text-dark">
                                                        <i class="bx bx-category me-1"></i>
                                                        {{ $memo->nonTravelMemoCategory->name ?? 'N/A' }}
                                                    </span>
                                                </td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    @if($memo->staff)
                                                        <div>{{ $memo->staff->fname }} {{ $memo->staff->lname }}</div>
                                                    @else
                                                        <span class="text-muted">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                                                    <div>{{ $memo->division->division_name ?? 'N/A' }}</div>
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
                                                        
                                                        // Get workflow information using helper function
                                                        $workflowInfo = $getWorkflowInfo($memo);
                                                        $approvalLevel = $workflowInfo['approvalLevel'];
                                                        $workflowRole = $workflowInfo['workflowRole'];
                                                        $actorName = $workflowInfo['actorName'];
                                                    @endphp
                                                    
                                                    @if($memo->overall_status === 'approved')
                                                        <!-- Structured display for approved status -->
                                                        <div class="text-center">
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
                            @if($approvedByMe instanceof \Illuminate\Pagination\LengthAwarePaginator && $approvedByMe->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $approvedByMe->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-check-circle fs-1 text-success opacity-50"></i>
                                <p class="mb-0">No approved non-travel memos found.</p>
                                <small>Non-travel memos you have approved will appear here.</small>
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
        const categoryFilter = document.getElementById('categoryFilter').value;
        const divisionFilter = document.getElementById('divisionFilter').value;
        const staffFilter = document.getElementById('staffFilter').value;
        const documentFilter = document.getElementById('documentFilter').value.toLowerCase();

        const rows = document.querySelectorAll('#pendingTable tbody tr');
        
        rows.forEach(row => {
            let show = true;
            
            // Document number filter
            if (documentFilter && !row.querySelector('td:nth-child(2)').textContent.toLowerCase().includes(documentFilter)) {
                show = false;
            }
            
            // Category filter
            if (categoryFilter && !row.querySelector('td:nth-child(4)').textContent.includes(categoryFilter)) {
                show = false;
            }
            
            // Division filter
            if (divisionFilter && !row.querySelector('td:nth-child(6)').textContent.includes(divisionFilter)) {
                show = false;
            }
            
            // Staff filter
            if (staffFilter && !row.querySelector('td:nth-child(5)').textContent.includes(staffFilter)) {
                show = false;
            }
            
            row.style.display = show ? '' : 'none';
        });
    };

    // Apply filters button
    document.getElementById('applyFilters').addEventListener('click', applyFilters);

    // Auto-apply filters on change
    document.getElementById('categoryFilter').addEventListener('change', applyFilters);
    document.getElementById('divisionFilter').addEventListener('change', applyFilters);
    document.getElementById('staffFilter').addEventListener('change', applyFilters);
    document.getElementById('documentFilter').addEventListener('input', applyFilters);
});
</script>
@endpush
