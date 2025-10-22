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
                    <select name="staff" id="staffFilter" class="form-select select2">
                        <option value="">All Staff</option>
                        @foreach($divisions as $division)
                            @foreach($division->staff as $staff)
                                <option value="{{ $staff->fname }} {{ $staff->lname }}" {{ request('staff') == $staff->fname . ' ' . $staff->lname ? 'selected' : '' }}>
                                    {{ $staff->fname }} {{ $staff->lname }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="statusFilter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-info-circle me-1 text-success"></i> Status
                    </label>
                    <select name="status" id="statusFilter" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('service-requests.pending-approvals') }}" class="btn btn-outline-secondary w-100 fw-bold">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
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
                        <span class="badge bg-warning text-white ms-2">{{ $pendingRequests->count() ?? 0 }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
                        <i class="bx bx-check-circle me-2"></i> Approved by Me
                        <span class="badge bg-success text-white ms-2">{{ $approvedByMe->count() ?? 0 }}</span>
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="requestTabsContent">
                <!-- Pending Approvals Tab -->
                <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="p-3">
                        @include('service-requests.partials.pending-approvals-tab')
                    </div>
                </div>

                <!-- Approved by Me Tab -->
                <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
                    <div class="p-3">
                        @include('service-requests.partials.approved-by-me-tab')
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('.select2').select2({
            width: '100%'
        });
    }
    
    // AJAX filtering - auto-update when filters change
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    
    // Manual filter button click
    if (document.getElementById('applyFilters')) {
        document.getElementById('applyFilters').addEventListener('click', applyFilters);
    }
    
    // Auto-apply filters when they change
    if (document.getElementById('divisionFilter')) {
        document.getElementById('divisionFilter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('staffFilter')) {
        document.getElementById('staffFilter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId) {
        console.log('Loading service requests pending approvals tab data for:', tabId);
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const division = document.getElementById('divisionFilter')?.value;
        const staff = document.getElementById('staffFilter')?.value;
        const status = document.getElementById('statusFilter')?.value;
        
        if (division) currentUrl.searchParams.set('division', division);
        if (staff) currentUrl.searchParams.set('staff', staff);
        if (status) currentUrl.searchParams.set('status', status);
        
        console.log('Service requests pending approvals request URL:', currentUrl.toString());
        
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
            console.log('Service requests pending approvals response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Service requests pending approvals response data:', data);
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                }
            } else {
                console.error('No HTML data received for service requests pending approvals');
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading service requests pending approvals tab data:', error);
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    // Handle tab switching with AJAX
    document.querySelectorAll('#requestTabs button[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('shown.bs.tab', function (e) {
            const target = e.target.getAttribute('data-bs-target');
            const tabId = target.replace('#', '');
            
            // Load tab data via AJAX
            loadTabData(tabId);
        });
    });
});
</script>
@endsection
