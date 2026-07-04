@extends('layouts.app')

@section('title', 'ActRF')

@section('header', 'Request for ARF')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('request-arf.pending-approvals') }}" class="btn btn-warning shadow-sm">
        <i class="bx bx-time me-1"></i> Pending Approvals
        @if(($pendingArfCount ?? 0) > 0)
            <span class="badge bg-danger ms-1">{{ $pendingArfCount }}</span>
        @endif
    </a>
</div>
@endsection

@section('content')
<style>
#arfFilters select.arf-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> ARF Request Management</h4>
        </div>

        <div class="row g-3 align-items-end w-100" id="arfFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'arfFilters',
                'resetUrl' => route('request-arf.index'),
                'showOverallStatus' => true,
                'searchLabel' => 'Search title',
                'searchPlaceholder' => 'Enter ARF title...',
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
        <ul class="nav nav-tabs nav-fill" id="arfTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mySubmitted-tab" data-bs-toggle="tab" data-bs-target="#mySubmitted" type="button" role="tab" aria-controls="mySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted ARFs
                    <span class="badge bg-success text-white ms-2" id="badge-mySubmitted">{{ $mySubmittedArfs->total() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="false">
                    <i class="bx bx-building me-2"></i> My Division ARFs
                    <span class="badge bg-info text-white ms-2" id="badge-myDivision">{{ $myDivisionArfs->total() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allArfs-tab" data-bs-toggle="tab" data-bs-target="#allArfs" type="button" role="tab" aria-controls="allArfs" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All ARF Requests
                        <span class="badge bg-primary text-white ms-2" id="badge-allArfs">{{ $allArfs instanceof \Illuminate\Pagination\LengthAwarePaginator ? $allArfs->total() : 0 }}</span>
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

            <div class="tab-pane fade" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-building me-2"></i> My Division ARF Requests
                            </h6>
                            <small class="text-muted">All ARF requests in your division (latest first)</small>
                        </div>
                    </div>
                    @include('request-arf.partials.my-division-tab')
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
function initRequestArfPage() {
    if (!document.getElementById('arfTabs')) return;
    var filtersEl = document.getElementById('arfFilters');
    if (!filtersEl) return;
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'arfFilters') applyFilters();
    });

    function getYearValue() {
        const el = document.getElementById('year');
        if (!el) return new Date().getFullYear().toString();
        const idx = el.selectedIndex;
        if (idx < 0 || !el.options[idx]) return new Date().getFullYear().toString();
        const v = el.options[idx].value;
        return (v !== undefined && v !== null && v !== '') ? String(v) : new Date().getFullYear().toString();
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', tabId);
        currentUrl.searchParams.set('year', getYearValue());
        const frag = window.APMListFragment;
        if (frag && frag.applyFilterValues) {
            frag.applyFilterValues(currentUrl, {
                document_number: document.getElementById('document_number')?.value,
                division_id: document.getElementById('division_id')?.value,
                staff_id: document.getElementById('staff_id')?.value,
                overall_status: document.getElementById('overall_status')?.value,
                search: document.getElementById('search')?.value,
                fund_type_id: document.getElementById('fund_type_id')?.value,
            });
        }

        window.history.replaceState({}, '', currentUrl.toString());

        // Show loading indicator
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
            if (data.count_all_arfs !== undefined) {
                const b = document.getElementById('badge-allArfs');
                if (b) b.textContent = data.count_all_arfs;
            }
        })
        .catch(error => {
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab && (urlTab === 'mySubmitted' || urlTab === 'myDivision' || urlTab === 'allArfs')) {
        setTimeout(function() {
            var tabEl = document.getElementById(urlTab + '-tab');
            if (tabEl && typeof bootstrap !== 'undefined') {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }, 50);
    }
    var filterTabInput = document.getElementById('filter_tab');
    document.querySelectorAll('#arfTabs button[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('click', function() {
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
document.addEventListener('DOMContentLoaded', initRequestArfPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('arfTabs')) return;
    setTimeout(initRequestArfPage, 0);
});
</script>
@endsection
