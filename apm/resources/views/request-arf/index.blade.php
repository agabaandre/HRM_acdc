@extends('layouts.app')

@section('title', 'ActRF')

@section('header', 'Request for ARF')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('request-arf.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(get_pending_arf_count(user_session('staff_id')) > 0)
            <span class="badge bg-danger ms-1">{{ get_pending_arf_count(user_session('staff_id')) }}</span>
        @endif
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> ARF Request Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="arfFilters" autocomplete="off">
                <div class="col-md-2">
                    <label for="year" class="form-label fw-semibold mb-1">
                        <i class="bx bx-calendar me-1 text-success"></i> Year
                    </label>
                    <select name="year" id="year" class="form-select" style="width: 100%;">
                        @foreach($years ?? [] as $yr => $label)
                            <option value="{{ $yr }}" {{ (string)($selectedYear ?? date('Y')) === (string)$yr ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-hash me-1 text-success"></i> Document Number
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Search by doc number...">
                </div>
                <div class="col-md-2">
                    <label for="division_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-building me-1 text-success"></i> Division</label>
                    <select name="division_id" id="division_id" class="form-select">
                        <option value="">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="staff_id" class="form-label fw-semibold mb-1"><i
                            class="bx bx-user me-1 text-success"></i> Staff</label>
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
                    <label for="search" class="form-label fw-semibold mb-1">
                        <i class="bx bx-search me-1 text-success"></i> Search Title
                    </label>
                    <input type="text" name="search" id="search" class="form-control" 
                           value="{{ request('search') }}" placeholder="Enter ARF title...">
                </div>
                <div class="col-md-2">
                    <label for="overall_status" class="form-label fw-semibold mb-1"><i
                            class="bx bx-info-circle me-1 text-success"></i> Status</label>
                    <select name="overall_status" id="overall_status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('overall_status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('overall_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="returned" {{ request('overall_status') == 'returned' ? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-success btn-sm w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <a href="{{ route('request-arf.index') }}" class="btn btn-outline-secondary btn-sm w-100 fw-bold" title="Reset Filters">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </div>
            </div>
        </div>
    </div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="arfTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted ARFs
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedArfs->count() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allArfs-tab" data-bs-toggle="tab" data-bs-target="#allArfs" type="button" role="tab" aria-controls="allArfs" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All ARF Requests
                        <span class="badge bg-primary text-white ms-2">{{ $allArfs->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="arfTabsContent">
            <!-- My Submitted ARFs Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted ARF Requests
                            </h6>
                            <small class="text-muted">All ARF requests you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('request-arf.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @include('request-arf.partials.my-submitted-tab')
                </div>
            </div>

            <!-- All ARF Requests Tab -->
            @if(in_array(87, user_session('permissions', [])))
            <div class="tab-pane fade" id="allArfs" role="tabpanel" aria-labelledby="allArfs-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-grid me-2"></i> All ARF Requests
                            </h6>
                            <small class="text-muted">All ARF requests in the system</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('request-arf.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @include('request-arf.partials.all-arfs-tab')
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
    
    if (document.getElementById('overall_status')) {
        document.getElementById('overall_status').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('search')) {
        document.getElementById('search').addEventListener('input', applyFilters);
    }
    
    if (document.getElementById('year')) {
        document.getElementById('year').addEventListener('change', applyFilters);
    }

    if (document.getElementById('document_number')) {
        document.getElementById('document_number').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        // Also trigger on input for real-time search
        document.getElementById('document_number').addEventListener('input', function() {
            clearTimeout(window.documentNumberTimeout);
            window.documentNumberTimeout = setTimeout(applyFilters, 500);
        });
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId) {
        console.log('Loading ARF requests tab data for:', tabId);
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const year = document.getElementById('year')?.value;
        const documentNumber = document.getElementById('document_number')?.value;
        const divisionId = document.getElementById('division_id')?.value;
        const staffId = document.getElementById('staff_id')?.value;
        const status = document.getElementById('overall_status')?.value;
        const search = document.getElementById('search')?.value;

        if (year) currentUrl.searchParams.set('year', year);
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (status) currentUrl.searchParams.set('overall_status', status);
        if (search) currentUrl.searchParams.set('search', search);
        
        console.log('ARF requests request URL:', currentUrl.toString());
        
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
            console.log('ARF requests response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('ARF requests response data:', data);
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                }
            } else {
                console.error('No HTML data received for ARF requests');
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
        })
        .catch(error => {
            console.error('Error loading ARF requests tab data:', error);
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    // Handle tab switching with AJAX
    document.querySelectorAll('#arfTabs button[data-bs-toggle="tab"]').forEach(button => {
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
