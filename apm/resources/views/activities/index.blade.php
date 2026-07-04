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
                            <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm">
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
                                                    if ($activity->is_single_memo) {
                                                        $statusMeta = $activity->memoIndexStatusMeta();
                                                        $workflowRole = $statusMeta['role'] ?? 'N/A';
                                                        $actorName = $activity->current_actor
                                                            ? trim(($activity->current_actor->fname ?? '').' '.($activity->current_actor->lname ?? ''))
                                                            : ($statusMeta['actor_name'] ?? 'N/A');
                                                    } else {
                                                        $workflowRole = $matrix->workflow_definition ? ($matrix->workflow_definition->role ?? 'N/A') : 'N/A';
                                                        $actorName = $matrix->current_actor ? ($matrix->current_actor->fname . ' ' . $matrix->current_actor->lname) : 'N/A';
                                                    }
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
                                                       class="btn btn-sm btn-outline-info activity-action-btn" title="Open">
                                                        <i class="bx bx-show me-1"></i>Open
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
                    <input type="hidden" name="tab" id="filter_tab" value="{{ request('tab', in_array(87, user_session('permissions', [])) ? 'all-activities' : 'my-division') }}">
                    @include('partials.apm-memo-list-filters', [
                        'filterId' => 'activityFilters',
                        'resetUrl' => route('activities.index'),
                        'showQuarter' => true,
                        'showFundType' => true,
                        'searchLabel' => 'Search Activity Title',
                        'searchPlaceholder' => 'Enter activity title to search...',
                        'staffLabel' => 'Responsible Person',
                        'staff' => $staff,
                        'divisions' => $divisions,
                        'years' => $years,
                        'quarters' => $quarters,
                        'selectedYear' => $selectedYear,
                        'selectedQuarter' => $selectedQuarter,
                        'selectedDivisionId' => $selectedDivisionId,
                        'selectedStatus' => $selectedStatus,
                        'selectedFundTypeId' => $selectedFundTypeId,
                        'searchTerm' => $searchTerm,
                        'fundTypeFilterOptions' => $fundTypeFilterOptions ?? [],
                    ])
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
    function applyFilters() {
        var activeTab = document.querySelector('#activitiesTabsContent .tab-pane.active');
        if (activeTab) loadTabData(activeTab.id);
    }
    document.addEventListener('apm-memo-filters:apply', function(e) {
        if (e.detail && e.detail.filterId === 'activityFilters') applyFilters();
    });
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
        const frag = window.APMListFragment;
        if (frag && frag.applyFilterValues) {
            frag.applyFilterValues(currentUrl, {
                year: document.getElementById('year')?.value,
                quarter: document.getElementById('quarter')?.value,
                division_id: document.getElementById('division_id')?.value,
                staff_id: document.getElementById('staff_id')?.value,
                status: document.getElementById('status')?.value,
                fund_type_id: document.getElementById('fund_type_id')?.value,
                document_number: document.getElementById('document_number')?.value,
                search: document.getElementById('search')?.value,
            });
        }

        window.history.replaceState({}, '', currentUrl.toString());

        // Show loading indicator
        const tabContent = document.getElementById(tabId);
        if (tabContent) {
            tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        }

        // Make AJAX request
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
