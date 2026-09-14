@php
    $maxRefs = (int) ($maxReferenced ?? 0);
    $existing = is_array($referencedMemos ?? null) ? $referencedMemos : [];
    $linkValues = old('referenced_memo_links');
    if (! is_array($linkValues)) {
        $linkValues = array_map(fn ($r) => (string) ($r['url'] ?? ''), $existing);
    }
    while (count($linkValues) < min(1, $maxRefs)) {
        $linkValues[] = '';
    }
@endphp
<div class="card border mb-4 @if($maxRefs < 1) d-none @endif" id="memo-referenced-memos-card"
    data-max-referenced="{{ $maxRefs }}">
    <div class="card-header bg-light border-bottom py-2">
        <span class="fw-semibold text-success">
            <i class="bx bx-link-alt me-1"></i> Reference approved memos
        </span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Paste links to <strong>approved</strong> memos already in APM (other memos, single memos, matrix activities, special memos, non-travel, or change requests).
            Approvers can open them from the memo view. Maximum: <span id="referenced-memos-max-label">{{ $maxRefs }}</span>.
        </p>
        <div id="referenced-memo-links-container">
            @foreach ($linkValues as $i => $linkVal)
                <div class="mb-2 referenced-memo-link-row">
                    <label class="form-label small text-muted mb-1">Reference {{ $i + 1 }}</label>
                    <input type="text"
                        name="referenced_memo_links[]"
                        class="form-control referenced-memo-link-input"
                        value="{{ $linkVal }}"
                        autocomplete="off"
                        placeholder="Paste the memo page URL from your browser (approved memos only)">
                </div>
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-success" id="referenced-memo-add-link" disabled>
                <i class="bx bx-plus"></i> Add another link
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="referenced-memo-remove-link" disabled>
                <i class="bx bx-minus"></i> Remove last
            </button>
        </div>
    </div>
</div>
