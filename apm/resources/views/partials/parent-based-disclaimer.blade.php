@php
    $disclaimer = $disclaimerData ?? [];
    $parent = $disclaimer['parent'] ?? null;
    $previousArfs = $disclaimer['previous_arfs'] ?? collect();
    $previousSrs = $disclaimer['previous_service_requests'] ?? collect();
    $previousCrs = $disclaimer['previous_change_requests'] ?? collect();
    $documentType = $documentType ?? 'arf'; // 'arf' or 'service_request'
    $hasContent = $parent || $previousArfs->isNotEmpty() || $previousSrs->isNotEmpty() || $previousCrs->isNotEmpty();
    $docLabel = $documentType === 'arf' ? 'Activity Request (ARF)' : 'Service Request';
@endphp
@php
    $approvedChangesList = $disclaimer['approved_changes_list'] ?? [];
@endphp
@if($hasContent)
<div class="card mt-3 border border-1" style="border-color: #dee2e6 !important;">
    <div class="card-header bg-light py-2" style="border-bottom-color: #dee2e6 !important;">
        <h6 class="mb-0" style="color: #006633; font-size: 15px;">
            <i class="fas fa-info-circle me-2"></i>Based on parent memo — {{ $docLabel }}
        </h6>
    </div>
    <div class="card-body small py-3">
        <p class="text-muted mb-3">
            This document is a <strong>{{ $docLabel }}</strong> prepared from an approved parent memo. The source document and any related Activity Requests, Service Requests, or Change Requests issued against the same parent are listed below for reference and audit trail.
        </p>
        @if(!empty($approvedChangesList))
        <p class="mb-1"><strong>Changes approved as per the CR memo:</strong></p>
        <ul class="mb-3 ps-3">
            @foreach($approvedChangesList as $item)
            <li class="mb-1">{{ $item }}</li>
            @endforeach
        </ul>
        @endif
        @if($parent)
        <p class="mb-2">
            <strong>Source document (parent memo):</strong>
            @if($parent['url'])
                <a href="{{ $parent['url'] }}">{{ $parent['document_number'] ?: $parent['title'] }}</a>
            @else
                {{ $parent['document_number'] ?: $parent['title'] }}
            @endif
        </p>
        @endif
        @if($previousArfs->isNotEmpty())
        <p class="mb-1"><strong>Related Activity Requests (ARF) for this parent:</strong></p>
        <ul class="mb-2 ps-3">
            @foreach($previousArfs as $arf)
            <li>
                <a href="{{ route('request-arf.show', $arf) }}">{{ $arf->document_number ?: $arf->arf_number }}</a>
                <span class="text-muted">({{ ucfirst($arf->overall_status ?? 'N/A') }})</span>
            </li>
            @endforeach
        </ul>
        @endif
        @if($previousSrs->isNotEmpty())
        <p class="mb-1"><strong>Related Service Requests for this parent:</strong></p>
        <ul class="mb-2 ps-3">
            @foreach($previousSrs as $sr)
            <li>
                <a href="{{ route('service-requests.show', $sr) }}">{{ $sr->document_number ?: $sr->request_number }}</a>
                <span class="text-muted">({{ ucfirst($sr->overall_status ?? 'N/A') }})</span>
            </li>
            @endforeach
        </ul>
        @endif
        @if($previousCrs->isNotEmpty())
        <p class="mb-1"><strong>Related Change Requests (resulting in {{ $documentType === 'arf' ? 'ARF' : 'Service Request' }}):</strong></p>
        <ul class="mb-0 ps-3">
            @foreach($previousCrs as $cr)
            <li class="mb-2">
                <a href="{{ route('change-requests.show', $cr) }}">{{ $cr->document_number ?: 'CR #' . $cr->id }}</a>
                <span class="text-muted">({{ ucfirst($cr->overall_status ?? 'N/A') }})</span>
                @if($cr->supporting_reasons)
                    <span class="d-block text-muted small mt-1 ms-0">— {{ html_entity_decode(Str::limit(strip_tags($cr->supporting_reasons), 2000), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span>
                @endif
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
@endif
