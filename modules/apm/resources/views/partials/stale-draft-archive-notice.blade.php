@php
    $archiveNotice = isset($memo) ? stale_draft_archive_notice($memo) : null;
@endphp
@if($archiveNotice)
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="bx bx-archive-in me-2 mt-1 fs-5"></i>
        <div>
            @if($archiveNotice['is_stale'])
                <strong>Stale draft — scheduled for archive.</strong>
                This draft has not been updated for more than {{ $archiveNotice['months'] }} month(s) and still holds budget.
                It will be <strong>automatically archived on {{ $archiveNotice['scheduled_archive_at']->format('l, j F Y \a\t H:i') }}</strong>
                (weekly archive run) unless you submit, update, or delete it.
            @else
                <strong>Auto-archive notice.</strong>
                Draft memos with budget that are not updated for {{ $archiveNotice['months'] }} month(s) are archived automatically each week.
                If unchanged, this memo becomes eligible on <strong>{{ $archiveNotice['eligible_at']->format('j F Y') }}</strong>
                and would be archived on <strong>{{ $archiveNotice['scheduled_archive_at']->format('l, j F Y \a\t H:i') }}</strong>.
                Save or submit before then to keep it active.
            @endif
        </div>
    </div>
@endif
