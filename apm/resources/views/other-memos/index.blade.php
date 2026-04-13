@extends('layouts.app')

@section('title', 'Other memos')

@section('header', 'Other memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a wire:navigate href="{{ route('other-memos.create') }}" class="btn btn-success shadow-sm">
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
#otherMemoFilters select.other-memo-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>

@if (session('msg'))
    <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
@endif

<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-blank me-2 text-success"></i> Other Memo Management</h4>
        </div>

        <div class="row g-3 align-items-end" id="otherMemoFilters" autocomplete="off">
            <form action="{{ route('other-memos.index') }}" method="GET" class="row g-3 align-items-end w-100" id="otherMemoFiltersForm">
                <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
                <div class="col-md-2">
                    <label for="year" class="form-label fw-semibold mb-1">
                        <i class="bx bx-calendar me-1 text-success"></i> Year
                    </label>
                    <select name="year" id="year" class="form-select" style="width: 100%;">
                        @foreach($years ?? [] as $yr => $label)
                            <option value="{{ $yr }}" {{ ($year ?? date('Y')) == $yr ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="document_number" class="form-label fw-semibold mb-1">
                        <i class="bx bx-file me-1 text-success"></i> Document #
                    </label>
                    <input type="text" name="document_number" id="document_number" class="form-control"
                           value="{{ request('document_number') }}" placeholder="Doc #" style="width: 100%;">
                </div>
                <div class="col-md-2">
                    <label for="staff_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-user me-1 text-success"></i> Staff
                    </label>
                    <select name="staff_id" id="staff_id" class="form-select apm-filter-select other-memo-filter-select" style="width: 100%;">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->staff_id }}" {{ (string) request('staff_id') === (string) $member->staff_id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="division_id" class="form-label fw-semibold mb-1">
                        <i class="bx bx-building me-1 text-success"></i> Division
                    </label>
                    <select name="division_id" id="division_id" class="form-select apm-filter-select other-memo-filter-select" style="width: 100%;">
                        <option value="">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="memo_status" class="form-label fw-semibold mb-1">
                        <i class="bx bx-info-circle me-1 text-success"></i> Status
                    </label>
                    <select name="status" id="memo_status" class="form-select apm-filter-select other-memo-filter-select" style="width: 100%;">
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
                           value="{{ request('search') }}" placeholder="Title or type…" style="width: 100%;">
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="button" class="btn btn-success btn-sm" id="applyOtherMemoFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
                <div class="col-auto d-flex align-items-end">
                    <a wire:navigate href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bx bx-reset me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-fill" id="otherMemoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="otherMySubmitted-tab" data-bs-toggle="tab" data-bs-target="#otherMySubmitted" type="button" role="tab" aria-controls="otherMySubmitted" aria-selected="true">
                    <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                    <span class="badge bg-success text-white ms-2" id="badge-other-mySubmitted">{{ $mySubmittedMemos->total() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="otherAllMemos-tab" data-bs-toggle="tab" data-bs-target="#otherAllMemos" type="button" role="tab" aria-controls="otherAllMemos" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Other Memos
                        <span class="badge bg-primary text-white ms-2" id="badge-other-allMemos">{{ $allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator ? $allMemos->total() : $allMemos->count() }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <div class="tab-content" id="otherMemoTabsContent">
            <div class="tab-pane fade show active" id="otherMySubmitted" role="tabpanel" aria-labelledby="otherMySubmitted-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-file-alt me-2"></i> My Submitted Memos
                            </h6>
                            <small class="text-muted">Other memos you have created</small>
                        </div>
                    </div>
                    @include('other-memos.partials.my-submitted-tab')
                </div>
            </div>
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="otherAllMemos" role="tabpanel" aria-labelledby="otherAllMemos-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Other Memos
                                </h6>
                                <small class="text-muted">All other memos in the system</small>
                            </div>
                        </div>
                        @include('other-memos.partials.all-memos-tab')
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function initOtherMemosPage() {
    if (!document.getElementById('otherMemoTabs')) return;
    var filtersEl = document.getElementById('otherMemoFilters');
    if (!filtersEl) return;
    if (window.APMFilters) {
        APMFilters.clearInited('#otherMemoFilters');
        APMFilters.init('#otherMemoFilters', {
            fields: [
                { param: 'year', id: 'year', default: APMFilters.currentYear },
                { param: 'staff_id', id: 'staff_id' },
                { param: 'division_id', id: 'division_id' },
                { param: 'status', id: 'memo_status' },
                { param: 'document_number', id: 'document_number' },
                { param: 'search', id: 'search' }
            ],
            tabParam: 'filter_tab',
            tabDefault: 'mySubmitted',
            selectSelector: '.apm-filter-select'
        });
    }
    function applyFilters() {
        setTimeout(function() {
            var activeTab = document.querySelector('#otherMemoTabsContent .tab-pane.active');
            if (activeTab) loadOtherMemoTabData(activeTab.id);
        }, 0);
    }
    var applyBtn = document.getElementById('applyOtherMemoFilters');
    if (applyBtn) applyBtn.addEventListener('click', function(e) { e.preventDefault(); applyFilters(); });
    var form = document.getElementById('otherMemoFiltersForm');
    if (form) form.addEventListener('submit', function(e) { e.preventDefault(); applyFilters(); });
    ['staff_id', 'division_id', 'memo_status', 'year'].forEach(function(id) {
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
            if (e.key === 'Enter') {
                clearTimeout(documentNumberTimeout);
                applyFilters();
            }
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
    function mapPaneIdToTabParam(paneId) {
        if (paneId === 'otherAllMemos') return 'allMemos';
        return 'mySubmitted';
    }
    function loadOtherMemoTabData(paneId, page) {
        page = page || 1;
        var tabParam = mapPaneIdToTabParam(paneId);
        var currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabParam);
        var year = getYearValue();
        var documentNumber = (document.getElementById('document_number') && document.getElementById('document_number').value) ? document.getElementById('document_number').value.trim() : '';
        var staffId = document.getElementById('staff_id') ? (document.getElementById('staff_id').value || '') : '';
        var divisionId = document.getElementById('division_id') ? (document.getElementById('division_id').value || '') : '';
        var status = document.getElementById('memo_status') ? (document.getElementById('memo_status').value || '') : '';
        var search = document.getElementById('search') ? (document.getElementById('search').value || '').trim() : '';
        currentUrl.searchParams.set('year', year);
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        else currentUrl.searchParams.delete('document_number');
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        else currentUrl.searchParams.delete('staff_id');
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        else currentUrl.searchParams.delete('division_id');
        if (status) currentUrl.searchParams.set('status', status);
        else currentUrl.searchParams.delete('status');
        if (search) currentUrl.searchParams.set('search', search);
        else currentUrl.searchParams.delete('search');
        window.history.replaceState({}, '', currentUrl.toString());
        var tabContent = document.getElementById(paneId);
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
                tabContent.innerHTML = '<div class="p-3">' + rebuildOtherMemoTabShell(paneId, data.html) + '</div>';
                attachOtherMemoPaginationHandlers(paneId);
            } else if (!data.html && tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
            }
            if (data.count_my_submitted !== undefined) {
                var badgeMy = document.getElementById('badge-other-mySubmitted');
                if (badgeMy) badgeMy.textContent = data.count_my_submitted;
            }
            if (data.count_all_memos !== undefined) {
                var badgeAll = document.getElementById('badge-other-allMemos');
                if (badgeAll) badgeAll.textContent = data.count_all_memos;
            }
        })
        .catch(function(error) {
            console.error('Error loading other memo tab data:', error);
            if (tabContent) {
                tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
            }
        });
    }
    function rebuildOtherMemoTabShell(paneId, innerHtml) {
        if (paneId === 'otherAllMemos') {
            return '<div class="d-flex align-items-center justify-content-between mb-3"><div><h6 class="mb-0 text-primary fw-bold"><i class="bx bx-grid me-2"></i> All Other Memos</h6><small class="text-muted">All other memos in the system</small></div></div>' + innerHtml;
        }
        return '<div class="d-flex align-items-center justify-content-between mb-3"><div><h6 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2"></i> My Submitted Memos</h6><small class="text-muted">Other memos you have created</small></div></div>' + innerHtml;
    }
    function attachOtherMemoPaginationHandlers(paneId) {
        var tabContent = document.getElementById(paneId);
        if (!tabContent) return;
        tabContent.querySelectorAll('.pagination a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var url = new URL(this.href);
                var page = url.searchParams.get('page') || 1;
                loadOtherMemoTabData(paneId, page);
            });
        });
    }
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab) {
        setTimeout(function() {
            var tabEl = (urlTab === 'allMemos') ? document.getElementById('otherAllMemos-tab') : document.getElementById('otherMySubmitted-tab');
            if (tabEl && typeof bootstrap !== 'undefined') {
                document.querySelectorAll('#otherMemoTabs .nav-link').forEach(function(btn) { btn.classList.remove('active'); });
                document.querySelectorAll('#otherMemoTabsContent .tab-pane').forEach(function(pane) { pane.classList.remove('active', 'show'); });
                tabEl.classList.add('active');
                var pane = document.getElementById(tabEl.getAttribute('aria-controls'));
                if (pane) { pane.classList.add('active', 'show'); loadOtherMemoTabData(pane.id); }
            }
        }, 50);
    }
    var filterTabInput = document.getElementById('filter_tab');
    document.querySelectorAll('#otherMemoTabs [data-bs-toggle="tab"]').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#otherMemoTabs .nav-link').forEach(function(btn) { btn.classList.remove('active'); });
            document.querySelectorAll('#otherMemoTabsContent .tab-pane').forEach(function(pane) { pane.classList.remove('active', 'show'); });
            this.classList.add('active');
            var tabId = this.getAttribute('aria-controls');
            if (filterTabInput) filterTabInput.value = mapPaneIdToTabParam(tabId);
            var tabPane = document.getElementById(tabId);
            if (tabPane) tabPane.classList.add('active', 'show');
            loadOtherMemoTabData(tabId);
        });
    });
    var activeTabButton = document.querySelector('#otherMemoTabs .nav-link.active');
    if (activeTabButton && !urlTab) {
        loadOtherMemoTabData(activeTabButton.getAttribute('aria-controls'));
    }
}
document.addEventListener('DOMContentLoaded', initOtherMemosPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('otherMemoTabs')) return;
    setTimeout(initOtherMemosPage, 0);
});
</script>
@endsection
