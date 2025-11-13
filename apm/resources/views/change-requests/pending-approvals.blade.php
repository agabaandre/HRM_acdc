@extends('layouts.app')

@section('title', 'Change Request Pending Approvals')
@section('header', 'Change Request Pending Approvals')

@section('header-actions')
    <a href="{{ route('change-requests.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Change Requests
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
                        <i class="bx bx-time me-2 text-success"></i> Change Request Approval Management
                    </h4>
                    <small class="text-muted">Showing change requests at your current approval level</small>
                </div>
                <div class="text-end">
                    <div class="badge bg-warning fs-6 me-3">
                        <i class="bx bx-time me-1"></i>
                        {{ $pendingChangeRequests->count() }} Pending
                    </div>
                </div>
            </div>

            <div class="row g-3 align-items-end" id="changeRequestFilters" autocomplete="off">
                <div class="col-12 mb-2">
                    <small class="text-muted">
                        <i class="bx bx-info-circle me-1"></i>
                        <strong>Note:</strong> These filters apply to change requests currently at your approval level (excluding draft change requests).
                    </small>
                </div>
                <div class="col-md-3">
                    <label for="memoTypeFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Memo Type
                    </label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="memoTypeFilter">
                            <option value="">All Memo Types</option>
                            <option value="App\Models\Activity">Activity</option>
                            <option value="App\Models\SpecialMemo">Special Memo</option>
                            <option value="App\Models\NonTravelMemo">Non-Travel Memo</option>
                            <option value="App\Models\RequestArf">Request ARF</option>
                            <option value="App\Models\ServiceRequest">Service Request</option>
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
                                <option value="{{ $division->division_id }}">{{ $division->division_name }}</option>
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
                            @foreach ($pendingChangeRequests as $changeRequest)
                                @if($changeRequest->staff)
                                    <option value="{{ $changeRequest->staff->staff_id }}">{{ $changeRequest->staff->fname }} {{ $changeRequest->staff->lname }}</option>
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
            <ul class="nav nav-tabs nav-fill" id="changeRequestTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                        <i class="bx bx-time me-2"></i> Pending Approval
                        <span class="badge bg-warning text-white ms-2">{{ $pendingChangeRequests->total() ?? 0 }}</span>
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
            <div class="tab-content" id="changeRequestTabsContent">
                <!-- Pending Approval Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-warning fw-bold">
                                    <i class="bx bx-time me-2"></i> Pending Approval
                                </h6>
                                <small class="text-muted">Change requests awaiting your approval</small>
                            </div>
                        </div>
                        
                        @if($pendingChangeRequests && $pendingChangeRequests->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="pendingTable">
                                    <thead class="table-warning">
                                        <tr>
                                            <th style="width: 3%;">#</th>
                                            <th style="width: 20%; max-width: 200px;">Title</th>
                                            <th>Parent Memo</th>
                                            <th>Staff Member</th>
                                            <th>Division</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($pendingChangeRequests->currentPage() - 1) * $pendingChangeRequests->perPage() + 1; @endphp
                                        @foreach($pendingChangeRequests as $changeRequest)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td style="max-width: 200px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                                    <div class="fw-bold text-primary">{{ $changeRequest->activity_title }}</div>
                                                </td>
                                                <td>
                                                    @if($changeRequest->parentMemo)
                                                        <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                                                        <br>
                                                        <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($changeRequest->staff)
                                                        {{ $changeRequest->staff->fname }} {{ $changeRequest->staff->lname }}
                                                    @else
                                                        <span class="text-muted">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td>{{ $changeRequest->division->division_name ?? 'N/A' }}</td>
                                                <td>{{ $changeRequest->date_from ? \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') : 'N/A' }}</td>
                                                <td>
                                                    @php
                                                        $statusBadgeClass = [
                                                            'draft' => 'bg-secondary',
                                                            'submitted' => 'bg-warning',
                                                            'approved' => 'bg-success',
                                                            'rejected' => 'bg-danger',
                                                        ];
                                                        $statusClass = $statusBadgeClass[$changeRequest->overall_status] ?? 'bg-secondary';
                                                    @endphp
                                                    
                                                    @if($changeRequest->overall_status === 'submitted')
                                                        <div class="text-center">
                                                            <span class="badge {{ $statusClass }} mb-1">
                                                                {{ strtoupper($changeRequest->overall_status) }}
                                                            </span>
                                                        </div>
                                                    @else
                                                        <span class="badge {{ $statusClass }}">
                                                            {{ strtoupper($changeRequest->overall_status ?? 'draft') }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('change-requests.show', $changeRequest) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
                                                        @if($changeRequest->overall_status === 'draft' && $changeRequest->staff_id === user_session('staff_id'))
                                                            <a href="{{ route('change-requests.edit', $changeRequest) }}" 
                                                               class="btn btn-sm btn-outline-warning" title="Edit">
                                                                <i class="bx bx-edit"></i>
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
                            @if($pendingChangeRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingChangeRequests->hasPages())
                                <div class="d-flex justify-content-center mt-3">
                                    {{ $pendingChangeRequests->appends(request()->query())->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bx bx-time fs-1 text-warning opacity-50"></i>
                                <p class="mb-0">No pending change requests found.</p>
                                <small>Change requests awaiting your approval will appear here.</small>
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
                                <small class="text-muted">Change requests you have approved</small>
                            </div>
                        </div>
                        
                        @if($approvedByMe && $approvedByMe->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="approvedTable">
                                    <thead class="table-success">
                                        <tr>
                                            <th style="width: 3%;">#</th>
                                            <th style="width: 20%; max-width: 200px;">Title</th>
                                            <th>Parent Memo</th>
                                            <th>Staff Member</th>
                                            <th>Division</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                                        @foreach($approvedByMe as $changeRequest)
                                            <tr>
                                                <td>{{ $count++ }}</td>
                                                <td style="max-width: 200px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                                    <div class="fw-bold text-primary">{{ $changeRequest->activity_title }}</div>
                                                </td>
                                                <td>
                                                    @if($changeRequest->parentMemo)
                                                        <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                                                        <br>
                                                        <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                                                    @else
                                                        <span class="text-muted">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($changeRequest->staff)
                                                        {{ $changeRequest->staff->fname }} {{ $changeRequest->staff->lname }}
                                                    @else
                                                        <span class="text-muted">Not assigned</span>
                                                    @endif
                                                </td>
                                                <td>{{ $changeRequest->division->division_name ?? 'N/A' }}</td>
                                                <td>{{ $changeRequest->date_from ? \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') : 'N/A' }}</td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        {{ strtoupper($changeRequest->overall_status ?? 'approved') }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="btn-group">
                                                        <a href="{{ route('change-requests.show', $changeRequest) }}" 
                                                           class="btn btn-sm btn-outline-info" title="View">
                                                            <i class="bx bx-show"></i>
                                                        </a>
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
                                <p class="mb-0">No approved change requests found.</p>
                                <small>Change requests you have approved will appear here.</small>
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
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    // Filter functionality
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    
    // Auto-apply filters when they change
    if (document.getElementById('memoTypeFilter')) {
        document.getElementById('memoTypeFilter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('divisionFilter')) {
        document.getElementById('divisionFilter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('staffFilter')) {
        document.getElementById('staffFilter').addEventListener('change', applyFilters);
    }
    
    // Manual filter button click
    if (document.getElementById('applyFilters')) {
        document.getElementById('applyFilters').addEventListener('click', applyFilters);
    }
    
    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        console.log('Loading change request pending approval tab data for:', tabId, 'page:', page);
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const memoType = document.getElementById('memoTypeFilter')?.value;
        const divisionId = document.getElementById('divisionFilter')?.value;
        const staffId = document.getElementById('staffFilter')?.value;
        
        if (memoType) currentUrl.searchParams.set('memo_type', memoType);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        
        console.log('Change request pending approval request URL:', currentUrl.toString());
        
        // Show loading indicator
        const tabContent = document.getElementById(tabId);
        if (tabContent) {
            tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }
        
        fetch(currentUrl.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('Change request pending approval response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Change request pending approval response data:', data);
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                    attachPaginationHandlers(tabId);
                }
            } else {
                console.error('No HTML data received for change request pending approval');
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading change request pending approval tab data:', error);
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    function attachPaginationHandlers(tabId) {
        const tabContent = document.getElementById(tabId);
        if (!tabContent) return;
        
        const paginationLinks = tabContent.querySelectorAll('.pagination a');
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const page = url.searchParams.get('page') || 1;
                loadTabData(tabId, page);
            });
        });
    }

    // Add click handlers to tabs to load data via AJAX
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent Bootstrap's default tab behavior
            
            // Remove active class from all tabs and buttons
            document.querySelectorAll('#changeRequestTabs .nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('#changeRequestTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            
            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            const tabId = this.getAttribute('aria-controls');
            const tabPane = document.getElementById(tabId);
            if (tabPane) {
                tabPane.classList.add('active', 'show');
            }
            
            loadTabData(tabId);
        });
    });
    
    // Load data for the default active tab
    const activeTabButton = document.querySelector('#changeRequestTabs .nav-link.active');
    if (activeTabButton) {
        loadTabData(activeTabButton.getAttribute('aria-controls'));
    }
});
</script>
@endpush