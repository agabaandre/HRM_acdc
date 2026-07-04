@extends('layouts.app')

@section('title', 'Single Memos')

@section('header', 'Single Memos')

@section('header-actions')
    @if(!empty($showCreateSingleMemoInstructions))
        <button type="button" class="btn btn-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#createSingleMemoInstructionsModal">
            <i class="bx bx-plus-circle me-1"></i> Create new
        </button>
    @endif
@endsection

@section('content')
@if(!empty($showCreateSingleMemoInstructions))
<div class="modal fade" id="createSingleMemoInstructionsModal" tabindex="-1" aria-labelledby="createSingleMemoInstructionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createSingleMemoInstructionsModalLabel">
                    <i class="bx bx-info-circle me-2 text-success"></i> How to create a single memo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Single memos must be created from the correct <strong>quarterly matrix</strong>, not from this list. Creating on the wrong matrix causes approval and budget issues.</p>
                <ol class="mb-3 ps-3">
                    <li class="mb-2">Go to <strong>Quarterly Travel Matrices</strong> and open your division's matrix for the <strong>current quarter</strong> (<strong>{{ $currentQuarterLabel ?? '' }}</strong>).</li>
                    <li class="mb-2">On that matrix page, click <strong>Add Single Memo</strong>.</li>
                    <li class="mb-0">Complete the activity form and submit it for approval.</li>
                </ol>
                <div class="alert alert-warning mb-0 small">
                    <i class="bx bx-error-circle me-1"></i>
                    Do not create single memos on matrices from past or future quarters. Only the <strong>current quarter</strong> matrix is allowed.
                </div>
                @if(!empty($currentQuarterMatrix) && in_array($currentQuarterMatrix->overall_status, ['approved', 'pending', 'returned', 'onhold'], true))
                    <p class="mt-3 mb-0 small text-muted">
                        Your division's {{ $currentQuarterLabel }} matrix is available and accepts single memos.
                    </p>
                @elseif(!empty($currentQuarterMatrix))
                    <p class="mt-3 mb-0 small text-muted">
                        Your division's {{ $currentQuarterLabel }} matrix exists but is not yet in a status that accepts single memos (pending, approved, returned, or on hold).
                    </p>
                @else
                    <p class="mt-3 mb-0 small text-muted">
                        No matrix was found for your division in {{ $currentQuarterLabel }}. Contact your focal person or administrator if you need one created.
                    </p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="{{ route('matrices.index', ['year' => $apmCurrentYear ?? now()->year, 'quarter' => $apmCurrentQuarter ?? ('Q' . now()->quarter)]) }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-grid-alt me-1"></i> Go to Matrices
                </a>
                @if(!empty($currentQuarterMatrix) && in_array($currentQuarterMatrix->overall_status, ['approved', 'pending', 'returned', 'onhold'], true))
                    <a href="{{ route('matrices.show', $currentQuarterMatrix) }}" class="btn btn-success btn-sm">
                        <i class="bx bx-link-external me-1"></i> Open current quarter matrix
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
<style>
    .table-title-cell {
        max-width: 270px;
        word-wrap: break-word;
        word-break: break-word;
        white-space: normal;
        line-height: 1.4;
    }

    /* Fund Type: wrap like Status (narrow column, multi-line badge + codes) */
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
    /* Prevent duplicate visible select: only the Select2 widget should show */
    #memoFilters select.memo-filter-select.select2-hidden-accessible {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
</style>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-body py-3 px-4 bg-light rounded-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
            <h4 class="mb-0 text-success fw-bold"><i class="bx bx-file-doc me-2 text-success"></i> Single Memo Management</h4>
                    </div>

        <div class="row g-3 align-items-end" id="memoFilters" autocomplete="off">
            <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', 'mySubmitted') }}">
            @include('partials.apm-memo-list-filters', [
                'filterId' => 'memoFilters',
                'resetUrl' => route('activities.single-memos.index'),
                'showQuarter' => true,
                'statusDomId' => 'statusFilter',
                'searchLabel' => 'Search Single Memo Title',
                'searchPlaceholder' => 'Enter single memo title to search...',
                'staffLabel' => 'Staff/Responsible Person',
                'staff' => $staff,
                'divisions' => $divisions,
                'years' => $years,
                'quarters' => $quarters,
                'selectedYear' => $selectedYear,
                'selectedQuarter' => $selectedQuarter,
                'selectedFundTypeId' => $selectedFundTypeId ?? '',
                'fundTypeFilterOptions' => $fundTypeFilterOptions ?? [],
                'searchTerm' => $searchTerm,
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
function initSingleMemosPage() {
    if (!document.getElementById('memoTabs')) return;
    var filtersEl = document.getElementById('memoFilters');
    if (!filtersEl) return;

    if (filtersEl._apmSingleMemosAbort) {
        filtersEl._apmSingleMemosAbort.abort();
    }
    var ctrl = new AbortController();
    filtersEl._apmSingleMemosAbort = ctrl;
    var sig = { signal: ctrl.signal };

    function applyFilters() {
        var activeTab = document.querySelector('#memoTabsContent .tab-pane.active');
        if (activeTab) loadTabData(activeTab.id);
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'memoFilters') applyFilters();
    }, sig);
    // Open tab from URL so filter state (and tab) persist after submit/reload
    var urlParams = new URLSearchParams(window.location.search);
    var tabParam = urlParams.get('tab');
    if (tabParam) {
        setTimeout(function() {
            var tabEl = null;
            if (tabParam === 'mySubmitted' || tabParam === 'my-division') tabEl = document.getElementById('mySubmitted-tab');
            else if (tabParam === 'allMemos' || tabParam === 'all') tabEl = document.getElementById('allMemos-tab');
            else if (tabParam === 'sharedMemos' || tabParam === 'shared') tabEl = document.getElementById('sharedMemos-tab');
            if (tabEl && typeof bootstrap !== 'undefined') {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }, 50);
    }
    
    // Add click handlers to tabs to reset pagination when switching
    var tabToParam = { 'mySubmitted': 'my-division', 'allMemos': 'all', 'sharedMemos': 'shared' };
    var filterTabInput = document.getElementById('filter_tab');
    const tabButtons = document.querySelectorAll('#memoTabs [data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent Bootstrap's default tab behavior
            
            // Remove active class from all tabs and buttons
            document.querySelectorAll('#memoTabs .nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('#memoTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            
            // Add active class to clicked button and corresponding pane
            this.classList.add('active');
            const tabId = this.getAttribute('aria-controls');
            if (filterTabInput && tabToParam[tabId]) filterTabInput.value = tabToParam[tabId];
            const tabPane = document.getElementById(tabId);
            if (tabPane) {
                tabPane.classList.add('active', 'show');
            }
            
            loadTabData(tabId);
        }, sig);
    });
    
    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const frag = window.APMListFragment;
        if (frag && frag.applyFilterValues) {
            frag.applyFilterValues(currentUrl, {
                staff_id: document.getElementById('staff_id')?.value,
                division_id: document.getElementById('division_id')?.value,
                status: document.getElementById('statusFilter')?.value,
                document_number: document.getElementById('document_number')?.value,
                search: document.getElementById('search')?.value,
                year: document.getElementById('year')?.value,
                quarter: document.getElementById('quarter')?.value,
                fund_type_id: document.getElementById('fund_type_id')?.value,
            });
        }

        // Update URL so filter state persists on reload (matrices pattern)
        window.history.replaceState({}, '', currentUrl.toString());

        // Show loading indicator
        const tabContent = document.getElementById(tabId);
        if (tabContent) {
            tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }
        
        // Make AJAX request
        fetch((window.APMListFragment && window.APMListFragment.applyToUrl)
            ? window.APMListFragment.applyToUrl(currentUrl.toString())
            : currentUrl.toString(), {
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
    
    attachPaginationHandlers('mySubmitted');
    attachPaginationHandlers('allMemos');
    attachPaginationHandlers('sharedMemos');
}
document.addEventListener('DOMContentLoaded', initSingleMemosPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('memoTabs')) return;
    // Defer so DOM (including select options) is fully in place after navigation
    setTimeout(initSingleMemosPage, 0);
});
</script>
@endsection

