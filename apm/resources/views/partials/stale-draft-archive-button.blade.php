@if(!empty($item['can_archive']) || (function_exists('can_archive_stale_draft_memo') && can_archive_stale_draft_memo($item)))
    <form
        action="{{ route('stale-drafts.archive') }}"
        method="POST"
        class="d-inline"
        onsubmit="return confirm('Archive this stale draft? It will be marked archived and release held budget.');"
    >
        @csrf
        <input type="hidden" name="memo_type" value="{{ $item['type'] }}">
        <input type="hidden" name="memo_id" value="{{ $item['id'] }}">
        @if(!empty($redirect))
            <input type="hidden" name="redirect" value="{{ $redirect }}">
        @endif
        <button type="submit" class="btn btn-sm btn-warning">
            <i class="bx bx-archive-in"></i> Archive
        </button>
    </form>
@endif
