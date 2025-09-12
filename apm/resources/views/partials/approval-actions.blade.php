@php
    $modelType = class_basename($resource);
    $modelId = $resource->id;
    $canTakeAction = can_take_action_generic($resource);
    $isWithCreator = is_with_creator_generic($resource);
    $isApproved = $resource->isApproved();
    $isDraft = property_exists($resource, 'is_draft') ? $resource->is_draft : ($resource->overall_status === 'draft');
@endphp

@if($canTakeAction && !$isApproved)
    <div class="dropdown">
        <button class="btn btn-success dropdown-toggle w-100 text-white" type="button" id="approvalActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 0px;">
            Take Action
        </button>
        <ul class="dropdown-menu w-100" aria-labelledby="approvalActionsDropdown">
            @if(!$isWithCreator)
                <li>
                    <a class="dropdown-item" href="#confirmReview" data-bs-toggle="modal">
                        <i class="bx bx-check text-bold"></i> Approve
                    </a>
                </li>
            @endif
            <li>
                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#recommendChangesModal">
                    <i class="bx bx-edit text-bold"></i> Not Approved
                </a>
            </li>
        </ul>
    </div>

    <!-- Recommend Changes Modal -->
    <div class="modal fade" id="recommendChangesModal" tabindex="-1" aria-labelledby="recommendChangesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="recommendChangesModalLabel">Not Approved</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('generic.approve', ['model' => $modelType, 'id' => $modelId]) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="recommendChangesComment" class="form-label">Comments</label>
                            <textarea class="form-control" id="recommendChangesComment" name="comment" rows="3" required></textarea>
                            <input type="hidden" name="action" value="returned"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Confirm Review Modal -->
    <div class="modal fade" id="confirmReview" tabindex="-1" aria-labelledby="confirmReviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmReviewlLabel">Mark as Acknowledged</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="{{ route('generic.approve', ['model' => $modelType, 'id' => $modelId]) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            This confirms your approval for this {{ strtolower($modelType) }}.
                            <input type="hidden" name="action" value="approved"/>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">OK</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($isWithCreator && !$isApproved && $isDraft)
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#submitForApprovalModal">
        <i class="bx bx-save me-2"></i> Submit for Approval
    </button>

    <!-- Submit for Approval Modal -->
    <div class="modal fade" id="submitForApprovalModal" tabindex="-1" aria-labelledby="submitForApprovalModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="submitForApprovalModalLabel">Submit for Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit this {{ strtolower($modelType) }} for approval?</p>
                    <p class="text-muted">Once submitted, you will not be able to make changes until it is returned.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="{{ route('generic.submit', ['model' => $modelType, 'id' => $modelId]) }}" class="btn btn-success">
                        <i class="bx bx-save me-1"></i> Yes, Submit
                    </a>
                </div>
            </div>
        </div>
    </div>
@endif 