
<div class="d-flex gap-2">
    <button class="btn btn-success w-100 text-white" type="button" data-bs-toggle="modal" data-bs-target="#confirmReview" style="border-radius: 0px;">
        <i class="bx bx-check text-bold"></i> Pass
    </button>
    @if(!still_with_creator($matrix))
    <button class="btn btn-danger w-100 text-white" type="button" data-bs-toggle="modal" data-bs-target="#recommendChangesModal" style="border-radius: 0px;">
        <i class="bx bx-edit text-bold"></i> Not Passed
    </button>
    @endif

</div>
        
        <!-- Convert to Single Memo Modal -->
        <div class="modal fade" id="recommendChangesModal" tabindex="-1" aria-labelledby="recommendChangesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-white">
                        <h5 class="modal-title text-white" id="recommendChangesModalLabel">
                            <i class="bx bx-file-text me-2"></i> Return Activity
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('matrices.activities.status', [$matrix, $activity])}}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Note:</strong> This will convert the activity to the Divsion Head as a Single Memo for Revision.
                            </div>
                            <div class="mb-3">
                                <label for="recommendChangesComment" class="form-label">Comments (Required)</label>
                                <textarea class="form-control" id="recommendChangesComment" name="comment" rows="3" required placeholder="Please provide reason for not passing this activity..."></textarea>
                                <input type="hidden" name="action" value="convert_to_single_memo"/>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="bx bx-file-text me-1"></i> Return Actvity
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Not Required Modal -->
        <div class="modal fade" id="notRequiredModal" tabindex="-1" aria-labelledby="notRequiredModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="notRequiredModalLabel">Mark as Not Required</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('matrices.activities.status', [$matrix, $activity])}}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="notRequiredComment" class="form-label">Comments</label>
                                <textarea class="form-control" id="notRequiredComment" name="comment" rows="3" required></textarea>
                                <input type="hidden" name="action" value="rejected"/>
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

        <div class="modal fade" id="confirmReview" tabindex="-1" aria-labelledby="confirmReviewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-md modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmReviewlLabel">Mark as Acknowledged</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('matrices.activities.status', [$matrix, $activity])}}" method="POST">
                       @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                               This confirms that you find this activity okay.
                               <input type="hidden" name="action" value="passed"/>
                            </div>
                             @if($matrix->approval_level=='5')
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="available_budget" class="form-label">Available Budget <span class="text-danger">*</span></label>
                                                <input type="number" name="available_budget" class="form-control" placeholder="Available Budget" required>
                                            </div>
                                        </div>
                            @endif
                            <div class="mb-3">
                                <label for="confirmReviewComment" class="form-label">Comments</label>
                                <textarea class="form-control" id="confirmReviewComment" name="comment" rows="3"></textarea>
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

       