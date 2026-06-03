@php
    /** @var \App\Models\ChangeRequest $changeRequest */
    $divisionName = trim((string) ($changeRequest->division->division_name ?? ''));
@endphp
@if($changeRequest->document_number)
    <span class="badge bg-primary">{{ $changeRequest->document_number }}</span>
@else
    <span class="text-muted">Pending</span>
@endif
@if($divisionName !== '')
    <small class="text-muted d-block mt-1 text-wrap" style="max-width: 200px;">{{ $divisionName }}</small>
@else
    <small class="text-muted d-block mt-1">N/A</small>
@endif
