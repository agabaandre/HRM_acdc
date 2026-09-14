@php
    $directorateName = trim((string) ($dd['directorate_name'] ?? ''));
    $directorName = trim((string) ($dd['director_name'] ?? ''));
@endphp
@if($directorateName !== '' || $directorName !== '')
    @if($directorateName !== '')
        <div class="small"><strong>{{ $directorateName }}</strong></div>
    @endif
    @if($directorName !== '')
        <div class="small text-muted">{{ $directorName }}</div>
    @endif
@endif
