@php
    $ntCategoriesForConvert = \App\Models\NonTravelMemoCategory::orderBy('name')->get();
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="{{ $modalId }}Label">
                    <i class="bx bx-transfer me-2"></i>Convert to non-travel memo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ $formAction }}" method="POST" id="{{ $modalId }}Form">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <strong>This cannot be undone.</strong> The current memo will be removed and replaced by a new non-travel memo.
                        Approval history will be kept. Fund transactions (if any) stay linked to the new memo without restoring balances.
                    </div>
                    <div class="mb-3">
                        <label for="{{ $modalId }}_category" class="form-label">Non-travel category <span class="text-danger">*</span></label>
                        <select class="form-select" id="{{ $modalId }}_category" name="non_travel_memo_category_id" required>
                            <option value="">— Select category —</option>
                            @foreach ($ntCategoriesForConvert as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Convert this memo to a non-travel memo? The original will be deleted.');">
                        Convert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
