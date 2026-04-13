@extends('layouts.app')

@section('title', 'Edit memo')

@section('header', 'Edit: ' . $memo->memo_type_name_snapshot)

@section('header-actions')
    <a href="{{ route('other-memos.show', $memo) }}" class="btn btn-outline-secondary" wire:navigate>
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to memo
    </a>
@endsection

@push('styles')
<style>
    .other-memo-form-page .select2-container--bootstrap4 .select2-results__option--highlighted,
    .other-memo-form-page .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #198754 !important;
        color: #fff !important;
    }
    .other-memo-form-page .select2-container--bootstrap4 .select2-results__option[aria-selected="true"] {
        background-color: rgba(25, 135, 84, 0.12) !important;
        color: #0f5132 !important;
    }
</style>
@endpush

@section('content')
<div class="other-memo-form-page">
    @if (session('msg'))
        <div class="alert alert-{{ session('type', 'info') }}">{{ session('msg') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 text-dark">
                <i class="bx bx-edit-alt me-2 text-success"></i> {{ $memo->memo_type_name_snapshot }}
            </h5>
            @if ($memo->document_number)
                <code class="small">{{ $memo->document_number }}</code>
            @endif
        </div>
        <div class="card-body p-4">
            <form method="post" action="{{ route('other-memos.update', $memo) }}" id="other-memo-edit-form">
                @csrf
                <input type="hidden" name="_method" id="other-memo-form-method" value="PUT">

                <div class="mb-4">
                    <h6 class="fw-bold text-success mb-3 border-bottom pb-2">
                        <i class="bx bx-file-blank me-2"></i> Memo content
                    </h6>
                    @include('other-memos.partials.dynamic-fields', [
                        'schema' => $memo->fields_schema_snapshot ?? [],
                        'values' => $memo->payload ?? [],
                        'readonly' => false,
                    ])
                </div>

                <div class="card border mb-4">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-git-merge me-1"></i> Approval sequence (order matters)</span>
                    </div>
                    <div class="card-body">
                        @include('other-memos.partials.approver-rows', [
                            'approvers' => $memo->approvers_config ?? [],
                            'staffOptions' => $staffOptions,
                            'roleExamples' => $roleExamples,
                        ])
                    </div>
                </div>

                <div class="card border mb-4">
                    <div class="card-body">
                        <label class="form-label fw-semibold">Notes to approvers (optional)</label>
                        <textarea name="submission_remarks" class="form-control" rows="2" placeholder="Shown on submit / resubmit trail">{{ old('submission_remarks') }}</textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-success" id="other-memo-save-draft"><i class="bx bx-save"></i> Save draft</button>
                    @if (in_array($memo->overall_status, ['draft', 'returned'], true))
                        <button type="submit" class="btn btn-outline-success" formaction="{{ route('other-memos.submit', $memo) }}" formmethod="post" id="btn-submit-approval">
                            <i class="bx bx-send"></i> Submit for approval
                        </button>
                    @endif
                    <a href="{{ route('other-memos.show', $memo) }}" class="btn btn-outline-secondary" wire:navigate>Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
    function syncSummernote() {
        if (typeof jQuery === 'undefined') return;
        jQuery('textarea.summernote').each(function() {
            var $t = jQuery(this);
            if ($t.summernote && $t.next('.note-editor').length) {
                $t.val($t.summernote('code'));
            }
        });
    }
    var form = document.getElementById('other-memo-edit-form');
    var methodInput = document.getElementById('other-memo-form-method');
    form?.addEventListener('submit', syncSummernote);
    document.getElementById('other-memo-save-draft')?.addEventListener('click', function() {
        if (methodInput) methodInput.disabled = false;
    });
    document.getElementById('btn-submit-approval')?.addEventListener('click', function(e) {
        if (!confirm('Submit for approval using the approver list above?')) {
            e.preventDefault();
            return;
        }
        syncSummernote();
        if (methodInput) methodInput.disabled = true;
    });
    document.addEventListener('livewire:navigated', function() {
        if (typeof jQuery !== 'undefined' && typeof window.initOtherMemoStaffSelect2 === 'function') {
            jQuery('#approver-rows-container select.approver-staff-id').each(function() {
                window.initOtherMemoStaffSelect2(jQuery(this));
            });
        }
    });
})();
</script>
@endpush
