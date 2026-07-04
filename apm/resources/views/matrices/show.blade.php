@extends('layouts.app')

@php
    $matrixQmStaffId = (int) ($matrix->focal_person_id ?? 0);
    $currentStaffIdForQm = (int) (user_session('staff_id') ?? 0);
    $isMatrixQm = $matrixQmStaffId > 0 && $currentStaffIdForQm === $matrixQmStaffId;
    $currentDivisionId = (int) (user_session('division_id') ?? 0);
    $canDivisionAddSingleMemo = $currentDivisionId > 0 && $currentDivisionId === (int) $matrix->division_id;
    $matrixIsCurrentQuarter = matrix_is_current_quarter($matrix);
    $isStrictAdmin = user_session('role') == 10;
    $effectiveHodId = effective_division_head_staff_id($matrix->division);
    $isDivisionHod = $effectiveHodId !== null && (int) $effectiveHodId === $currentStaffIdForQm;
    $matrixRegularActivityCount = $matrix->activities()->where('is_single_memo', 0)->count();
    $canEnvelopeOnHoldStatus = in_array($matrix->overall_status, ['draft', 'returned'], true);
    $canEnvelopeOnHold = ($isDivisionHod || $isStrictAdmin) && $canEnvelopeOnHoldStatus && $matrixRegularActivityCount === 0;
@endphp

@section('title', 'View Matrix')

@section('styles')
<style>
    #matrix-show-app .mx-matrix-table thead th {
        background: #f8fafc !important;
        color: rgba(0, 0, 0, 0.7) !important;
        font-weight: 600 !important;
        font-size: 0.75rem !important;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    #matrix-show-app .mx-matrix-table tbody td {
        color: rgba(0, 0, 0, 0.87) !important;
        vertical-align: middle !important;
    }
    #matrix-show-app .mx-row-warning {
        background-color: #fff3cd !important;
    }
    #matrix-show-app .mx-row-approved {
        background-color: #d5f5de !important;
    }
    #matrix-show-app .mx-row-over-limit {
        background-color: #f8d7da !important;
    }
    #matrix-show-app .mx-show-vuetify-app {
        background: transparent !important;
    }
    #matrix-show-app .v-application__wrap {
        min-height: 0 !important;
    }
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
    /* Smaller action button text on matrix show page only (table actions, Search, Reset) */
    .matrix-show-page .btn {
        font-size: 0.65rem;
        padding: 0.25rem 0.5rem;
    }
    .matrix-show-page .btn-sm {
        font-size: 0.55rem;
        padding: 0.18rem 0.35rem;
    }
    /* Stacked action buttons: same width, vertical */
    .matrix-show-page .matrix-show-action-group {
        width: 100px;
        display: inline-flex;
        flex-direction: column;
    }
    .matrix-show-page .matrix-show-action-group .btn {
        width: 100%;
        text-align: center;
        justify-content: center;
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
    
        @if($matrix->overall_status === 'draft')
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> Add Activity
        </a>
        @endif

        @if($canDivisionAddSingleMemo && $matrixIsCurrentQuarter && in_array($matrix->overall_status, ['approved', 'pending', 'returned', 'onhold'], true))
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> Add Single Memo 
        </a>
        @endif

        @if($canEnvelopeOnHold)
            <button type="button" class="btn btn-outline-info btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#envelopeOnHoldModal">
                <i class="bx bx-pause-circle me-1"></i> Envelope (on hold)
            </button>
        @endif

        @if(still_with_creator($matrix))
            <a wire:navigate href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning btn-sm shadow-sm">
                <i class="bx bx-edit me-1"></i> Edit Matrix
            </a>
        @endif
        @if($matrix->overall_status === 'approved')
            <div class="dropdown" id="matrixExportDropdownWrap">
                <button class="btn btn-primary btn-sm shadow-sm dropdown-toggle" type="button" id="matrixExportDropdown" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-auto-close="true" aria-expanded="false" aria-haspopup="true">
                    <i class="bx bx-download me-1"></i> Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="matrixExportDropdown">
                    <li><a class="dropdown-item" href="{{ route('matrices.export.pdf', $matrix) }}" target="_blank"><i class="bx bx-file me-2"></i>Export as PDF</a></li>
                    <li><a class="dropdown-item" href="{{ route('matrices.export.excel', $matrix) }}"><i class="bx bx-spreadsheet me-2"></i>Export as Excel</a></li>
                </ul>
            </div>
        @endif
        @if(function_exists('can_archive_memo') && can_archive_memo($matrix))
            <button type="button" class="btn btn-outline-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#archiveMatrixModal">
                <i class="bx bx-archive me-1"></i> Archive
            </button>
        @elseif($isStrictAdmin && ($matrix->overall_status ?? '') === 'archived')
            <button type="button" class="btn btn-outline-success btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#unarchiveMatrixModal">
                <i class="bx bx-reset me-1"></i> Unarchive
            </button>
        @endif
       <a wire:navigate href="{{ route('matrices.index') }}" class="btn btn-outline-secondary btn-sm">
       <i class="bx bx-arrow-back me-1"></i> Back
    </a>
</div>
@endsection

@section('content')
@if($matrix->overall_status === 'onhold')
    <div class="alert alert-info border-0 shadow-sm mx-3 mt-3 mb-0" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bx bx-envelope fs-4"></i>
            <div>
                <strong>Envelope (on hold)</strong>
                <p class="mb-0 small">This matrix is held open for division single memos only. Regular matrix activities are not used in this mode.</p>
            </div>
        </div>
    </div>
@endif

@if(function_exists('can_archive_memo') && can_archive_memo($matrix))
    <div class="modal fade" id="archiveMatrixModal" tabindex="-1" aria-labelledby="archiveMatrixModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="archiveMatrixModalLabel">Archive Matrix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Are you sure you want to archive this matrix?</p>
                    <p class="text-muted small mb-0">This keeps records intact and hides it from active matrix lists unless archived is selected.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('matrices.archive', $matrix) }}">
                        @csrf
                        <button type="submit" class="btn btn-danger"><i class="bx bx-archive me-1"></i> Archive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
@if(($isStrictAdmin ?? false) && ($matrix->overall_status ?? '') === 'archived')
    <div class="modal fade" id="unarchiveMatrixModal" tabindex="-1" aria-labelledby="unarchiveMatrixModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unarchiveMatrixModalLabel">Unarchive Matrix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Restore this matrix to active workflow lists?</p>
                    <p class="text-muted small mb-0">The matrix status is restored to its previous workflow state.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('matrices.unarchive', $matrix) }}">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="bx bx-reset me-1"></i> Unarchive</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@if($canEnvelopeOnHold)
    <div class="modal fade" id="envelopeOnHoldModal" tabindex="-1" aria-labelledby="envelopeOnHoldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="envelopeOnHoldModalLabel">Envelope (on hold)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="{{ route('matrices.envelope-on-hold', $matrix) }}">
                    @csrf
                    <div class="modal-body">
                        <p class="mb-2">Mark this matrix as an envelope on hold when it has <strong>no regular activities</strong> (single memos are allowed). The division can then add <strong>single memos</strong> only; regular matrix activities stay disabled until you use a normal draft matrix.</p>
                        <div class="mb-0">
                            <label for="envelopeOnHoldComment" class="form-label">Comment (optional)</label>
                            <textarea class="form-control" id="envelopeOnHoldComment" name="comment" rows="3" maxlength="2000" placeholder="e.g. Awaiting division submissions via single memos…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-dark"><i class="bx bx-pause-circle me-1"></i> Confirm on hold</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<div class="matrix-show-page" id="matrix-show-root" data-matrix-id="{{ $matrix->id }}" data-activities-url="{{ route('matrices.activities-for-approver', $matrix) }}" data-single-memos-url="{{ route('matrices.single-memos-for-approver', $matrix) }}" data-budgets-url="{{ route('matrices.budgets', $matrix) }}" data-activity-destroy-base="{{ url('matrices/'.$matrix->id.'/activities') }}">
@include('matrices.partials.matrix-metadata')
   
<div id="matrix-show-app" data-apm-vuetify-page="matrix-show" class="col-md-12">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading matrix tables…</p>
    </div>
</div>

<!-- Division Schedule and Approval Trail Section -->
<div class="row mt-4">
    <div class="col-lg-7">
        <div id="matrix-show-participants-mount">
            <div class="text-center py-4 text-muted">
                <div class="spinner-border text-success spinner-border-sm" role="status"></div>
                <div class="mt-2 small">Loading division schedule…</div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- Approval Actions Section -->
        @if(can_take_action($matrix) || (can_division_head_edit($matrix) && $matrix->overall_status === 'returned'))
            <div class="card shadow-lg border-0 mb-4" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                <div class="card-header bg-transparent border-0 py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                    <h5 class="card-title mb-0 fw-bold" style="color: #1f2937;">
                        <i class="bx bx-check-circle me-2" style="color: #059669;"></i>Approval Actions
                    </h5>
                </div>
                <div class="card-body">
                    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
                </div>
            </div>
        @endif
        @if(
            $matrix->activities->count() > 0
            && (
                ($matrix->overall_status === 'draft' && $isMatrixQm)
                || ($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
            )
        )
                <button type="button w-100" class="btn btn-success w-100 text-white" data-bs-toggle="modal" data-bs-target="#submitMatrixModal">
                    @if($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
                        <i class="fa fa-envelope"></i> Resubmit Matrix for Approval
                    @else
                        <i class="fa fa-envelope"></i> Submit Matrix for Approval
                    @endif
                </button>
        @endif



        <!-- Approval Trail Section -->
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


<!-- Submit Matrix Confirmation Modal -->
<div class="modal fade" id="submitMatrixModal" tabindex="-1" aria-labelledby="submitMatrixModalLabel" aria-hidden="true" data-request-approval-url="{{ route('matrices.request_approval', $matrix) }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
           
                <h5 class="modal-title text-white" id="submitMatrixModalLabel">
                    <i class="bx bx-save me-2"></i> 
                    @if($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
                        Resubmit Matrix for Approval
                    @else
                        Submit Matrix for Approval
                    @endif
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
                    <p class="mb-3">Are you sure you want to resubmit this matrix for approval?</p>
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Note:</strong> As the Head of Division, you are resubmitting this matrix after it was returned. Please add any comments about the changes made.
                    </div>
                @else
                <p class="mb-3">Are you sure you want to submit this matrix for approval?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> Once submitted, you will not be able to make further changes to this matrix unless it's returned.
                </div>
                @endif
                
                @if($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
                    <div class="mb-3">
                        <label for="hodComment" class="form-label">
                            <strong>Comments (Optional):</strong>
                        </label>
                        <textarea class="form-control" id="hodComment" name="hod_comment" rows="3" 
                                  placeholder="Add any comments about the changes made to the matrix..."></textarea>
                    </div>
                @endif
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>Activities Count:</strong><br>
                        <span class="text-muted" id="activities-count">
                            <i class="bx bx-loader-alt bx-spin"></i> Loading...
                        </span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <strong>Total Budget:</strong><br>
                        <span class="text-primary fw-bold fs-6" id="total-budget">
                            <i class="bx bx-loader-alt bx-spin"></i> Loading...
                        </span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                @if($matrix->overall_status === 'returned' && can_division_head_edit($matrix))
                    <button type="button" class="btn btn-success" id="submitWithCommentBtn">
                        <i class="fa fa-envelope"></i> Yes, Resubmit Matrix
                    </button>
                @else
                <a wire:navigate href="{{ route('matrices.request_approval', $matrix) }}" class="btn btn-success">
                        <i class="fa fa-envelope"></i> Yes, Submit Matrix
                </a>
                @endif
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

<!-- Delete Activity Confirmation Modal -->
<div class="modal fade" id="deleteActivityModal" tabindex="-1" aria-labelledby="deleteActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="deleteActivityModalLabel">
                    <i class="bx bx-trash me-2"></i> Delete Activity
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this activity?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All data associated with this activity will be permanently deleted.
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong>Activity Title:</strong><br>
                        <span class="text-muted" id="deleteActivityTitle">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form id="deleteActivityForm" method="POST" action="#" style="display: inline;" data-requires-activity-id="1">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" id="deleteActivitySubmitBtn" disabled>
                        <i class="bx bx-trash me-1"></i> Yes, Delete Activity
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Single Memo Confirmation Modal -->
<div class="modal fade" id="deleteSingleMemoModal" tabindex="-1" aria-labelledby="deleteSingleMemoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title text-white" id="deleteSingleMemoModalLabel">
                    <i class="bx bx-trash me-2"></i> Delete Single Memo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this single memo?</p>
                <div class="alert alert-danger">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. All data associated with this single memo will be permanently deleted.
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong>Single Memo Title:</strong><br>
                        <span class="text-muted" id="deleteSingleMemoTitle">-</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <form id="deleteSingleMemoForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-trash me-1"></i> Yes, Delete Single Memo
                    </button>
                </form>
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
// Ensure Export dropdown shows options (fix for Livewire navigation / clipping)
function initMatrixExportDropdown() {
    var btn = document.getElementById('matrixExportDropdown');
    if (!btn || typeof bootstrap === 'undefined') return;
    try {
        bootstrap.Dropdown.getOrCreateInstance(btn, { boundary: 'viewport' });
    } catch (e) {}
}
document.addEventListener('DOMContentLoaded', initMatrixExportDropdown);
document.addEventListener('livewire:navigated', initMatrixExportDropdown);

// Format currency with dollar sign (used by matrix metadata budget widgets)
function formatCurrency(amount) {
    if (window.ApmMatrixShow && typeof window.ApmMatrixShow.formatCurrency === 'function') {
        return window.ApmMatrixShow.formatCurrency(amount);
    }
    return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount || 0);
}

// Load matrix budget information (URL from current page so correct after Livewire navigation)
function loadMatrixBudgets() {
    const root = document.getElementById('matrix-show-root');
    const budgetsUrl = root ? root.getAttribute('data-budgets-url') : null;
    if (!budgetsUrl) return;
    fetch(budgetsUrl)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update intramural budget
                const intramuralElement = document.getElementById('intramural-budget');
                if (intramuralElement) {
                    intramuralElement.innerHTML = formatCurrency(data.data.intramural_budget);
                }
                
                // Update extramural budget
                const extramuralElement = document.getElementById('extramural-budget');
                if (extramuralElement) {
                    extramuralElement.innerHTML = formatCurrency(data.data.extramural_budget);
                }
                
                // Update total budget
                const totalBudgetElement = document.getElementById('total-budget');
                if (totalBudgetElement) {
                    totalBudgetElement.innerHTML = formatCurrency(data.data.total_budget);
                }
                
                // Update activities count
                const activitiesCountElement = document.getElementById('activities-count');
                if (activitiesCountElement) {
                    activitiesCountElement.innerHTML = data.data.activities_count + ' activities';
                }
            } else {
                console.error('Error loading matrix budgets:', data.message);
                // Show error state
                const elements = ['intramural-budget', 'extramural-budget', 'total-budget', 'activities-count'];
                elements.forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        element.innerHTML = '<span class="text-danger">Error loading</span>';
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error loading matrix budgets:', error);
            // Show error state
            const elements = ['intramural-budget', 'extramural-budget', 'total-budget', 'activities-count'];
            elements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<span class="text-danger">Error loading</span>';
                }
            });
        });
}

function initMatrixShowPage() {
    setTimeout(loadMatrixBudgets, 500);
}
document.addEventListener('DOMContentLoaded', initMatrixShowPage);
document.addEventListener('livewire:navigated', function() {
    if (document.getElementById('matrix-show-app')) {
        initMatrixShowPage();
        if (typeof initializeTooltips === 'function') initializeTooltips();
    }
});

// Initialize Bootstrap tooltips
function initializeTooltips() {
    // Initialize tooltips for elements with data-bs-toggle="tooltip"
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        // Dispose existing tooltip if any
        const existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
        if (existingTooltip) {
            existingTooltip.dispose();
        }
        // Create new tooltip
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize tooltips for buttons with modals (using data-bs-title)
    const modalButtons = document.querySelectorAll('[data-bs-toggle="modal"][data-bs-title]');
    modalButtons.forEach(function (button) {
        // Dispose existing tooltip if any
        const existingTooltip = bootstrap.Tooltip.getInstance(button);
        if (existingTooltip) {
            existingTooltip.dispose();
        }
        // Create new tooltip
        new bootstrap.Tooltip(button, {
            title: button.getAttribute('data-bs-title')
        });
    });
}

// Copy Activity Modal: use delegation so it works after Livewire navigation (wire:navigate)
document.addEventListener('show.bs.modal', function(event) {
    if (event.target.id !== 'copyActivityModal') return;
    const modal = event.target;
    const button = event.relatedTarget;
    if (!button) return;
    const activityId = button.getAttribute('data-activity-id');
    const activityTitle = button.getAttribute('data-activity-title') || '';
    modal.setAttribute('data-copy-activity-id', activityId || '');
    const titleEl = modal.querySelector('#copy-activity-title');
    if (titleEl) titleEl.textContent = activityTitle;
});
document.addEventListener('click', function(event) {
    const confirmBtn = event.target.closest('#confirm-copy-activity');
    if (!confirmBtn) return;
    const modal = document.getElementById('copyActivityModal');
    if (!modal) return;
    const activityId = modal.getAttribute('data-copy-activity-id');
    if (!activityId) return;
    window.location.href = '{{ url("matrices/" . $matrix->id . "/activities") }}/' + activityId + '/copy';
});

// Resubmit / submit matrix with comment: delegated so it works after Livewire navigation
document.addEventListener('click', function(event) {
    const submitWithCommentBtn = event.target.closest('#submitWithCommentBtn');
    if (submitWithCommentBtn) {
        event.preventDefault();
        const modal = submitWithCommentBtn.closest('#submitMatrixModal');
        const actionUrl = modal && modal.getAttribute('data-request-approval-url');
        if (!actionUrl) return;
        const commentEl = document.getElementById('hodComment');
        const comment = commentEl ? commentEl.value.trim() : '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        if (comment) {
            const commentInput = document.createElement('input');
            commentInput.type = 'hidden';
            commentInput.name = 'hod_comment';
            commentInput.value = comment;
            form.appendChild(commentInput);
        }
        document.body.appendChild(form);
        form.submit();
        return;
    }
    const submitWithFocalCommentBtn = event.target.closest('#submitWithFocalCommentBtn');
    if (submitWithFocalCommentBtn) {
        event.preventDefault();
        const modal = submitWithFocalCommentBtn.closest('#submitMatrixModal');
        const actionUrl = modal && modal.getAttribute('data-request-approval-url');
        if (!actionUrl) return;
        const commentEl = document.getElementById('focalPersonComment');
        const comment = commentEl ? commentEl.value.trim() : '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = actionUrl;
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        if (comment) {
            const commentInput = document.createElement('input');
            commentInput.type = 'hidden';
            commentInput.name = 'focal_person_comment';
            commentInput.value = comment;
            form.appendChild(commentInput);
        }
        document.body.appendChild(form);
        form.submit();
    }
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

// Delete activity / single-memo modals — delegated so Livewire navigate cannot leave a blank form action
// (blank action would DELETE the matrix URL and hit MatrixController@destroy).
if (!window._apmMatrixDeleteModalsBound) {
    window._apmMatrixDeleteModalsBound = true;

    document.addEventListener('show.bs.modal', function (event) {
        if (event.target.id === 'deleteActivityModal') {
            const button = event.relatedTarget;
            const activityId = button ? String(button.getAttribute('data-activity-id') || '').trim() : '';
            const activityTitle = button ? (button.getAttribute('data-activity-title') || '-') : '-';
            const titleEl = document.getElementById('deleteActivityTitle');
            const form = document.getElementById('deleteActivityForm');
            const submitBtn = document.getElementById('deleteActivitySubmitBtn');
            const root = document.getElementById('matrix-show-root');
            const base = root ? String(root.getAttribute('data-activity-destroy-base') || '').replace(/\/$/, '') : '';

            if (titleEl) titleEl.textContent = activityTitle;
            if (form && base && activityId && /^\d+$/.test(activityId)) {
                form.action = base + '/' + activityId;
                if (submitBtn) submitBtn.disabled = false;
            } else if (form) {
                form.action = '#';
                if (submitBtn) submitBtn.disabled = true;
            }
            return;
        }

        if (event.target.id === 'deleteSingleMemoModal') {
            const button = event.relatedTarget;
            const memoId = button ? String(button.getAttribute('data-memo-id') || '').trim() : '';
            const memoTitle = button ? (button.getAttribute('data-memo-title') || '-') : '-';
            const titleEl = document.getElementById('deleteSingleMemoTitle');
            const form = document.getElementById('deleteSingleMemoForm');
            if (titleEl) titleEl.textContent = memoTitle;
            if (form && memoId && /^\d+$/.test(memoId)) {
                form.action = @json(url('single-memos')) + '/' + memoId;
            }
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!form || form.id !== 'deleteActivityForm') return;
        const action = String(form.action || '');
        if (action === '#' || action.endsWith('/activities') || !/\/activities\/\d+/.test(action)) {
            event.preventDefault();
            if (typeof show_notification === 'function') {
                show_notification('Could not determine which activity to delete. Close the dialog and try again.', 'error');
            }
        }
    }, true);
}
</script>

<style>
/* Search enhancements */
#search-status {
    border-left: 4px solid #0d6efd;
}

/* Budget display enhancements */
.budget-display {
    line-height: 1.2;
}

.budget-available {
    font-size: 0.85em;
    color: #6c757d;
    margin-top: 2px;
}

.search-input-group .input-group-text {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.search-input-group .form-control:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Search button hover effects */
#search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#clear-search-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Page size selector styling */
#pageSizeSelect {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    transition: all 0.15s ease-in-out;
}

#pageSizeSelect:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

#showingRange {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Budget styling for total budget in matrix information */
#total-budget {
    background: linear-gradient(135deg, #f9f0ff 0%, #efdbff 100%);
    padding: 0.4rem 0.6rem;
    border-radius: 0.4rem;
    border: 1px solid #722ed1;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    transition: all 0.2s ease;
    display: inline-block;
    font-size: 0.9rem;
    color: #531dab !important;
}

#total-budget:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
</style>

@endpush

<!-- Copy Activity Modal -->
<div class="modal fade" id="copyActivityModal" tabindex="-1" aria-labelledby="copyActivityModalLabel" aria-hidden="true" data-matrix-id="{{ $matrix->id }}">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="copyActivityModalLabel">
                    <i class="bx bx-copy me-2"></i>Copy Activity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to copy this activity?</p>
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Note:</strong> This will create a copy of the activity with "(Copy)" added to the title. The copied activity will be in draft status and you can edit it as needed.
                </div>
                <div class="mt-3">
                    <strong>Activity Title:</strong><br>
                    <span id="copy-activity-title" class="text-muted"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-info" id="confirm-copy-activity">
                    <i class="bx bx-copy me-1"></i> Copy Activity
                </button>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
