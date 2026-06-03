@php
    $cr = $changeRequestForEdit ?? null;
@endphp

@include('partials.travel-cash-request-fields', [
    'changeRequestForEdit' => $cr,
    'showChangeRequestWorkflowHint' => true,
])

<div class="card border-0 shadow-sm mb-4" id="wb-change-request-details-card">
    <div class="card-header bg-warning text-dark">
        <h6 class="mb-0">
            <i class="fas fa-edit me-2"></i> Change Request Details
        </h6>
    </div>
    <div class="card-body">
        <div class="mb-0">
            <label for="supporting_reasons" class="form-label fw-semibold">
                <i class="fas fa-comment-alt me-1 text-warning"></i>
                {{ $supportingReasonsLabel ?? 'Supporting Reasons' }}
                <span class="text-danger">*</span>
            </label>
            @if(!empty($supportingReasonsHint))
                <small class="text-muted d-block mb-2">{{ $supportingReasonsHint }}</small>
            @else
                <small class="text-muted d-block mb-2">Provide detailed justification for the requested changes (shown to approvers and on the printed approval).</small>
            @endif
            <textarea name="supporting_reasons" id="supporting_reasons"
                class="form-control {{ !empty($useSummernoteForSupportingReasons) ? 'summernote' : '' }} @error('supporting_reasons') is-invalid @enderror"
                rows="{{ $supportingReasonsRows ?? 4 }}" required
                placeholder="{{ $supportingReasonsPlaceholder ?? 'Please explain why you are requesting these changes...' }}">{{ old('supporting_reasons', $cr->supporting_reasons ?? '') }}</textarea>
            @error('supporting_reasons')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
