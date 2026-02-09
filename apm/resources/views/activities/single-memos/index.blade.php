@extends('layouts.app')

@section('title', 'Single Memos')

@section('header', 'Single Memos')

@section('header-actions')

@endsection

@section('content')
<style>
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
    
    /* Both tabs now use the same 9-column layout */
    .table th:nth-child(1) { width: 3%; }   /* # - reduced from 5% */
    .table th:nth-child(2) { width: 16%; }  /* Document # - increased by 4% from 12% */
    .table th:nth-child(3) { width: 20%; }  /* Title */
    .table th:nth-child(4) { width: 12%; }  /* Responsible Person */
    .table th:nth-child(5) { width: 15%; }  /* Division - with text wrapping */
    .table th:nth-child(6) { width: 7%; }   /* Date Range - reduced by 5% from 12% */
    .table th:nth-child(7) { width: 10%; }  /* Fund Type */
    .table th:nth-child(8) { width: 8%; }   /* Status */
    .table th:nth-child(9) { width: 11%; }  /* Actions - increased by 5% from 6% */
    
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
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-doc me-2 text-success"></i> Single Memo Management</h4>
                    </div>

        <!-- Search Row -->
        <div class="row g-3 mb-3">
            <div class="col-12">
                <label for="search" class="form-label fw-semibold mb-1">
                    <i class="bx bx-search me-1 text-success"></i> Search Single Memo Title
                </label>
                <input type="text" name="search" id="search" class="form-control" 
                       value="{{ $searchTerm ?? '' }}" placeholder="Enter single memo title to search...">
            </div>
        </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <form action="{{ route('activities.single-memos.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Enter document number">
                </div>
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff/Responsible Person
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
                <div class="col-md-1">
                    <label for="year" class="form-label fw-semibold mb-1">
                        <i class="bx bx-calendar me-1 text-success"></i> Year
                    </label>
                    <select name="year" id="year" class="form-select select2" style="width: 100%;">
                        @foreach($years as $year)
                            <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label for="quarter" class="form-label fw-semibold mb-1">
                        <i class="bx bx-time-five me-1 text-success"></i> Quarter
                    </label>
                    <select name="quarter" id="quarter" class="form-select select2" style="width: 100%;">
                        @foreach($quarters as $quarter)
                            <option value="{{ $quarter }}" {{ $selectedQuarter == $quarter ? 'selected' : '' }}>
                                {{ $quarter }}
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
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary w-100 fw-bold">
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
                    <i class="bx bx-file-doc me-2"></i> My Division Single Memos
                    <span class="badge bg-success text-white ms-2" id="badge-mySubmitted">{{ $myMemos->total() }}</span>
                    </button>
                </li>
                @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                <button class="nav-link" id="allMemos-tab" data-bs-toggle="tab" data-bs-target="#allMemos" type="button" role="tab" aria-controls="allMemos" aria-selected="false">
                    <i class="bx bx-grid me-2"></i> All Single Memos
                    <span class="badge bg-primary text-white ms-2" id="badge-allMemos">{{ $allMemos ? $allMemos->total() : 0 }}</span>
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                <button class="nav-link" id="sharedMemos-tab" data-bs-toggle="tab" data-bs-target="#sharedMemos" type="button" role="tab" aria-controls="sharedMemos" aria-selected="false">
                    <i class="bx bx-share me-2"></i> Shared Single Memos
                    <span class="badge bg-info text-white ms-2" id="badge-sharedMemos">{{ $sharedMemos->total() }}</span>
                    </button>
                </li>
            </ul>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif
        
        <!-- Tab Content -->
        <div class="tab-content" id="memoTabsContent">
            <!-- My Single Memos Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    @include('activities.single-memos.partials.my-division-memos-tab')
                </div>
            </div>
            
            <!-- All Single Memos Tab -->
            @if(in_array(87, user_session('permissions', [])))
            <div class="tab-pane fade" id="allMemos" role="tabpanel" aria-labelledby="allMemos-tab">
                <div class="p-3">
                    @include('activities.single-memos.partials.all-memos-tab')
                        </div>
                    </div>
                                                @endif
            
            <!-- Shared Single Memos Tab -->
            <div class="tab-pane fade" id="sharedMemos" role="tabpanel" aria-labelledby="sharedMemos-tab">
                <div class="p-3">
                    @include('activities.single-memos.partials.shared-memos-tab')
                </div>
            </div>
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
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('statusFilter')) {
        document.getElementById('statusFilter').addEventListener('change', applyFilters);
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
    
    // Handle tab switching based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam) {
        // Wait for DOM to be fully loaded, then switch to the appropriate tab
        setTimeout(() => {
            switch(tabParam) {
                case 'all':
                    const allTab = document.getElementById('allMemos-tab');
                    if (allTab) {
                        // Use Bootstrap's tab API to properly switch
                        const tab = new bootstrap.Tab(allTab);
                        tab.show();
                    }
                    break;
                case 'my-division':
                    const myDivisionTab = document.getElementById('mySubmitted-tab');
                    if (myDivisionTab) {
                        const tab = new bootstrap.Tab(myDivisionTab);
                        tab.show();
                    }
                    break;
                case 'shared':
                    const sharedTab = document.getElementById('sharedMemos-tab');
                    if (sharedTab) {
                        const tab = new bootstrap.Tab(sharedTab);
                        tab.show();
                    }
                    break;
            }
        }, 100);
        
        // Remove the tab parameter from URL after switching to avoid confusion
        const newUrl = new URL(window.location);
        newUrl.searchParams.delete('tab');
        window.history.replaceState({}, '', newUrl);
    }
    
    // Add click handlers to tabs to reset pagination when switching
    const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent Bootstrap's default tab behavior
            
            // Remove active class from all tabs and buttons
            document.querySelectorAll('.nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            
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
    
    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const staffId = document.getElementById('staff_id')?.value;
        const divisionId = document.getElementById('division_id')?.value;
        const status = document.getElementById('statusFilter')?.value;
        const documentNumber = document.getElementById('document_number')?.value;
        const search = document.getElementById('search')?.value;
        const year = document.getElementById('year')?.value;
        const quarter = document.getElementById('quarter')?.value;
        
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (status) currentUrl.searchParams.set('status', status);
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        if (search) currentUrl.searchParams.set('search', search);
        if (year) currentUrl.searchParams.set('year', year);
        if (quarter) currentUrl.searchParams.set('quarter', quarter);

        // Show loading indicator
        const tabContent = document.getElementById(tabId);
        if (tabContent) {
            tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }
        
        // Make AJAX request
        fetch(currentUrl.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                    attachPaginationHandlers(tabId);
                }
            } else {
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
            if (data.count_my_division !== undefined) {
                const b = document.getElementById('badge-mySubmitted');
                if (b) b.textContent = data.count_my_division;
            }
            if (data.count_all_memos !== undefined) {
                const b = document.getElementById('badge-allMemos');
                if (b) b.textContent = data.count_all_memos;
            }
            if (data.count_shared_memos !== undefined) {
                const b = document.getElementById('badge-sharedMemos');
                if (b) b.textContent = data.count_shared_memos;
            }
        })
        .catch(error => {
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    // Function to attach pagination click handlers
    function attachPaginationHandlers(tabId) {
        const tabContent = document.getElementById(tabId);
        if (!tabContent) return;
        
        // Find pagination links within this tab
        const paginationLinks = tabContent.querySelectorAll('.pagination a');
        paginationLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Extract page number from URL
                const url = new URL(this.href);
                const page = url.searchParams.get('page') || 1;
                
                // Load tab data with new page
                loadTabData(tabId, page);
            });
        });
    }
    
    // Attach initial pagination handlers for all tabs
    attachPaginationHandlers('mySubmitted');
    attachPaginationHandlers('allMemos');
    attachPaginationHandlers('sharedMemos');
});
</script>
@endsection

