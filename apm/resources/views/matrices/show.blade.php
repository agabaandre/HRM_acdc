@extends('layouts.app')

@section('title', 'View Matrix')

@section('styles')
<style>
    .avatar-sm {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: white;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        transition: background-color 0.2s ease;
    }
    
    .badge.rounded-pill {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
    }
    
    .card-footer .row > div {
        padding: 0.5rem;
    }
    
    .card-footer .d-flex {
        min-height: 60px;
    }
    
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    
    .table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
    
    .table td {
        vertical-align: middle;
    }
    
    .text-muted {
        color: #6c757d !important;
    }
    
    .fw-semibold {
        font-weight: 600 !important;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
        border-radius: 0.5rem;
    }
    
    .shadow-sm {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
    }
    
    .card {
        border-radius: 0.75rem;
        overflow: hidden;
    }
    
    .badge.rounded-pill {
        font-weight: 500;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>
@endsection

@section('header', 'Matrix Details')

@section('header-actions')
<div class="d-flex gap-2">
   @if($matrix->division_id == user_session()['division_id'] )
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> {{ $matrix->overall_status=='draft' ? 'Add Activity' : 'Add Single Memo' }}
        </a>
    @endif
     @if(still_with_creator($matrix))
        <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning btn-sm shadow-sm">
            <i class="bx bx-edit me-1"></i> Edit Matrix
        </a>
    @endif
    <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bx bx-arrow-back me-1"></i> Back
    </a>
</div>
@endsection

@section('content')

@include('matrices.partials.matrix-metadata')
   
<div class="col-md-12">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light border-0 py-3" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%) !important;">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bx bx-calendar-event me-2 text-primary"></i>Activities
            </h5>
            <small class="text-muted d-block mt-1">
                {{ $matrix->activities->count() }} activities in this matrix
            </small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            @if(can_take_action($matrix) && get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft')
                                <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                            @endif
                            <th class="border-0 px-3 py-3 text-muted fw-semibold" style="width: 50px;">#</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold">Title</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold">Date Range</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Participants</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Fund Type</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Budget (USD)</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Status</th>
                            <th class="border-0 px-3 py-3 text-muted fw-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                          $count=1;
                          //dd($activities[0]->activity_budget);
                        //  dd($activities[1]->activity_budget->fundcode);
                        @endphp

                        @forelse($activities as $activity)
                            <tr>
                                @if(can_take_action($matrix) &&  get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft')
                               <td class="px-3 py-3">
                                    @if(can_approve_activity($activity) && !done_approving_activty($activity))
                                        <input type="checkbox" class="form-check-input activity-checkbox" value="{{ $activity->id }}" data-activity-title="{{ $activity->activity_title }}">
                                    @endif
                                </td>
                                @endif
                               <td class="px-3 py-3">
                                    <span class="badge bg-secondary rounded-pill">{{ $count }}</span>
                               </td>
                                <td class="px-3 py-3 text-wrap" style="max-width: 350px;">
                                    {{ $activity->activity_title }}
                                </td>
                                <td class="px-3 py-3">{{ \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}</td>
                                <td class="px-3 py-3 text-center">
                                    <span class="badge bg-info rounded-pill">{{ $activity->total_participants }}</span>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="badge bg-info rounded-pill">{{ $activity->fundType->name }}</span>
                                </td>
                             <td class="px-3 py-3 text-center">
                                @php
                                //dd($activity);
                                    $budget = is_array($activity->budget) ? $activity->budget : json_decode($activity->budget, true);
                                    $totalBudget = 0;

                                    if (is_array($budget)) {
                                        foreach ($budget as $key => $entries) {
                                            if ($key === 'grand_total') continue;

                                            foreach ($entries as $item) {
                                                $unitCost = floatval($item['unit_cost'] ?? 0);
                                                $units = floatval($item['units'] ?? 0);
                                                $days = floatval($item['days'] ?? 1);
                                                $totalBudget += $unitCost * $units * $days;
                                            }
                                        }
                                    }
                                @endphp
                                <span class="fw-bold text-success">{{ number_format($totalBudget, 2) }} USD</span>
                            </td>

                                <td class="px-3 py-3 text-center">

                                @php //dd(can_approve_activity($activity)) @endphp
                                    @if(can_approve_activity($activity))
                                    <span class="badge bg-{{ allow_print_activity($activity) ? 'success' : ($activity->status === 'rejected' ? 'danger' : 'secondary') }} rounded-pill">
                                        @if(allow_print_activity($activity))
                                            Passed
                                        @elseif(!empty($activity->my_last_action))
                                            {{ ucfirst($activity->my_last_action->action) }}
                                        @else
                                            Pending
                                        @endif
                                    </span>
                                    @else
                                    <span class="badge bg-success rounded-pill">No Action Required</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </td>
                            </tr>
                        @php
                          $count++;
                        @endphp
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="bx bx-calendar-x fs-1 text-muted mb-3 d-block"></i>
                                    <div class="text-muted">No activities found for this matrix.</div>
                                    <small class="text-muted">Activities will appear here once they are added.</small>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Approve Selected Activities Button -->
            <div class="p-4 border-top bg-light" id="approveSelectedSection" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bx bx-check-circle text-success me-2 fs-4"></i>
                        <span class="text-muted fw-semibold" id="selectedCount">0 activities selected</span>
                    </div>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-success btn-lg shadow-sm" id="approveSelectedBtn" data-bs-toggle="modal" data-bs-target="#approveSelectedModal">
                            <i class="bx bx-check me-2"></i> Pass Selected Activities
                        </button>
                        {{-- <button type="button" class="btn btn-danger btn-lg shadow-sm" id="rejectSelectedBtn" data-bs-toggle="modal" data-bs-target="#rejectSelectedModal">
                            <i class="bx bx-x me-2"></i> N
                        </button> --}}
                    </div>
                </div>
            </div>

            <div class="p-4 bg-light border-top">
                <div class="d-flex justify-content-center">
                    {{ $activities->withQueryString()->links() }}
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Division Schedule and Approval Trail Section -->
<div class="row mt-4">
    <div class="col-lg-9">
        @if(count($matrix->division_staff) > 0)
            @include('matrices.partials.participants-schedule')
        @else
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="bx bx-calendar-x fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Division Schedule Available</h5>
                    <p class="text-muted mb-0">Staff schedules for {{ $matrix->quarter }} {{ $matrix->year }} will appear here once they are added.</p>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-3">
        @if(count($matrix->matrixApprovalTrails) > 0)
            @include('matrices.partials.approval-trail',['trails'=>$matrix->matrixApprovalTrails])
        @else
            <div class="card shadow-sm border-0">
                <div class="card-body text-center ">
                    <i class="bx bx-history fs-1 text-muted mb-3"></i>
                    <h5 class="text-muted">No Approval History</h5>
                    <p class="text-muted mb-0">Approval trail will appear here once actions are taken on this matrix.</p>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Action Buttons Section -->
<div class="row mt-4">
    <div class="col-12">
        <div class="d-flex justify-content-end gap-3">
            @if(can_take_action($matrix) || (can_division_head_edit($matrix) && $matrix->overall_status === 'returned'))
                <div class="d-flex align-items-center">
                    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
                </div>
            @endif
             
            {{-- @if($matrix->overall_status === 'returned')
              <button type="button" class="btn btn-success btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#returnMatrixModal">
                    <i class="bx bx-save me-2"></i> Return to Focal Person
                </button>
            
            @endif --}}

            @if(($matrix->activities->count() > 0 && still_with_creator($matrix)))
                <button type="button" class="btn btn-success btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#submitMatrixModal">
                    <i class="bx bx-save me-2"></i> Submit Matrix for Approval
                </button>
            @endif
        </div>
    </div>
</div>

<!-- Submit Matrix Confirmation Modal -->
<div class="modal fade" id="submitMatrixModal" tabindex="-1" aria-labelledby="submitMatrixModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
           
                <h5 class="modal-title text-white" id="submitMatrixModalLabel">
                    <i class="bx bx-save me-2"></i> Submit Matrix for Approval
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to submit this matrix for approval?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> Once submitted, you will not be able to make further changes to this matrix unless it's returned.
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Activities Count:</strong><br>
                        <span class="text-muted">{{ $matrix->activities->count() }} activities</span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <strong>Total Budget:</strong><br>
                        @php
                            $matrixTotalBudget = 0;
                            foreach($matrix->activities as $activity) {
                                $budget = is_array($activity->budget) ? $activity->budget : json_decode($activity->budget, true);
                                
                                if (is_array($budget)) {
                                    foreach ($budget as $key => $entries) {
                                        if ($key === 'grand_total') continue;
                                        
                                        foreach ($entries as $item) {
                                            $unitCost = floatval($item['unit_cost'] ?? 0);
                                            $units = floatval($item['units'] ?? 0);
                                            $days = floatval($item['days'] ?? 1);
                                            $matrixTotalBudget += $unitCost * $units * $days;
                                        }
                                    }
                                }
                            }
                        @endphp
                        <span class="text-success fw-bold fs-5">{{ number_format($matrixTotalBudget, 2) }} USD</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <a href="{{ route('matrices.request_approval', $matrix) }}" class="btn btn-success">
                    <i class="bx bx-save me-1"></i> Yes, Submit Matrix
                </a>
            </div>
        </div>
    </div>
</div>



<!-- Approve Selected Activities Confirmation Modal -->
<div class="modal fade" id="approveSelectedModal" tabindex="-1" aria-labelledby="approveSelectedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title text-white" id="approveSelectedModalLabel">
                    <i class="bx bx-check me-2"></i> Pass Selected Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to pass the selected activities?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This action will mark all selected activities as passed.
                </div>
                <div id="selectedActivitiesList" class="mt-3">
                    <!-- Selected activities will be listed here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form action="{{ route('matrices.activities.batch.status') }}" method="POST" id="approveSelectedForm">
                    @csrf
                    <input type="hidden" name="matrix_id" value="{{ $matrix->id }}">
                    <input type="hidden" name="action" value="passed">
                    <input type="hidden" name="activity_ids[]" id="selectedActivityIds">
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check me-1"></i> Yes, Pass Activities
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Selected Activities Confirmation Modal -->
<div class="modal fade" id="rejectSelectedModal" tabindex="-1" aria-labelledby="rejectSelectedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="rejectSelectedModalLabel">
                    <i class="bx bx-x me-2"></i> Don't Pass Selected Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to reject the selected activities?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This action will mark all selected activities as rejected.
                </div>
                <div id="rejectSelectedActivitiesList" class="mt-3">
                    <!-- Selected activities will be listed here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form action="{{ route('matrices.activities.batch.status') }}" method="POST" id="rejectSelectedForm">
                    @csrf
                    <input type="hidden" name="matrix_id" value="{{ $matrix->id }}">
                    <input type="hidden" name="action" value="rejected">
                    <input type="hidden" name="activity_ids[]" id="rejectSelectedActivityIds">
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x me-1"></i> Yes, Reject Activities
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Staff Activities Modal -->
<div class="modal fade" id="staffActivitiesModal" tabindex="-1" aria-labelledby="staffActivitiesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: #119A48 !important;">
                <h5 class="modal-title text-white" id="staffActivitiesModalLabel">
                    <i class="bx bx-user me-2"></i> Staff Activities
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <h4 id="staffNameDisplay" class="mb-2" style="color: #119A48 !important;"></h4>
                    <p class="text-muted">Activity details for {{ $matrix->quarter }} {{ $matrix->year }}</p>
                </div>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs" id="staffActivitiesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="my-division-tab" data-bs-toggle="tab" data-bs-target="#my-division" type="button" role="tab" aria-controls="my-division" aria-selected="true" style="border-color: #119A48 !important; color: #119A48 !important;">
                            <i class="bx bx-building me-1"></i> My Division
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="other-divisions-tab" data-bs-toggle="tab" data-bs-target="#other-divisions" type="button" role="tab" aria-controls="other-divisions" aria-selected="false">
                            <i class="bx bx-globe me-1"></i> Other Divisions
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content mt-3" id="staffActivitiesTabContent">
                    <!-- My Division Tab -->
                    <div class="tab-pane fade show active" id="my-division" role="tabpanel" aria-labelledby="my-division-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity Title</th>
                                        <th>Focal Person</th>
                                        <th>Division</th>
                                        <th class="text-center">Days</th>
                                    </tr>
                                </thead>
                                <tbody id="myDivisionActivities">
                                    <!-- Content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Other Divisions Tab -->
                    <div class="tab-pane fade" id="other-divisions" role="tabpanel" aria-labelledby="other-divisions-tab">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Activity Title</th>
                                        <th>Focal Person</th>
                                        <th>Division</th>
                                        <th class="text-center">Days</th>
                                    </tr>
                                </thead>
                                <tbody id="otherDivisionsActivities">
                                    <!-- Content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>


#staffActivitiesModal .nav-tabs .nav-link:hover {
    border-color: #119A48 !important;
    color: #119A48 !important;
}

#staffActivitiesModal .nav-tabs {
    border-bottom-color: #119A48 !important;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const activityCheckboxes = document.querySelectorAll('.activity-checkbox');
    const approveSelectedSection = document.getElementById('approveSelectedSection');
    const selectedCount = document.getElementById('selectedCount');
    const selectedActivitiesList = document.getElementById('selectedActivitiesList');
    const selectedActivityIds = document.getElementById('selectedActivityIds');
    const rejectSelectedActivitiesList = document.getElementById('rejectSelectedActivitiesList');
    const rejectSelectedActivityIds = document.getElementById('rejectSelectedActivityIds');

    // Select All functionality
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        activityCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        updateApproveSection();
    });

    // Individual checkbox functionality
    activityCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllState();
            updateApproveSection();
        });
    });

    // Update select all checkbox state
    function updateSelectAllState() {
        const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
        const totalBoxes = activityCheckboxes.length;
        
        if (checkedBoxes.length === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length === totalBoxes) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }

    // Update approve section visibility and content
    function updateApproveSection() {
        const checkedBoxes = document.querySelectorAll('.activity-checkbox:checked');
        const selectedIds = [];
        const selectedTitles = [];

        checkedBoxes.forEach(checkbox => {
            selectedIds.push(checkbox.value);
            selectedTitles.push(checkbox.dataset.activityTitle);
        });

        if (selectedIds.length > 0) {
            approveSelectedSection.style.display = 'block';
            selectedCount.textContent = `${selectedIds.length} activities selected`;
            selectedActivityIds.value = selectedIds.join(',');
            rejectSelectedActivityIds.value = selectedIds.join(',');
            
            // Update both modal contents
            updateModalContent(selectedActivitiesList, selectedTitles);
            updateModalContent(rejectSelectedActivitiesList, selectedTitles);
        } else {
            approveSelectedSection.style.display = 'none';
            selectedCount.textContent = '0 activities selected';
            selectedActivityIds.value = '';
            rejectSelectedActivityIds.value = '';
        }
    }

    // Function to update modal content
    function updateModalContent(container, titles) {
        container.innerHTML = '';
        titles.forEach(title => {
            container.innerHTML += `
                <div class="small text-muted">
                    <i class="bx bx-check-circle text-success me-1"></i>
                    ${title}
                </div>
            `;
        });
    }

    // Initialize the state
    updateSelectAllState();
    updateApproveSection();
});

// Staff Activities Modal Function
function showStaffActivities(staffId, staffName) {
    // Set staff name in modal
    document.getElementById('staffNameDisplay').textContent = staffName;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('staffActivitiesModal'));
    modal.show();
    
    // Load activities data
    loadStaffActivities(staffId);
}

function loadStaffActivities(staffId) {
    // Show loading state
    document.getElementById('myDivisionActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="bx bx-loader-alt bx-spin me-2"></i>Loading...</td></tr>';
    document.getElementById('otherDivisionsActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3"><i class="bx bx-loader-alt bx-spin me-2"></i>Loading...</td></tr>';
    
    const url = `${window.location.origin}/staff/apm/staff/${staffId}/activities?matrix_id={{ $matrix->id }}`;
    console.log('Fetching from URL:', url);
    
    // Fetch activities data via AJAX
    fetch(url)
        .then(response => response.json())
        .then(data => {
            // Populate My Division tab
            populateActivitiesTable('myDivisionActivities', data.my_division || []);
            
            // Populate Other Divisions tab
            populateActivitiesTable('otherDivisionsActivities', data.other_divisions || []);
        })
        .catch(error => {
            console.error('Error loading staff activities:', error);
            document.getElementById('myDivisionActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading data</td></tr>';
            document.getElementById('otherDivisionsActivities').innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger">Error loading data</td></tr>';
        });
}

function populateActivitiesTable(tableId, activities) {
    const tbody = document.getElementById(tableId);
    
    if (activities.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">No activities found</td></tr>';
        return;
    }
    
    let html = '';
    activities.forEach(activity => {
        html += `
            <tr>
                <td class="fw-semibold">${activity.activity_title || 'N/A'}</td>
                <td>${activity.focal_person || 'N/A'}</td>
                <td>${activity.division_name || 'N/A'}</td>
                <td class="text-center">
                    <span class="badge bg-primary rounded-pill">${activity.days || 0}</span>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}
</script>
@endpush
@endsection
