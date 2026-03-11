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
/* Vertical action buttons */
.btn-group-vertical form.d-inline { display: block !important; }
.btn-group-vertical form .btn { width: 100%; border-radius: 0; }
.btn-group-vertical .btn:first-child { border-top-left-radius: 0.25rem; border-top-right-radius: 0.25rem; }
.btn-group-vertical .btn:last-child { border-bottom-left-radius: 0.25rem; border-bottom-right-radius: 0.25rem; }
#serviceFilters select.service-request-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
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

        <form action="{{ route('service-requests.index') }}" method="GET" class="row g-3 align-items-end w-100" id="serviceFiltersForm">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
            <div class="row g-3 align-items-end w-100" id="serviceFilters" autocomplete="off">
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
                <label for="division_id" class="form-label fw-semibold mb-1">
                    <i class="bx bx-building me-1 text-success"></i> Division
                </label>
                <select name="division_id" id="division_id" class="form-select apm-filter-select service-request-filter-select">
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
                <select name="staff_id" id="staff_id" class="form-select apm-filter-select service-request-filter-select">
                    <option value="">All Staff</option>
                    @foreach($staff as $member)
                        <option value="{{ $member->staff_id }}" {{ request('staff_id') == $member->staff_id ? 'selected' : '' }}>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="service_type" class="form-label fw-semibold mb-1">
                    <i class="bx bx-cog me-1 text-success"></i> Service Type
                </label>
                <select name="service_type" id="service_type" class="form-select apm-filter-select service-request-filter-select">
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
                <select name="status" id="request_status" class="form-select apm-filter-select service-request-filter-select">
                    <option value="">All Statuses</option>
                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="returned" {{ request('status') == 'returned' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-success btn-sm w-100 fw-bold" id="applyFilters">
                    <i class="bx bx-search-alt-2 me-1"></i> Filter
                </button>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <a wire:navigate href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary btn-sm w-100 fw-bold" title="Reset Filters">
                    <i class="bx bx-reset me-1"></i> Reset
                </a>
            </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="serviceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted Requests
                    <span class="badge bg-success text-white ms-2" id="badge-mySubmitted">{{ $mySubmittedRequests->total() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allRequests-tab" data-bs-toggle="tab" data-bs-target="#allRequests" type="button" role="tab" aria-controls="allRequests" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Service Requests
                        <span class="badge bg-primary text-white ms-2" id="badge-allRequests">{{ $allRequests ? $allRequests->total() : 0 }}</span>
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
function initServiceRequestsPage() {
    if (!document.getElementById('serviceTabs')) return;
    var filtersEl = document.getElementById('serviceFilters');
    if (!filtersEl) return;
    if (window.APMFilters) {
        APMFilters.clearInited('#serviceFilters');
        APMFilters.init('#serviceFilters', {
            fields: [
                { param: 'year', id: 'year', default: APMFilters.currentYear },
                { param: 'division_id', id: 'division_id' },
                { param: 'staff_id', id: 'staff_id' },
                { param: 'service_type', id: 'service_type' },
                { param: 'status', id: 'request_status' },
                { param: 'search', id: 'search' }
            ],
            tabParam: 'filter_tab',
            tabDefault: 'mySubmitted',
            selectSelector: '.apm-filter-select'
        });
    }
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    
    if (document.getElementById('applyFilters')) {
        document.getElementById('applyFilters').addEventListener('click', function(e) { e.preventDefault(); applyFilters(); });
    }
    var form = document.getElementById('serviceFiltersForm');
    if (form) form.addEventListener('submit', function(e) { e.preventDefault(); applyFilters(); });
    
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

    if (document.getElementById('year')) {
        document.getElementById('year').addEventListener('change', function() {
            setTimeout(applyFilters, 0);
        });
    }

    if (document.getElementById('search')) {
        document.getElementById('search').addEventListener('input', applyFilters);
    }

    function getYearValue() {
        const el = document.getElementById('year');
        if (!el) return new Date().getFullYear().toString();
        if (typeof $ !== 'undefined' && $(el).data('select2')) {
            const val = $(el).val();
            return (val !== undefined && val !== null && val !== '') ? String(val) : new Date().getFullYear().toString();
        }
        const idx = el.selectedIndex;
        if (idx < 0 || !el.options[idx]) return new Date().getFullYear().toString();
        const v = el.options[idx].value;
        return (v !== undefined && v !== null && v !== '') ? String(v) : new Date().getFullYear().toString();
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', tabId);
        const year = getYearValue();
        currentUrl.searchParams.set('year', year);
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

        window.history.replaceState({}, '', currentUrl.toString());

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
        .then(response => response.json())
        .then(data => {
            if (data.html) {
                if (tabContent) {
                    tabContent.innerHTML = data.html;
                }
            } else {
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
            if (data.count_my_submitted !== undefined) {
                const b = document.getElementById('badge-mySubmitted');
                if (b) b.textContent = data.count_my_submitted;
            }
            if (data.count_all_requests !== undefined) {
                const b = document.getElementById('badge-allRequests');
                if (b) b.textContent = data.count_all_requests;
            }
        })
        .catch(error => {
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab && (urlTab === 'mySubmitted' || urlTab === 'allRequests')) {
        setTimeout(function() {
            var tabEl = document.getElementById(urlTab + '-tab');
            if (tabEl && typeof bootstrap !== 'undefined') {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }, 50);
    }
    var filterTabInput = document.getElementById('filter_tab');
    document.querySelectorAll('#serviceTabs button[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('click', function(e) {
            var tabId = this.getAttribute('aria-controls');
            if (filterTabInput) filterTabInput.value = tabId;
        });
        button.addEventListener('shown.bs.tab', function (e) {
            const target = e.target.getAttribute('data-bs-target');
            const tabId = target.replace('#', '');
            loadTabData(tabId);
        });
    });
}
document.addEventListener('DOMContentLoaded', initServiceRequestsPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('serviceTabs')) return;
    setTimeout(initServiceRequestsPage, 0);
});
</script>
@endsection
