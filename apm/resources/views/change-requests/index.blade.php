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
            <label for="search" class="form-label fw-semibold mb-1">
                <i class="bx bx-search me-1 text-success"></i> Search Activity Title
            </label>
            <input type="text" name="search" id="search" class="form-control" 
                   value="{{ request('search') }}" placeholder="Enter activity title...">
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
                    <span class="badge bg-success text-white ms-2">{{ $myChangeRequests->count() ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="myDivisionChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#myDivisionChangeRequests" type="button" role="tab" aria-controls="myDivisionChangeRequests" aria-selected="false">
                    <i class="bx bx-building me-2"></i> My Division CRs
                    <span class="badge bg-info text-white ms-2">{{ $myDivisionChangeRequests->count() ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sharedChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#sharedChangeRequests" type="button" role="tab" aria-controls="sharedChangeRequests" aria-selected="false">
                    <i class="bx bx-share me-2"></i> Shared CRs
                    <span class="badge bg-warning text-white ms-2">{{ $sharedChangeRequests->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#allChangeRequests" type="button" role="tab" aria-controls="allChangeRequests" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All CRs
                        <span class="badge bg-primary text-white ms-2">{{ $allChangeRequests->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="changeRequestTabsContent">
            <!-- My Change Requests Tab -->
            <div class="tab-pane fade show active" id="myChangeRequests" role="tabpanel" aria-labelledby="myChangeRequests-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-edit me-2"></i> My Change Requests
                            </h6>
                            <small class="text-muted">All change requests you have created</small>
                        </div>
                    </div>

                    @include('change-requests.partials.my-change-requests-tab')
                </div>
            </div>

            <!-- My Division Change Requests Tab -->
            <div class="tab-pane fade" id="myDivisionChangeRequests" role="tabpanel" aria-labelledby="myDivisionChangeRequests-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-building me-2"></i> My Division Change Requests
                            </h6>
                            <small class="text-muted">Change requests in your division</small>
                        </div>
                    </div>

                    @include('change-requests.partials.my-division-change-requests-tab')
                </div>
            </div>

            <!-- Shared Change Requests Tab -->
            <div class="tab-pane fade" id="sharedChangeRequests" role="tabpanel" aria-labelledby="sharedChangeRequests-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-warning fw-bold">
                                <i class="bx bx-share me-2"></i> Shared Change Requests
                            </h6>
                            <small class="text-muted">Change requests where you are the responsible person</small>
                        </div>
                    </div>

                    @include('change-requests.partials.shared-change-requests-tab')
                </div>
            </div>

            <!-- All Change Requests Tab -->
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="allChangeRequests" role="tabpanel" aria-labelledby="allChangeRequests-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Change Requests
                                </h6>
                                <small class="text-muted">All change requests in the system</small>
                            </div>
                        </div>

                        @include('change-requests.partials.all-change-requests-tab')
                    </div>
                </div>
            @endif
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
    
    // AJAX filtering - auto-update when filters change
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    
    // Auto-apply filters when they change
    if (document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('memo_type')) {
        document.getElementById('memo_type').addEventListener('change', applyFilters);
    }
    
    // Document number filter - apply on Enter key or after 1 second delay
    if (document.getElementById('document_number')) {
        let documentNumberTimeout;
        document.getElementById('document_number').addEventListener('input', function() {
            clearTimeout(documentNumberTimeout);
            documentNumberTimeout = setTimeout(applyFilters, 1000);
        });
        
        document.getElementById('document_number').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(documentNumberTimeout);
                applyFilters();
            }
        });
    }
    
    // Search input - apply on Enter key or after 500ms delay
    if (document.getElementById('search')) {
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(applyFilters, 500);
        });
        
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                clearTimeout(searchTimeout);
                applyFilters();
            }
        });
    }
    
    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        console.log('Loading change request tab data for:', tabId, 'page:', page);
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const documentNumber = document.getElementById('document_number')?.value;
        const staffId = document.getElementById('staff_id')?.value;
        const divisionId = document.getElementById('division_id')?.value;
        const status = document.getElementById('statusFilter')?.value;
        const memoType = document.getElementById('memo_type')?.value;
        const search = document.getElementById('search')?.value;
        
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (status) currentUrl.searchParams.set('status', status);
        if (memoType) currentUrl.searchParams.set('memo_type', memoType);
        if (search) currentUrl.searchParams.set('search', search);
        
        console.log('Change request request URL:', currentUrl.toString());
        
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
            console.log('Change request response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Change request response data:', data);
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                    attachPaginationHandlers(tabId);
                }
            } else {
                console.error('No HTML data received for change request');
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading change request tab data:', error);
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

// Delete change request function
function deleteChangeRequest(changeRequestId) {
    if (!confirm('Are you sure you want to delete this change request? This action cannot be undone.')) {
        return;
    }

    // Get CSRF token
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Create form data
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', token);

    // Send delete request
    fetch(`/apm/change-requests/${changeRequestId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            if (data.msg) {
                alert(data.msg);
            }
            // Reload the current tab
            const activeTab = document.querySelector('#changeRequestTabs .nav-link.active');
            if (activeTab) {
                loadTabData(activeTab.getAttribute('aria-controls'));
            }
        } else {
            alert(data.msg || 'Failed to delete change request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the change request');
    });
}
</script>
@endpush
