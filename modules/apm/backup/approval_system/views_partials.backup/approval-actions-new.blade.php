<div class="d-flex gap-2 flex-wrap">
    @if(!still_with_creator($matrix))
        <a href="#confirmReview" data-bs-toggle="modal"
           class="btn btn-success d-flex align-items-center text-white">
            <i class="bx bx-check me-2"></i> Approve
        </a>
    @endif

    <a href="#" data-bs-toggle="modal" data-bs-target="#recommendChangesModal"
       class="btn btn-warning d-flex align-items-center text-white">
        <i class="bx bx-edit me-2"></i> Recommend Changes
    </a>

    <a href="#" data-bs-toggle="modal" data-bs-target="#notRequiredModal"
       class="btn btn-danger d-flex align-items-center text-white">
        <i class="bx bx-x-circle me-2"></i> Reject
    </a>
</div>
