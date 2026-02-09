@extends('layouts.app')

@section('title', 'Special Memos')

@section('header', 'Special Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('special-memo.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(get_staff_pending_action_count('special-memo') > 0)
            <span class="badge bg-danger ms-1">{{ get_staff_pending_action_count('special-memo') }}</span>
        @endif
    </a>
    <a href="{{ route('special-memo.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
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
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> Special Memo Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <form action="{{ route('special-memo.index') }}" method="GET" class="row g-3 align-items-end w-100">
                <div class="col-md-2">
                    <label for="year" class="form-label fw-semibold mb-1">
                        <i class="bx bx-calendar me-1 text-success"></i> Year
                    </label>
                    <select name="year" id="year" class="form-select select2" style="width: 100%;">
                        @foreach($years ?? [] as $yr => $label)
                            <option value="{{ $yr }}" {{ (string)($year ?? date('Y')) === (string)$yr ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control" 
                           value="{{ request('document_number') }}" placeholder="Doc #">
                </div>
                <div class="col-md-2">
                    <label for="request_type_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-category me-1 text-success"></i> Request Type
                    </label>
                    <select name="request_type_id" id="request_type_id" class="form-select select2" style="width: 100%;">
                        <option value="">All Request Types</option>
                        @foreach($requestTypes as $requestType)
                            <option value="{{ $requestType->id }}" {{ request('request_type_id') == $requestType->id ? 'selected' : '' }}>
                                {{ $requestType->name }}
                            </option>
                        @endforeach
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
                            <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="special_status" class="form-label fw-semibold mb-1">
                        <i class="bx bx-info-circle me-1 text-success"></i> Status
                    </label>
                    <select name="status" id="special_status" class="form-select select2" style="width: 100%;">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                        <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Returned</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="search" class="form-label fw-semibold mb-1">
                        <i class="bx bx-search me-1 text-success"></i> Search Title
                    </label>
                    <input type="text" name="search" id="search" class="form-control" 
                           value="{{ request('search') }}" placeholder="Enter memo title...">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-success btn-sm w-100" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <a href="{{ route('special-memo.index') }}" class="btn btn-outline-secondary btn-sm w-100 fw-bold">
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
                    <i class="bx bx-file-alt me-2"></i> My Submitted Special Memos
                    <span class="badge bg-success text-white ms-2">{{ $mySubmittedMemos->total() ?? 0 }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sharedMemos-tab" data-bs-toggle="tab" data-bs-target="#sharedMemos" type="button" role="tab" aria-controls="sharedMemos" aria-selected="false">
                    <i class="bx bx-share me-2"></i> Shared Special Memos
                    <span class="badge bg-info text-white ms-2">{{ $sharedMemos->total() ?? 0 }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allMemos-tab" data-bs-toggle="tab" data-bs-target="#allMemos" type="button" role="tab" aria-controls="allMemos" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Special Memos
                        <span class="badge bg-primary text-white ms-2">{{ $allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator ? $allMemos->total() : $allMemos->count() }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="memoTabsContent">
            <!-- My Submitted Special Memos Tab -->
            <div class="tab-pane fade show active" id="mySubmitted" role="tabpanel" aria-labelledby="mySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Special Memos
                            </h6>
                            <small class="text-muted">All special memos you have submitted</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('special-memo.export.my-submitted', request()->query()) }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>

                    @include('special-memo.partials.my-submitted-tab')
                </div>
            </div>

            <!-- All Special Memos Tab -->
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="allMemos" role="tabpanel" aria-labelledby="allMemos-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Special Memos
                                </h6>
                                <small class="text-muted">All special memos in the system</small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('special-memo.export.all', request()->query()) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to Excel
                                </a>
                            </div>
                        </div>
                        
                        @include('special-memo.partials.all-memos-tab')
                    </div>
                </div>
            @endif

            <!-- Shared Special Memos Tab -->
            <div class="tab-pane fade" id="sharedMemos" role="tabpanel" aria-labelledby="sharedMemos-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-share me-2"></i> Shared Special Memos
                            </h6>
                            <small class="text-muted">Special memos where you have been added as a participant by other staff</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('special-memo.export.shared', request()->query()) }}" class="btn btn-outline-info btn-sm">
                                <i class="bx bx-download me-1"></i> Export to Excel
                            </a>
                        </div>
                    </div>
                    
                    @include('special-memo.partials.shared-memos-tab')
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Defer so Select2 has updated the underlying select
    function applyFilters() {
        setTimeout(function() {
            var activeTab = document.querySelector('.tab-pane.active');
            if (activeTab) loadTabData(activeTab.id);
        }, 0);
    }
    
    if (document.getElementById('applyFilters')) {
        document.getElementById('applyFilters').addEventListener('click', applyFilters);
    }
    ['year', 'request_type_id', 'staff_id', 'division_id', 'special_status'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', applyFilters);
    });
    if (document.getElementById('document_number')) {
        var documentNumberTimeout;
        document.getElementById('document_number').addEventListener('input', function() {
            clearTimeout(documentNumberTimeout);
            documentNumberTimeout = setTimeout(applyFilters, 1000);
        });
        document.getElementById('document_number').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { clearTimeout(documentNumberTimeout); applyFilters(); }
        });
    }
    
    function getYearValue() {
        var currentYear = String(new Date().getFullYear());
        if (typeof $ !== 'undefined' && $('#year').length) {
            var jqVal = $('#year').val();
            if (jqVal != null && jqVal !== '') return String(jqVal).trim();
        }
        var sel = document.getElementById('year');
        if (!sel) return currentYear;
        var idx = sel.selectedIndex;
        if (idx < 0 || !sel.options[idx]) return currentYear;
        var v = (sel.options[idx].value || '').trim();
        return v || currentYear;
    }
    
    function loadTabData(tabId, page) {
        page = page || 1;
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        var year = getYearValue();
        var documentNumber = (document.getElementById('document_number') && document.getElementById('document_number').value) ? document.getElementById('document_number').value.trim() : '';
        var requestTypeId = document.getElementById('request_type_id') ? (document.getElementById('request_type_id').value || '') : '';
        var staffId = document.getElementById('staff_id') ? (document.getElementById('staff_id').value || '') : '';
        var divisionId = document.getElementById('division_id') ? (document.getElementById('division_id').value || '') : '';
        var status = document.getElementById('special_status') ? (document.getElementById('special_status').value || '') : '';
        var search = document.getElementById('search') ? (document.getElementById('search').value || '').trim() : '';
        
        currentUrl.searchParams.set('year', year);
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        if (requestTypeId) currentUrl.searchParams.set('request_type_id', requestTypeId);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (status) currentUrl.searchParams.set('status', status);
        if (search) currentUrl.searchParams.set('search', search);
        
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
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.html && tabContent) {
                tabContent.innerHTML = data.html;
                attachPaginationHandlers(tabId);
            } else if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
            }
            if (data.count_my_submitted !== undefined) {
                var b = document.querySelector('#mySubmitted-tab .badge');
                if (b) b.textContent = data.count_my_submitted;
            }
            if (data.count_shared_memos !== undefined) {
                var b = document.querySelector('#sharedMemos-tab .badge');
                if (b) b.textContent = data.count_shared_memos;
            }
            if (data.count_all_memos !== undefined) {
                var b = document.querySelector('#allMemos-tab .badge');
                if (b) b.textContent = data.count_all_memos;
            }
        })
        .catch(error => {
            console.error('Error loading special memo tab data:', error);
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
