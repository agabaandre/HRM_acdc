@extends('layouts.app')

@section('title', 'Single Memo Pending Approvals')
@section('header', 'Single Memo Pending Approvals')

@section('header-actions')
    <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Single Memos
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
                        <i class="bx bx-time me-2 text-success"></i> Single Memo Approval Management
                    </h4>
                    <small class="text-muted">Showing single memos at your current approval level</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6 me-3">
                        <i class="bx bx-time me-1"></i>
                        {{ $pendingMemos->count() }} Pending
                    </div>
                    <a href="{{ route('activities.single-memos.index', ['status' => 'pending']) }}" class="btn btn-outline-warning btn-sm">
                        <i class="bx bx-list me-1"></i> View All Pending
                    </a>
                </div>
            </div>

            <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
                <div class="col-12 mb-2">
                    <small class="text-muted">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>Note:</strong> These filters apply to single memos currently at your approval level (excluding draft memos).
                    </small>
                </div>
                <div class="col-md-3">
                    <label for="requestTypeFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Request Type
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="requestTypeFilter">
                            <option value="">All Request Types</option>
                            @foreach ($requestTypes as $requestType)
                                <option value="{{ $requestType->id }}">{{ $requestType->name }}</option>
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
                <div class="col-md-3">
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
                <div class="col-md-3 d-flex align-items-end">
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
                                <small class="text-muted">Single memos awaiting your approval</small>
                            </div>
                        </div>
                        
                        @if($pendingMemos && $pendingMemos->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="pendingTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Request Type</th>
                                            <th>Staff Member</th>
                                            <th>Division</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = 1; @endphp
                                        @foreach($pendingMemos as $memo)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td>
                                                    <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                                                    @if($memo->document_number)
                                                        <small class="text-muted">#{{ $memo->document_number }}</small>
                                                    @endif
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
                                                        <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if(can_edit_memo($memo))
                                                            <a href="{{ route('activities.single-memos.edit', [$memo->matrix, $memo]) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
                                                            </a>
                                                        @endif
                                                        @if($memo->responsible_person_id == user_session('staff_id') && in_array($memo->overall_status, ['draft', 'returned']))
                                                            <form action="{{ route('activities.single-memos.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this single memo? This action cannot be undone.')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        @if($memo->overall_status === 'approved')
                                                            <a href="{{ route('activities.single-memos.print', $memo) }}" 
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
                            @if($pendingMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingMemos->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $pendingMemos->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-time fs-1 text-warning opacity-50"></i>
                                <p class="mb-0">No pending single memos found.</p>
                                <small>Single memos awaiting your approval will appear here.</small>
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
                                <small class="text-muted">Single memos you have approved</small>
                            </div>
                        </div>
                        
                        @if($approvedByMe && $approvedByMe->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="approvedTable">
                                    <thead class="table-success">
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Request Type</th>
                                            <th>Staff Member</th>
                                            <th>Division</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = 1; @endphp
                                        @foreach($approvedByMe as $memo)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td>
                                                    <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                                                    @if($memo->document_number)
                                                        <small class="text-muted">#{{ $memo->document_number }}</small>
                                                    @endif
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
                                                        <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if(can_edit_memo($memo))
                                                            <a href="{{ route('activities.single-memos.edit', [$memo->matrix, $memo]) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
                                                            </a>
                                                        @endif
                                                        @if($memo->responsible_person_id == user_session('staff_id') && in_array($memo->overall_status, ['draft', 'returned']))
                                                            <form action="{{ route('activities.single-memos.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this single memo? This action cannot be undone.')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                    <i class="bx bx-trash"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        @if($memo->overall_status === 'approved')
                                                            <a href="{{ route('matrices.activities.memo-pdf', [$memo->matrix, $memo]) }}" 
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
                                <p class="mb-0">No approved single memos found.</p>
                                <small>Single memos you have approved will appear here.</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

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
        $('#applyFilters').on('click', function() {
            const requestType = $('#requestTypeFilter').val();
            const division = $('#divisionFilter').val();
            const staff = $('#staffFilter').val();

            $('#pendingTable tbody tr').each(function() {
                let showRow = true;
                const row = $(this);

                if (requestType && row.find('td:nth-child(3) .badge').text().trim() !== requestType) {
                    showRow = false;
                }

                if (division && row.find('td:nth-child(5)').text().trim() !== division) {
                    showRow = false;
                }

                if (staff && row.find('td:nth-child(4)').text().trim() !== staff) {
                    showRow = false;
                }

                row.toggle(showRow);
            });

            // Update count
            const visibleRows = $('#pendingTable tbody tr:visible').length;
            $('#pending-tab .badge').text(visibleRows);
        });
    });
    </script>
@endsection
