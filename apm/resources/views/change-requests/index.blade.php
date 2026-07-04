@extends('layouts.app')

@section('title', 'Change Requests')

@section('header', 'Change Requests')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('change-requests.pending-approvals') }}" class="btn btn-warning shadow-sm">
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
/* Vertical action buttons */
.btn-group-vertical form.d-inline { display: block !important; }
.btn-group-vertical form .btn { width: 100%; border-radius: 0; }
.btn-group-vertical .btn:first-child { border-top-left-radius: 0.25rem; border-top-right-radius: 0.25rem; }
.btn-group-vertical .btn:last-child { border-bottom-left-radius: 0.25rem; border-bottom-right-radius: 0.25rem; }
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
/* 8-column layout for change requests (division under document #) */
.table th:nth-child(1) { width: 3%; }   /* # */
.table th:nth-child(2) { width: 18%; }  /* Document # + division */
.table th:nth-child(3) { width: 22%; }  /* Title */
.table th:nth-child(4) { width: 14%; }  /* Parent Memo */
.table th:nth-child(5) { width: 8%; }   /* Date Range */
.table th:nth-child(6) { width: 12%; }  /* Changes */
.table th:nth-child(7) { width: 9%; }   /* Status */
.table th:nth-child(8) { width: 12%; }  /* Actions */
.table td:nth-child(2), .table td:nth-child(4) { 
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    line-height: 1.3;
}
#changeRequestFilters select.change-request-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-edit me-2 text-success"></i> Change Request Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="changeRequestFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'myChangeRequests') }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'changeRequestFilters',
                'resetUrl' => route('change-requests.index'),
                'showMemoType' => true,
                'showFundType' => true,
                'statusDomId' => 'statusFilter',
                'searchLabel' => 'Search Activity Title',
                'searchPlaceholder' => 'Enter activity title...',
                'staff' => $staff,
                'divisions' => $divisions,
                'years' => $years,
                'selectedYear' => $selectedYear ?? date('Y'),
                'selectedFundTypeId' => $selectedFundTypeId ?? '',
                'fundTypeFilterOptions' => $fundTypeFilterOptions ?? [],
            ])
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
                    <span class="badge bg-success text-white ms-2" id="badge-myChangeRequests">{{ $myChangeRequests->total() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="myDivisionChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#myDivisionChangeRequests" type="button" role="tab" aria-controls="myDivisionChangeRequests" aria-selected="false">
                    <i class="bx bx-building me-2"></i> My Division CRs
                    <span class="badge bg-info text-white ms-2" id="badge-myDivisionChangeRequests">{{ $myDivisionChangeRequests->total() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sharedChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#sharedChangeRequests" type="button" role="tab" aria-controls="sharedChangeRequests" aria-selected="false">
                    <i class="bx bx-share me-2"></i> Shared CRs
                    <span class="badge bg-warning text-white ms-2" id="badge-sharedChangeRequests">{{ $sharedChangeRequests->total() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allChangeRequests-tab" data-bs-toggle="tab" data-bs-target="#allChangeRequests" type="button" role="tab" aria-controls="allChangeRequests" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All CRs
                        <span class="badge bg-primary text-white ms-2" id="badge-allChangeRequests">{{ $allChangeRequests ? $allChangeRequests->total() : 0 }}</span>
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
function initChangeRequestsPage() {
    if (!document.getElementById('changeRequestTabs')) return;
    var filtersEl = document.getElementById('changeRequestFilters');
    if (!filtersEl) return;
    function applyFilters() {
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            const tabId = activeTab.id;
            loadTabData(tabId);
        }
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'changeRequestFilters') applyFilters();
    });
    
    function getYearValue() {
        const currentYear = String(new Date().getFullYear());
        const el = document.getElementById('year');
        if (!el) return currentYear;
        if (typeof $ !== 'undefined' && $(el).data('select2')) {
            const val = $(el).val();
            return (val !== undefined && val !== null && val !== '') ? String(val) : currentYear;
        }
        const idx = el.selectedIndex;
        if (idx < 0 || !el.options[idx]) return currentYear;
        const v = el.options[idx].value;
        return (v !== undefined && v !== null && v !== '') ? String(v) : currentYear;
    }

    /** Native select or Select2 (hidden native .value is unreliable). */
    function getSelectOrSelect2Value(id) {
        const el = document.getElementById(id);
        if (!el) return '';
        if (typeof $ !== 'undefined' && $(el).data('select2')) {
            const val = $(el).val();
            if (val === undefined || val === null) return '';
            return Array.isArray(val) ? (val[0] != null ? String(val[0]) : '') : String(val);
        }
        return el.value != null ? String(el.value) : '';
    }

    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        const year = getYearValue();
        currentUrl.searchParams.set('year', year);
        const documentNumber = document.getElementById('document_number')?.value;
        const staffId = getSelectOrSelect2Value('staff_id');
        const divisionId = getSelectOrSelect2Value('division_id');
        const status = getSelectOrSelect2Value('statusFilter');
        const memoType = getSelectOrSelect2Value('memo_type');
        const fundTypeId = document.getElementById('fund_type_id')?.value;
        const search = document.getElementById('search')?.value;
        
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        else currentUrl.searchParams.delete('document_number');

        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        else currentUrl.searchParams.delete('staff_id');

        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        else currentUrl.searchParams.delete('division_id');

        if (status) currentUrl.searchParams.set('status', status);
        else currentUrl.searchParams.delete('status');

        if (memoType) currentUrl.searchParams.set('memo_type', memoType);
        else currentUrl.searchParams.delete('memo_type');

        if (fundTypeId) currentUrl.searchParams.set('fund_type_id', fundTypeId);
        else currentUrl.searchParams.delete('fund_type_id');

        if (search) currentUrl.searchParams.set('search', search);
        else currentUrl.searchParams.delete('search');

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
                    attachPaginationHandlers(tabId);
                }
            } else {
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                }
            }
            if (data.count_my_change_requests !== undefined) {
                const b = document.getElementById('badge-myChangeRequests');
                if (b) b.textContent = data.count_my_change_requests;
            }
            if (data.count_my_division !== undefined) {
                const b = document.getElementById('badge-myDivisionChangeRequests');
                if (b) b.textContent = data.count_my_division;
            }
            if (data.count_shared !== undefined) {
                const b = document.getElementById('badge-sharedChangeRequests');
                if (b) b.textContent = data.count_shared;
            }
            if (data.count_all !== undefined) {
                const b = document.getElementById('badge-allChangeRequests');
                if (b) b.textContent = data.count_all;
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

    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
        setTimeout(function() {
            var tabEl = document.getElementById(urlTab + '-tab');
            if (tabEl && ['myChangeRequests', 'myDivisionChangeRequests', 'sharedChangeRequests', 'allChangeRequests'].indexOf(urlTab) !== -1) {
                if (typeof bootstrap !== 'undefined') {
                    document.querySelectorAll('#changeRequestTabs .nav-link').forEach(function(btn) { btn.classList.remove('active'); });
                    document.querySelectorAll('#changeRequestTabsContent .tab-pane').forEach(function(pane) { pane.classList.remove('active', 'show'); });
                    tabEl.classList.add('active');
                    var pane = document.getElementById(tabEl.getAttribute('aria-controls'));
                    if (pane) { pane.classList.add('active', 'show'); loadTabData(pane.id); }
                }
            }
        }, 50);
    }
    var filterTabInput = document.getElementById('filter_tab');
    const tabButtons = document.querySelectorAll('#changeRequestTabs [data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#changeRequestTabs .nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('#changeRequestTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            this.classList.add('active');
            const tabId = this.getAttribute('aria-controls');
            if (filterTabInput) filterTabInput.value = tabId;
            const tabPane = document.getElementById(tabId);
            if (tabPane) tabPane.classList.add('active', 'show');
            loadTabData(tabId);
        });
    });
    window.loadChangeRequestTabData = loadTabData;
    // Do not call loadTabData on initial load when there is no tab in URL — keep server-rendered content so the view shows data. AJAX load only when user switches tab or applies filters.
}
document.addEventListener('DOMContentLoaded', initChangeRequestsPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('changeRequestTabs')) return;
    setTimeout(initChangeRequestsPage, 0);
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
    const deleteBaseUrl = @json(url('change-requests'));
    fetch(`${deleteBaseUrl}/${changeRequestId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        let data = {};
        try { data = JSON.parse(text); } catch (e) {
            data = { success: false, msg: text || `Delete failed (${response.status})` };
        }
        if (!response.ok) {
            data.success = false;
            data.msg = data.msg || `Delete failed (${response.status})`;
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            // Show success message
            if (data.msg) {
                alert(data.msg);
            }
            // Reload the current tab
            const activeTab = document.querySelector('#changeRequestTabs .nav-link.active');
            if (activeTab) {
                if (typeof window.loadChangeRequestTabData === 'function') {
                    window.loadChangeRequestTabData(activeTab.getAttribute('aria-controls'));
                } else {
                    window.location.reload();
                }
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
