@extends('layouts.app')

@section('title', 'View Matrix')

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
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-calendar-event me-2 text-primary"></i>Activities</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            @if(!activities_approved_by_me($matrix) && get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft')
                                <th>
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                            @endif
                            <th>#</th>
                            <th>Title</th>
                            <th>Date Range</th>
                            <th>Participants</th>
                            <th>Budget (USD)</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                          $count=1;
                        @endphp
                        @forelse($activities as $activity)
                            <tr>
                                @if(!activities_approved_by_me($matrix) &&  get_approvable_activities($matrix)->count()>0 && $matrix->overall_status!=='draft')
                               <td>
                                    @if(can_approve_activity($activity) && !done_approving_activty($activity))
                                        <input type="checkbox" class="form-check-input activity-checkbox" value="{{ $activity->id }}" data-activity-title="{{ $activity->activity_title }}">
                                    @endif
                                </td>
                                @endif
                               <th>{{$count}}</th>
                                <td class="text-wrap" style="max-width: 350px;">
                                    {{ $activity->activity_title }}
                                </td>
                                <td>{{ \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}</td>
                                <td>{{ $activity->total_participants }}</td>
                             <td>
                                @php
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
                                {{ number_format($totalBudget, 2) }} USD
                            </td>

                                <td>
                                    @if(can_approve_activity($activity))
                                    <span class="badge bg-{{ ($activity->status === 'approved' || ($activity->my_last_action && $activity->my_last_action->action=='passed')) ? 'success' : ($activity->status === 'rejected' ? 'danger' : 'secondary') }}">
                                        {{ ucfirst(($activity->my_last_action)?$activity->my_last_action->action : 'Pending' ) }}
                                    </span>
                                    @else
                                    <span class="badge bg-success">No Action Required</span>
                                    @endif
                                </td>
                                <td class="text-center">
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
                                <td colspan="8" class="text-center text-muted py-4">No activities found for this matrix.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Approve Selected Activities Button -->
            <div class="p-3 border-top" id="approveSelectedSection" style="display: none;">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted" id="selectedCount">0 activities selected</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success" id="approveSelectedBtn" data-bs-toggle="modal" data-bs-target="#approveSelectedModal">
                            <i class="bx bx-check me-2"></i> Pass Selected Activities
                        </button>
                        <button type="button" class="btn btn-danger" id="rejectSelectedBtn" data-bs-toggle="modal" data-bs-target="#rejectSelectedModal">
                            <i class="bx bx-x me-2"></i> Reject Activities
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-3">
                {{ $activities->withQueryString()->links() }}
            </div>

        </div>
    </div>
</div>

<div class="row">

@php
//dd($matrix->division_staff->toArray());
@endphp

<div class="col-lg-8">
@if(count($matrix->division_schedule)>0)
 @include('matrices.partials.participants-schedule')
@endif
</div>

<div class="col-lg-4">
@if(count($matrix->matrixApprovalTrails)>0)
    @include('matrices.partials.approval-trail')
@endif
</div>
</div>

<div class="row">

@if(can_take_action($matrix))
<div class="col-md-4 mb-2 px-2 ms-auto">
    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
</div>
@endif

@if(($matrix->activities->count()>0 && still_with_creator($matrix)))
 <div class="col-md-4 mb-2 px-2 ms-auto">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#submitMatrixModal">
        <i class="bx bx-save me-2"></i> Submit Matrix
    </button>
 </div>
 @endif

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
</script>
@endpush
@endsection
