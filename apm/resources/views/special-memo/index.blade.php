@extends('layouts.app')

@section('title', 'Special Memos')

@section('header', 'Special Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('special-memo.pending-approvals') }}" class="btn btn-warning shadow-sm">
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
}
/* Vertical action buttons: stack full width */
.btn-group-vertical form.d-inline {
    display: block !important;
}
.btn-group-vertical form .btn {
    width: 100%;
    border-radius: 0;
}
.btn-group-vertical .btn:first-child {
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
}
.btn-group-vertical .btn:last-child,
.btn-group-vertical form .btn {
    border-bottom-left-radius: 0.25rem;
    border-bottom-right-radius: 0.25rem;
}
#memoFilters select.special-memo-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
/* Match single-memos list: fixed columns, title + fund type widths */
.special-memo-index-table {
    table-layout: fixed;
}
.table-title-cell {
    max-width: 270px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    line-height: 1.4;
}
.fund-type-cell {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    vertical-align: top;
    line-height: 1.3;
}
.fund-type-cell .badge {
    white-space: normal;
    display: inline-block;
    max-width: 100%;
    line-height: 1.35;
    text-align: left;
}
.special-memo-index-table td.fund-type-cell,
.special-memo-index-table td.table-title-cell {
    vertical-align: top;
}
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2 text-success"></i> Special Memo Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'memoFilters',
                'resetUrl' => route('special-memo.index'),
                'showRequestType' => true,
                'statusDomId' => 'special_status',
                'searchLabel' => 'Search title',
                'searchPlaceholder' => 'Enter memo title...',
                'staff' => $staff,
                'divisions' => $divisions,
                'years' => $years,
                'requestTypes' => $requestTypes,
                'selectedYear' => $year ?? date('Y'),
            ])
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
                <button class="nav-link" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="false">
                    <i class="bx bx-building me-2"></i> My Division Memos
                    <span class="badge bg-info text-white ms-2">{{ $myDivisionMemos->total() ?? 0 }}</span>
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

            <div class="tab-pane fade" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-info fw-bold">
                                <i class="bx bx-building me-2"></i> My Division Special Memos
                            </h6>
                            <small class="text-muted">All special memos in your division (latest first)</small>
                        </div>
                    </div>
                    @include('special-memo.partials.my-division-memos-tab')
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
function initSpecialMemoPage() {
    if (!document.getElementById('memoTabs')) return;
    var filtersEl = document.getElementById('memoFilters');
    if (!filtersEl) return;
    function applyFilters() {
        setTimeout(function() {
            var activeTab = document.querySelector('#memoTabsContent .tab-pane.active');
            if (activeTab) loadTabData(activeTab.id);
        }, 0);
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'memoFilters') applyFilters();
    });
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
        currentUrl.searchParams.set('year', year);
        var frag = window.APMListFragment;
        if (frag && frag.applyFilterValues) {
            frag.applyFilterValues(currentUrl, {
                document_number: (document.getElementById('document_number') && document.getElementById('document_number').value) ? document.getElementById('document_number').value.trim() : '',
                request_type_id: document.getElementById('request_type_id') ? (document.getElementById('request_type_id').value || '') : '',
                staff_id: document.getElementById('staff_id') ? (document.getElementById('staff_id').value || '') : '',
                division_id: document.getElementById('division_id') ? (document.getElementById('division_id').value || '') : '',
                status: document.getElementById('special_status') ? (document.getElementById('special_status').value || '') : '',
                search: document.getElementById('search') ? (document.getElementById('search').value || '').trim() : '',
                fund_type_id: document.getElementById('fund_type_id') ? (document.getElementById('fund_type_id').value || '') : '',
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
            if (data.count_my_division !== undefined) {
                var b = document.querySelector('#myDivision-tab .badge');
                if (b) b.textContent = data.count_my_division;
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

    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
        setTimeout(function() {
            var tabEl = document.getElementById(urlTab + '-tab');
            if (tabEl && (urlTab === 'mySubmitted' || urlTab === 'myDivision' || urlTab === 'sharedMemos' || urlTab === 'allMemos')) {
                if (typeof bootstrap !== 'undefined') {
                    document.querySelectorAll('#memoTabs .nav-link').forEach(function(btn) { btn.classList.remove('active'); });
                    document.querySelectorAll('#memoTabsContent .tab-pane').forEach(function(pane) { pane.classList.remove('active', 'show'); });
                    tabEl.classList.add('active');
                    var pane = document.getElementById(tabEl.getAttribute('aria-controls'));
                    if (pane) { pane.classList.add('active', 'show'); loadTabData(pane.id); }
                }
            }
        }, 50);
    }
    var filterTabInput = document.getElementById('filter_tab');
    const tabButtons = document.querySelectorAll('#memoTabs [data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#memoTabs .nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('#memoTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            this.classList.add('active');
            const tabId = this.getAttribute('aria-controls');
            if (filterTabInput) filterTabInput.value = tabId;
            const tabPane = document.getElementById(tabId);
            if (tabPane) tabPane.classList.add('active', 'show');
            loadTabData(tabId);
        });
    });
    var activeTabButton = document.querySelector('#memoTabs .nav-link.active');
    if (activeTabButton && !urlTab) {
        loadTabData(activeTabButton.getAttribute('aria-controls'));
    }
}
document.addEventListener('DOMContentLoaded', initSpecialMemoPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('memoTabs')) return;
    setTimeout(initSpecialMemoPage, 0);
});
</script>
@endsection
