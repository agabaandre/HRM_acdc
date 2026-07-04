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

        <div class="row g-3 align-items-end w-100" id="serviceFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'serviceFilters',
                'resetUrl' => route('service-requests.index'),
                'showServiceType' => true,
                'statusDomId' => 'request_status',
                'searchLabel' => 'Search Activity Title',
                'searchPlaceholder' => 'Enter activity title...',
                'staff' => $staff,
                'divisions' => $divisions,
                'years' => $years,
                'selectedYear' => $selectedYear ?? date('Y'),
            ])
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
                    <span class="badge bg-success text-white ms-2" id="badge-mySubmitted">{{ $mySubmittedRequests->total() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="false">
                    <i class="bx bx-building me-2"></i> My Division Requests
                    <span class="badge bg-info text-white ms-2" id="badge-myDivision">{{ $myDivisionRequests->total() }}</span>
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

            <div class="tab-pane fade" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-building me-2"></i> My Division Service Requests
                            </h6>
                            <small class="text-muted">All service requests in your division (latest first)</small>
                        </div>
                    </div>
                    @include('service-requests.partials.my-division-tab')
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
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'serviceFilters') applyFilters();
    });

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
        const frag = window.APMListFragment;
        if (frag && frag.applyFilterValues) {
            frag.applyFilterValues(currentUrl, {
                division_id: document.getElementById('division_id')?.value,
                staff_id: document.getElementById('staff_id')?.value,
                service_type: document.getElementById('service_type')?.value,
                status: document.getElementById('request_status')?.value,
                search: document.getElementById('search')?.value,
                fund_type_id: document.getElementById('fund_type_id')?.value,
            });
        }

        window.history.replaceState({}, '', currentUrl.toString());

        const tabContent = document.getElementById(tabId);
        if (tabContent) {
            tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }

        fetch(currentUrl.toString(), {
            method: 'GET',
            headers: (window.APMListFragment && window.APMListFragment.headers) ? window.APMListFragment.headers() : {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-APM-List-Fragment': '1'
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
            if (data.count_my_division !== undefined) {
                const b = document.getElementById('badge-myDivision');
                if (b) b.textContent = data.count_my_division;
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
    if (urlTab && (urlTab === 'mySubmitted' || urlTab === 'myDivision' || urlTab === 'allRequests')) {
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
