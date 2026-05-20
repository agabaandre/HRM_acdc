@php
    $parent = $parentServiceRequest ?? null;
    $isChild = !empty($isChildRequest) || (!empty($serviceRequest) && $serviceRequest->isChildRequest());
@endphp
@if($isChild && $parent)
    <div class="alert alert-warning border-warning shadow-sm mb-4 child-service-request-banner" role="alert">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-3 text-warning"><i class="fas fa-layer-group"></i></div>
            <div class="flex-grow-1">
                <h5 class="alert-heading mb-2 text-dark">
                    <span class="badge bg-warning text-dark me-2">Child service request</span>
                    Supplementary funds for the same memo
                </h5>
                <p class="mb-2">
                    This request covers the <strong>remaining memo balance</strong> not included in the parent service request.
                    Total requested funds here must not exceed
                    <strong>${{ number_format((float) ($childBalanceCap ?? $serviceRequest->original_total_budget ?? 0), 2) }}</strong>.
                </p>
                <p class="mb-0">
                    <span class="text-muted">Previous service request document:</span>
                    <a wire:navigate href="{{ route('service-requests.show', $parent) }}" class="fw-bold text-decoration-underline ms-1">
                        {{ $parent->document_number ?? ('SR #'.$parent->id) }}
                    </a>
                    @if($parent->request_number)
                        <span class="text-muted">({{ $parent->request_number }})</span>
                    @endif
                </p>
            </div>
        </div>
    </div>
@endif
