
<div class="d-flex gap-2">
{{-- @dd(activities_approved_by_me($matrix)) --}}
    @if(!still_with_creator($matrix) && activities_approved_by_me($matrix))
        <button class="btn btn-success w-50 text-white" type="button" data-bs-toggle="modal" data-bs-target="#confirmReview" style="border-radius: 0px;">
            <i class="bx bx-check text-bold"></i> Approve
        </button>
    
    
   
    @endif
  @if(!($matrix->overall_status == 'approved'))
     <button class="btn btn-danger w-50 text-white" type="button" data-bs-toggle="modal" data-bs-target="#recommendChangesModal" style="border-radius: 0px;">
        <i class="bx bx-edit text-bold"></i> Return entire matrix
    </button>
    @endif
 <br>
        

    
  
    
</div>
        
        <!-- Recommend Changes Modal -->
        <div class="modal fade" id="recommendChangesModal" tabindex="-1" aria-labelledby="recommendChangesModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm  modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="recommendChangesModalLabel">Not Approved</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('matrices.status', [$matrix])}}" method="POST">
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

        <!-- Not Required Modal -->
        <div class="modal fade" id="notRequiredModal" tabindex="-1" aria-labelledby="notRequiredModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="notRequiredModalLabel">Reject</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('matrices.status', [$matrix])}}" method="POST">
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
                    <form action="{{ route('matrices.status', [$matrix])}}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                               This confirms your approval for this matrix and all.
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

       