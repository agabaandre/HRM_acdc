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
                        
                        @include('activities.single-memos.partials.pending-approvals-tab')
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
                        
                        @include('activities.single-memos.partials.approved-by-me-tab')
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
        if (document.getElementById('requestTypeFilter')) {
            document.getElementById('requestTypeFilter').addEventListener('change', applyFilters);
        }
        
        if (document.getElementById('divisionFilter')) {
            document.getElementById('divisionFilter').addEventListener('change', applyFilters);
        }
        
        if (document.getElementById('staffFilter')) {
            document.getElementById('staffFilter').addEventListener('change', applyFilters);
        }

        // Function to load tab data via AJAX
        function loadTabData(tabId, page = 1) {
            console.log('Loading pending approvals tab data for:', tabId, 'page:', page);
            
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('page', page);
            currentUrl.searchParams.set('tab', tabId);
            
            // Include current filter values
            const requestType = document.getElementById('requestTypeFilter')?.value;
            const division = document.getElementById('divisionFilter')?.value;
            const staff = document.getElementById('staffFilter')?.value;
            
            if (requestType) currentUrl.searchParams.set('request_type', requestType);
            if (division) currentUrl.searchParams.set('division', division);
            if (staff) currentUrl.searchParams.set('staff', staff);
            
            console.log('Pending approvals request URL:', currentUrl.toString());
            
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
                console.log('Pending approvals response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Pending approvals response data:', data);
                if (data.html) {
                    if (tabContent) {
                        tabContent.innerHTML = data.html;
                        attachPaginationHandlers(tabId);
                    }
                } else {
                    console.error('No HTML data received for pending approvals');
                    if (tabContent) {
                        tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading pending approvals tab data:', error);
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
                document.querySelectorAll('#memoTabs .nav-link').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('#memoTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
                
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
        const activeTabButton = document.querySelector('#memoTabs .nav-link.active');
        if (activeTabButton) {
            loadTabData(activeTabButton.getAttribute('aria-controls'));
        }
    });
    </script>
@endsection
