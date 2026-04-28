@extends('layouts.app')

@section('title', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')
@section('header', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')

@section('header-actions')
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
#activityFilters select.activities-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>
    @if(isset($matrix))
        <!-- Matrix-specific activities view -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3 px-4 bg-light rounded-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                    <h4 class="mb-0 text-success fw-bold">
                        <i class="bx bx-task me-2 text-success"></i> 
                        Activities for {{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}
                    </h4>
                    <div>
                        <a wire:navigate href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to Matrix
                        </a>
                        @if($matrix->overall_status !== 'approved')
                            <a wire:navigate href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm">
                                <i class="bx bx-plus me-1"></i> Add Activity
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Matrix activities list -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-task me-2"></i> Matrix Activities
                            </h6>
                            <small class="text-muted">{{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}</small>
                        </div>
                    </div>
                    
                    @if($activities && $activities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Activity Title</th>
                                        <th>Responsible Person</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $actCount = 1; @endphp
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td>{{ $actCount++ }}</td>
                                            <td>
                                                <strong>{{ $activity->activity_title ?? 'Untitled Activity' }}</strong>
                                                @if($activity->is_single_memo)
                                                    <span class="badge bg-warning text-dark ms-2">Single Memo</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->responsiblePerson)
                                                    {{ $activity->responsiblePerson->fname }} {{ $activity->responsiblePerson->lname }}
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->date_from && $activity->date_to)
                                                    {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                                @else
                                                    <span class="text-muted">Dates not set</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $statusClass = $activity->status === 'PASSED' ? 'bg-success' : ($activity->status === 'pending' ? 'bg-warning' : 'bg-secondary');
                                                    $workflowRole = $matrix->workflow_definition ? ($matrix->workflow_definition->role ?? 'N/A') : 'N/A';
                                                    $actorName = $matrix->current_actor ? ($matrix->current_actor->fname . ' ' . $matrix->current_actor->lname) : 'N/A';
                                                @endphp
                                                @if($activity->overall_status === 'pending' || $activity->status === 'pending')
                                                    <div class="text-center">
                                                        <span class="badge {{ $statusClass }} text-dark mb-1">{{ strtoupper($activity->status ?? 'pending') }}</span>
                                                        <br>
                                                        <small class="text-muted d-block">{{ $workflowRole }}</small>
                                                        @if($actorName !== 'N/A')
                                                            <small class="text-muted d-block">{{ $actorName }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="badge {{ $statusClass }}">{{ strtoupper($activity->status ?? 'draft') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2 justify-content-center flex-wrap activity-actions action-buttons-stacked">
                                                    <a wire:navigate href="{{ route('matrices.activities.show', [$matrix, $activity]) }}" 
                                                       class="btn btn-sm btn-outline-info activity-action-btn" title="View">
                                                        <i class="bx bx-show me-1"></i>View
                                                    </a>
                                                    @if($activity->responsible_person_id == user_session('staff_id') && in_array($activity->overall_status, ['draft', 'returned']))
                                                        <form action="{{ route('matrices.activities.destroy', [$matrix, $activity]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity? This action cannot be undone.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger activity-action-btn" title="Delete">
                                                                <i class="bx bx-trash me-1"></i>Delete
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if($activity->status === 'PASSED' && $matrix->overall_status === 'approved')
                                                        <a wire:navigate href="{{ route('matrices.activities.memo-pdf', [$matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-success activity-action-btn" title="Print PDF" target="_blank">
                                                            <i class="bx bx-printer me-1"></i>Print
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        @if($activities instanceof \Illuminate\Pagination\LengthAwarePaginator && $activities->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $activities->appends(array_merge(request()->query(), ['tab' => 'matrix']))->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-task fs-1 text-primary opacity-50"></i>
                            <p class="mb-0">No activities found for this matrix.</p>
                            @if($matrix->overall_status !== 'approved')
                                <small>Click "Add Activity" to create the first activity.</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <!-- Main activities page view -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3 px-4 bg-light rounded-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                    <h4 class="mb-0 text-success fw-bold"><i class="bx bx-task me-2 text-success"></i> Activity Details</h4>
                </div>

                <div class="row g-3 align-items-end" id="activityFilters" autocomplete="off">
                    <form action="{{ route('activities.index') }}" method="GET" class="row g-3 align-items-end w-100" id="activitiesFiltersForm">
                        <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', in_array(87, user_session('permissions', [])) ? 'all-activities' : 'my-division') }}">
                        <div class="col-12 mb-2">
                            <label for="search" class="form-label fw-semibold mb-1">
                                <i class="bx bx-search me-1 text-success"></i> Search Activity Title
                            </label>
                            <input type="text" name="search" id="search" class="form-control" 
                                   value="{{ $searchTerm ?? request('search') }}" placeholder="Enter activity title to search...">
                        </div>
                    <div class="col-md-2">
                        <label for="document_number" class="form-label fw-semibold mb-1">
                            <i class="bx bx-file me-1 text-success"></i> Document #
                        </label>
                        <input type="text" name="document_number" id="document_number" class="form-control" 
                               value="{{ request('document_number') }}" placeholder="Enter document number">
                    </div>
                    <div class="col-md-2">
                        <label for="staff_id" class="form-label fw-semibold mb-1">
                            <i class="bx bx-user me-1 text-success"></i> Responsible Person
                        </label>
                        <select name="staff_id" id="staff_id" class="form-select apm-filter-select activities-filter-select" style="width: 100%;">
                            <option value="">All Staff</option>
                            @foreach($staff as $staffMember)
                                <option value="{{ $staffMember->staff_id }}" {{ request('staff_id') == $staffMember->staff_id ? 'selected' : '' }}>
                                    {{ $staffMember->fname }} {{ $staffMember->lname }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="year" class="form-label fw-semibold mb-1">
                            <i class="bx bx-calendar me-1 text-success"></i> Year
                        </label>
                        <select name="year" id="year" class="form-select" style="width: 100%;">
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
                        <select name="quarter" id="quarter" class="form-select" style="width: 100%;">
                            @foreach($quarters as $quarter)
                                <option value="{{ $quarter }}" {{ $selectedQuarter == $quarter ? 'selected' : '' }}>
                                    {{ $quarter }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="division_id" class="form-label fw-semibold mb-1">
                            <i class="bx bx-building me-1 text-success"></i> Division
                        </label>
                        <select name="division_id" id="division_id" class="form-select apm-filter-select activities-filter-select" style="width: 100%;">
                            <option value="">All Divisions</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ $selectedDivisionId == $division->id ? 'selected' : '' }}>
                                    {{ $division->division_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label for="status" class="form-label fw-semibold mb-1">
                            <i class="bx bx-info-circle me-1 text-success"></i> Status
                        </label>
                        <select name="status" id="status" class="form-select" style="width: 100%;">
                            <option value="" {{ ($selectedStatus ?? request('status')) === '' ? 'selected' : '' }}>All</option>
                            <option value="draft" {{ ($selectedStatus ?? request('status')) === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="pending" {{ ($selectedStatus ?? request('status')) === 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ ($selectedStatus ?? request('status')) === 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="rejected" {{ ($selectedStatus ?? request('status')) === 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="returned" {{ ($selectedStatus ?? request('status')) === 'returned' ? 'selected' : '' }}>Returned</option>
                            <option value="archived" {{ ($selectedStatus ?? request('status')) === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-success btn-sm w-100" id="applyFilters">
                            <i class="bx bx-search-alt-2 me-1"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <a wire:navigate href="{{ route('activities.index') }}" class="btn btn-outline-secondary btn-sm w-100">
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
                <ul class="nav nav-tabs nav-fill" id="activitiesTabs" role="tablist">
                    @if(in_array(87, user_session('permissions', [])))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-activities-tab" data-bs-toggle="tab" data-bs-target="#all-activities" type="button" role="tab" aria-controls="all-activities" aria-selected="true">
                            <i class="bx bx-grid me-2"></i> All Activities
                            <span class="badge bg-primary text-white ms-2" id="badge-all-activities">{{ $allActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                    @endif
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ !in_array(87, user_session('permissions', [])) ? 'active' : '' }}" id="my-division-tab" data-bs-toggle="tab" data-bs-target="#my-division" type="button" role="tab" aria-controls="my-division" aria-selected="{{ !in_array(87, user_session('permissions', [])) ? 'true' : 'false' }}">
                            <i class="bx bx-home me-2"></i> My Division Activities
                            <span class="badge bg-success text-white ms-2" id="badge-my-division">{{ $myDivisionActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="shared-activities-tab" data-bs-toggle="tab" data-bs-target="#shared-activities" type="button" role="tab" aria-controls="shared-activities" aria-selected="false">
                            <i class="bx bx-share me-2"></i> Shared Activities
                            <span class="badge bg-info text-white ms-2" id="badge-shared-activities">{{ $sharedActivities->total() ?? 0 }}</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="activitiesTabsContent">
                    <!-- All Activities Tab -->
                    @if(in_array(87, user_session('permissions', [])))
                    <div class="tab-pane fade show active" id="all-activities" role="tabpanel" aria-labelledby="all-activities-tab">
                        <div class="p-3">
                            @include('activities.partials.all-activities-tab')
                        </div>
                    </div>
                @endif
                
                <!-- My Division Activities Tab -->
                <div class="tab-pane fade {{ !in_array(87, user_session('permissions', [])) ? 'show active' : '' }}" id="my-division" role="tabpanel" aria-labelledby="my-division-tab">
                    <div class="p-3">
                        @include('activities.partials.my-division-activities-tab')
                                            </div>
                                        </div>

                <!-- Shared Activities Tab -->
                <div class="tab-pane fade" id="shared-activities" role="tabpanel" aria-labelledby="shared-activities-tab">
                    <div class="p-3">
                        @include('activities.partials.shared-activities-tab')
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<script>
function initActivitiesIndexPage() {
    if (!document.getElementById('activitiesTabs')) return;
    var filtersEl = document.getElementById('activityFilters');
    if (!filtersEl) return;
    // Shared filter state (matrices pattern): set from URL then Select2; no .select2 class so footer does not init
    if (window.APMFilters) {
        APMFilters.clearInited('#activityFilters');
        APMFilters.init('#activityFilters', {
            fields: [
                { param: 'staff_id', id: 'staff_id' },
                { param: 'year', id: 'year', default: APMFilters.currentYear },
                { param: 'quarter', id: 'quarter', default: APMFilters.currentQuarter },
                { param: 'division_id', id: 'division_id' },
                { param: 'status', id: 'status' },
                { param: 'document_number', id: 'document_number' },
                { param: 'search', id: 'search' }
            ],
            tabParam: 'filter_tab',
            tabDefault: document.getElementById('all-activities-tab') ? 'all-activities' : 'my-division',
            selectSelector: '.apm-filter-select'
        });
    }
    function applyFilters() {
        var activeTab = document.querySelector('#activitiesTabsContent .tab-pane.active');
        if (activeTab) loadTabData(activeTab.id);
    }
    
    if (document.getElementById('applyFilters')) {
        document.getElementById('applyFilters').addEventListener('click', function(e) { e.preventDefault(); applyFilters(); });
    }
    var form = document.getElementById('activitiesFiltersForm');
    if (form) form.addEventListener('submit', function(e) { e.preventDefault(); applyFilters(); });
    // Auto-apply filters when they change
    if (document.getElementById('year')) {
        document.getElementById('year').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('quarter')) {
        document.getElementById('quarter').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('division_id')) {
        document.getElementById('division_id').addEventListener('change', applyFilters);
    }
    
    if (document.getElementById('staff_id')) {
        document.getElementById('staff_id').addEventListener('change', applyFilters);
    }

    if (document.getElementById('status')) {
        document.getElementById('status').addEventListener('change', applyFilters);
    }
    
    // Document number filter - apply on Enter key or after 1 second delay
    if (document.getElementById('document_number')) {
        var documentNumberTimeout;
        document.getElementById('document_number').addEventListener('input', function() {
            clearTimeout(documentNumberTimeout);
            documentNumberTimeout = setTimeout(applyFilters, 1000);
        });
        document.getElementById('document_number').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); clearTimeout(documentNumberTimeout); applyFilters(); }
        });
    }
    
    // Keep hidden tab in sync so form submit opens the right tab
    var filterTabInput = document.getElementById('filter_tab');
    
    // Handle tab switching based on URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam) {
        setTimeout(function() {
            var tabEl = null;
            if (tabParam === 'all' || tabParam === 'all-activities') tabEl = document.getElementById('all-activities-tab');
            else if (tabParam === 'my-division') tabEl = document.getElementById('my-division-tab');
            else if (tabParam === 'shared' || tabParam === 'shared-activities') tabEl = document.getElementById('shared-activities-tab');
            if (tabEl && typeof bootstrap !== 'undefined') {
                var tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }, 50);
    }
    
    // Attach initial pagination handlers for all tabs
    attachPaginationHandlers('all-activities');
    attachPaginationHandlers('my-division');
    attachPaginationHandlers('shared-activities');
    
    // Add click handlers to tabs to load data via AJAX
    const tabButtons = document.querySelectorAll('#activitiesTabs [data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#activitiesTabs .nav-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('#activitiesTabsContent .tab-pane').forEach(pane => pane.classList.remove('active', 'show'));
            this.classList.add('active');
            const tabId = this.getAttribute('aria-controls');
            if (filterTabInput) filterTabInput.value = tabId;
            const tabPane = document.getElementById(tabId);
            if (tabPane) tabPane.classList.add('active', 'show');
            loadTabData(tabId);
        });
    });
    
    // Function to load tab data via AJAX
    function loadTabData(tabId, page = 1) {
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('page', page);
        currentUrl.searchParams.set('tab', tabId);
        
        // Include current filter values
        const year = document.getElementById('year')?.value;
        const quarter = document.getElementById('quarter')?.value;
        const divisionId = document.getElementById('division_id')?.value;
        const staffId = document.getElementById('staff_id')?.value;
        const status = document.getElementById('status')?.value;
        const documentNumber = document.getElementById('document_number')?.value;
        const search = document.getElementById('search')?.value;
        
        if (year) currentUrl.searchParams.set('year', year);
        if (quarter) currentUrl.searchParams.set('quarter', quarter);
        if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
        if (staffId) currentUrl.searchParams.set('staff_id', staffId);
        if (status) currentUrl.searchParams.set('status', status);
        else currentUrl.searchParams.delete('status');
        if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
        if (search) currentUrl.searchParams.set('search', search);

        window.history.replaceState({}, '', currentUrl.toString());

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
            if (data.count_all_activities !== undefined) {
                const b = document.getElementById('badge-all-activities');
                if (b) b.textContent = data.count_all_activities;
            }
            if (data.count_my_division !== undefined) {
                const b = document.getElementById('badge-my-division');
                if (b) b.textContent = data.count_my_division;
            }
            if (data.count_shared_activities !== undefined) {
                const b = document.getElementById('badge-shared-activities');
                if (b) b.textContent = data.count_shared_activities;
            }
        })
        .catch(error => {
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
}
document.addEventListener('DOMContentLoaded', initActivitiesIndexPage);
document.addEventListener('livewire:navigated', function() {
    if (!document.getElementById('activitiesTabs')) return;
    setTimeout(initActivitiesIndexPage, 0);
});
</script>
@endsection
