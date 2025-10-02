@extends('layouts.app')

@section('title', 'Service Requests')

@section('styles')
<style>
.status-badge-wrap {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    display: inline-block !important;
    max-width: 100% !important;
    line-height: 1.3 !important;
}

.status-badge-wrap .badge {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    display: inline-block !important;
    max-width: 100% !important;
    line-height: 1.3 !important;
}
</style>
@endsection

@section('header', 'Service Requests')

@section('header-actions')
<!-- Create functionality removed - requests will be handled from activities -->
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-cog me-2 text-success"></i> Service Request Management</h4>
        </div>

        <div class="row g-3 align-items-end w-100" id="serviceFilters" autocomplete="off">
            <div class="col-md-2">
                <label for="division_id" class="form-label fw-semibold mb-1">
                    <i class="bx bx-building me-1 text-success"></i> Division
                </label>
                <select name="division_id" id="division_id" class="form-select">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                            {{ $division->division_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="staff_id" class="form-label fw-semibold mb-1">
                    <i class="bx bx-user me-1 text-success"></i> Staff
                </label>
                <select name="staff_id" id="staff_id" class="form-select">
                    <option value="">All Staff</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="service_type" class="form-label fw-semibold mb-1">
                    <i class="bx bx-cog me-1 text-success"></i> Service Type
                </label>
                <select name="service_type" id="service_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="IT Support" {{ request('service_type') == 'IT Support' ? 'selected' : '' }}>IT Support</option>
                    <option value="Maintenance" {{ request('service_type') == 'Maintenance' ? 'selected' : '' }}>Maintenance</option>
                    <option value="Other" {{ request('service_type') == 'Other' ? 'selected' : '' }}>Other</option>
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
                <label for="request_status" class="form-label fw-semibold mb-1">
                    <i class="bx bx-info-circle me-1 text-success"></i> Status
                </label>
                <select name="status" id="request_status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                    <i class="bx bx-search-alt-2 me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
                    <i class="bx bx-reset me-1"></i> Reset
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="serviceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted Requests
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedRequests->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allRequests-tab" data-bs-toggle="tab" data-bs-target="#allRequests" type="button" role="tab" aria-controls="allRequests" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Service Requests
                        <span class="badge bg-primary text-white ms-2">{{ $allRequests->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="serviceTabsContent">
            <!-- My Submitted Requests Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Service Requests
                            </h6>
                            <small class="text-muted">All service requests you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('service-requests.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @include('service-requests.partials.my-submitted-tab')
                </div>
            </div>

            <!-- All Service Requests Tab -->
            @if(in_array(87, user_session('permissions', [])))
            <div class="tab-pane fade" id="allRequests" role="tabpanel" aria-labelledby="allRequests-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-grid me-2"></i> All Service Requests
                            </h6>
                            <small class="text-muted">All service requests in the system</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('service-requests.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @include('service-requests.partials.all-requests-tab')
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('service_type')) {
        document.getElementById('service_type').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('request_status')) {
        document.getElementById('request_status').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('search')) {
        document.getElementById('search').addEventListener('input', applyFilters);
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId) {
        console.log('Loading service requests tab data for:', tabId);
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const divisionId = document.getElementById('division_id')?.value;
        const staffId = document.getElementById('staff_id')?.value;
        const serviceType = document.getElementById('service_type')?.value;
        const status = document.getElementById('request_status')?.value;
        const search = document.getElementById('search')?.value;
        
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (serviceType) currentUrl.searchParams.set('service_type', serviceType);
        if (status) currentUrl.searchParams.set('status', status);
        if (search) currentUrl.searchParams.set('search', search);
        
        console.log('Service requests request URL:', currentUrl.toString());
        
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
            console.log('Service requests response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Service requests response data:', data);
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                }
            } else {
                console.error('No HTML data received for service requests');
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading service requests tab data:', error);
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    // Handle tab switching with AJAX
    document.querySelectorAll('#serviceTabs button[data-bs-toggle="tab"]').forEach(button => {
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
