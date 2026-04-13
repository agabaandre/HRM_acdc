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
<div class="other-memo-form-page" data-apm-livewire-page="other-memos-edit">
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
            <form method="post" action="{{ route('other-memos.update', $memo) }}" id="other-memo-edit-form" enctype="multipart/form-data">
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

                @php
                    $memoAttachments = is_array($memo->attachment) ? $memo->attachment : [];
                    $showOtherMemoAttachments = ($memo->memoTypeDefinition && $memo->memoTypeDefinition->attachments_enabled)
                        || $memo->attachments_enabled_snapshot
                        || count($memoAttachments) > 0;
                @endphp
                @if ($showOtherMemoAttachments)
                    @include('other-memos.partials.attachment-fields', ['attachments' => $memoAttachments])
                @endif

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

    @if ($showOtherMemoAttachments ?? false)
    if (typeof jQuery !== 'undefined') {
        jQuery(function($) {
            var attachmentIndex = {{ count($memoAttachments ?? []) }};
            $('#addAttachment').on('click', function() {
                var newField = '<div class="col-md-4 attachment-block">' +
                    '<label class="form-label">Document type</label>' +
                    '<input type="text" name="attachments[' + attachmentIndex + '][type]" class="form-control" required>' +
                    '<input type="file" name="attachments[' + attachmentIndex + '][file]" class="form-control mt-1 attachment-input" ' +
                    'accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>' +
                    '<small class="text-muted">Max size: 10MB</small></div>';
                var $c = $('#attachmentContainer');
                if ($c.find('.alert-info').length) {
                    $c.empty();
                }
                $c.append(newField);
                attachmentIndex++;
            });
            $('#removeAttachment').on('click', function() {
                var n = $('.attachment-block').length;
                if (n > 1) {
                    $('.attachment-block').last().remove();
                    attachmentIndex--;
                } else if (n === 1) {
                    $('.attachment-block').last().remove();
                    attachmentIndex = 0;
                    $('#attachmentContainer').html(
                        '<div class="col-12"><div class="alert alert-info mb-0">' +
                        '<i class="bx bx-info-circle me-2"></i>No attachments currently. Click <strong>Add New</strong> to add attachments.</div></div>'
                    );
                }
            });
            $(document).on('change', '.attachment-input', function() {
                var fileInput = this;
                var file = fileInput.files[0];
                if (!file) return;
                var maxSize = 10 * 1024 * 1024;
                var ext = file.name.split('.').pop().toLowerCase();
                var allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
                if (allowedExtensions.indexOf(ext) === -1) {
                    if (typeof show_notification === 'function') {
                        show_notification('Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, or DOCX files are allowed.', 'warning');
                    } else {
                        alert('Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, or DOCX files are allowed.');
                    }
                    $(fileInput).val('');
                    return;
                }
                if (file.size > maxSize) {
                    if (typeof show_notification === 'function') {
                        show_notification('File size must be less than 10MB.', 'warning');
                    } else {
                        alert('File size must be less than 10MB.');
                    }
                    $(fileInput).val('');
                }
            });
        });
    }
    @endif
})();
</script>
@endsection
