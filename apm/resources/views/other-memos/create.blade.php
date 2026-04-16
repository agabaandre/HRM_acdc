@extends('layouts.app')

@section('title', 'New other memo')

@section('header', 'New other memo')

@section('header-actions')
    <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>
        <i class="bx bx-arrow-back me-1 text-success"></i> Back to list
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
{{-- Page markers + scripts below live inside #apm-content-area so wire:navigate executes them (Livewire docs). --}}
<div class="other-memo-form-page" data-apm-livewire-page="other-memos-create">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0 text-dark">
                <i class="bx bx-file-blank me-2 text-success"></i> Create memo
            </h5>
        </div>
        <div class="card-body p-4">
            <form method="post" action="{{ route('other-memos.store') }}" id="other-memo-create-form" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <h6 class="fw-bold text-success mb-3 border-bottom pb-2">
                        <i class="bx bx-category me-2"></i> Memo type
                    </h6>
                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <label class="form-label fw-semibold" for="memo_type_slug">
                                <i class="bx bx-list-ul me-1 text-success"></i> Type <span class="text-danger">*</span>
                            </label>
                <select name="memo_type_slug" id="memo_type_slug" class="form-select other-memo-type-select w-100 border-success" required
                    data-placeholder="Select memo type" style="width: 100%;">
                                <option value="">— Load types —</option>
                            </select>
                            <p class="small text-muted mt-2 mb-0">Choose a catalogue entry from Settings → Other memo types. Fields and approvers appear after selection.</p>
                        </div>
                    </div>
                </div>

                <div class="card border mb-4 d-none" id="memo-fields-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-edit-alt me-1"></i> Memo content</span>
                    </div>
                    <div class="card-body" id="memo-dynamic-fields"></div>
                </div>

                <div class="card border mb-4 d-none" id="memo-attachments-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-paperclip me-1"></i> Attachments</span>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-danger btn-sm" id="other-memo-add-attachment">Add New</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="other-memo-remove-attachment">Remove</button>
                        </div>
                        <div class="row g-3" id="other-memo-attachment-container"></div>
                        <p class="small text-muted mt-2 mb-0">Max size 10 MB per file. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX.</p>
                    </div>
                </div>

                <div class="card border mb-4 d-none" id="memo-approvers-card">
                    <div class="card-header bg-light border-bottom py-2">
                        <span class="fw-semibold text-success"><i class="bx bx-git-merge me-1"></i> Approval sequence</span>
                    </div>
                    <div class="card-body">
                        @include('other-memos.partials.approver-rows', [
                            'approvers' => [],
                            'staffOptions' => $staffOptions,
                            'roleExamples' => $roleExamples,
                        ])
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 pt-2 border-top">
                    <button type="submit" class="btn btn-success" id="other-memo-create-submit">
                        <i class="bx bx-save"></i> Save draft
                    </button>
                    <a href="{{ route('other-memos.index') }}" class="btn btn-outline-secondary" wire:navigate>Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Inline script: must stay in morphed content (not the layout scripts stack) per Livewire navigate. --}}
<script>
(function() {
    var apiList = @json(route('memo-type-definitions.api.index')) + '?active_only=1';
    var types = {};

    function initMemoTypeSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $mt = jQuery('#memo_type_slug');
        if ($mt.hasClass('select2-hidden-accessible')) {
            $mt.select2('destroy');
        }
        $mt.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $mt.data('placeholder') || 'Select memo type',
            allowClear: false
        });
        $mt.off('change.otherMemoType').on('change.otherMemoType', onMemoTypeChange);
        onMemoTypeChange();
    }

    function renderFields(schema) {
        var host = document.getElementById('memo-dynamic-fields');
        if (typeof jQuery !== 'undefined') {
            jQuery(host).find('textarea.summernote').each(function() {
                var $t = jQuery(this);
                if ($t.next('.note-editor').length && typeof $t.summernote === 'function') {
                    try { $t.summernote('destroy'); } catch (e) {}
                }
            });
        }
        host.innerHTML = '';
        if (!schema || !schema.length) {
            host.innerHTML = '<p class="text-muted mb-0">No fields configured for this type.</p>';
            return;
        }
        schema.forEach(function(f) {
            if (f.enabled === false) {
                return;
            }
            var k = f.field;
            var lab = document.createElement('label');
            lab.className = 'form-label fw-semibold';
            lab.textContent = (f.display || k) + (f.required ? ' *' : '');
            host.appendChild(lab);
            var name = 'payload[' + k + ']';
            var inp;
            if (f.field_type === 'text_summernote') {
                inp = document.createElement('textarea');
                inp.name = name;
                inp.className = 'form-control summernote';
                inp.rows = 4;
                if (f.required) inp.required = true;
            } else if (f.field_type === 'textarea') {
                inp = document.createElement('textarea');
                inp.name = name;
                inp.className = 'form-control';
                inp.rows = 3;
                if (f.required) inp.required = true;
            } else if (f.field_type === 'number') {
                inp = document.createElement('input');
                inp.type = 'number';
                inp.step = 'any';
                inp.name = name;
                inp.className = 'form-control';
                if (f.required) inp.required = true;
            } else if (f.field_type === 'date') {
                inp = document.createElement('input');
                inp.type = 'date';
                inp.name = name;
                inp.className = 'form-control';
                if (f.required) inp.required = true;
            } else if (f.field_type === 'email') {
                inp = document.createElement('input');
                inp.type = 'email';
                inp.name = name;
                inp.className = 'form-control';
                if (f.required) inp.required = true;
            } else {
                inp = document.createElement('input');
                inp.type = 'text';
                inp.name = name;
                inp.className = 'form-control';
                if (f.required) inp.required = true;
            }
            var wrap = document.createElement('div');
            wrap.className = 'mb-3';
            wrap.appendChild(inp);
            host.appendChild(wrap);
        });
        if (typeof jQuery !== 'undefined' && typeof window.apmSummernoteOptions === 'function') {
            jQuery(host).find('textarea.summernote').each(function() {
                var $ta = jQuery(this);
                if (!$ta.next('.note-editor').length) {
                    $ta.summernote(window.apmSummernoteOptions({
                        height: 260,
                        minHeight: 200,
                        placeholder: 'Type here…'
                    }));
                }
            });
        }
    }

    function onMemoTypeChange() {
        var sel = document.getElementById('memo_type_slug');
        if (!sel) return;
        var t = types[sel.value];
        document.getElementById('memo-fields-card').classList.toggle('d-none', !t);
        document.getElementById('memo-approvers-card').classList.toggle('d-none', !t);
        var attCard = document.getElementById('memo-attachments-card');
        if (attCard) {
            attCard.classList.toggle('d-none', !t || !t.attachments_enabled);
        }
        if (t && typeof window.initOtherMemoApprovers === 'function') {
            // Re-init approver Select2 after card becomes visible.
            window.initOtherMemoApprovers();
        }
        if (t) renderFields(t.fields_schema || []);
    }

    function boot() {
        var sel = document.getElementById('memo_type_slug');
        if (!sel || sel.dataset.apmOtherMemoBoot) return;
        sel.dataset.apmOtherMemoBoot = '1';

        fetch(apiList, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (!json.success || !json.data) return;
                if (typeof jQuery !== 'undefined') {
                    var $s = jQuery(sel);
                    if ($s.hasClass('select2-hidden-accessible')) {
                        $s.select2('destroy');
                    }
                }
                sel.innerHTML = '<option value=""></option>';
                json.data.forEach(function(t) {
                    types[t.slug] = t;
                    var o = document.createElement('option');
                    o.value = t.slug;
                    o.textContent = t.name;
                    sel.appendChild(o);
                });
                initMemoTypeSelect2();
            });

        var form = document.getElementById('other-memo-create-form');
        if (form && !form.dataset.apmSummernoteSubmitBound) {
            form.dataset.apmSummernoteSubmitBound = '1';
            form.addEventListener('submit', function() {
                if (typeof jQuery === 'undefined') return;
                jQuery('#memo-dynamic-fields textarea.summernote').each(function() {
                    var $t = jQuery(this);
                    if ($t.summernote && $t.next('.note-editor').length) {
                        $t.val($t.summernote('code'));
                    }
                });
            });
        }
    }

    boot();

    if (typeof jQuery !== 'undefined') {
        jQuery(function($) {
            var otherMemoAttachmentIndex = 1;
            $('#other-memo-add-attachment').on('click', function() {
                var newField = '<div class="col-md-4 attachment-block">' +
                    '<label class="form-label">Document type</label>' +
                    '<input type="text" name="attachments[' + otherMemoAttachmentIndex + '][type]" class="form-control" required>' +
                    '<input type="file" name="attachments[]" class="form-control mt-1 other-memo-attachment-input" ' +
                    'accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>' +
                    '<small class="text-muted">Max size: 10MB</small></div>';
                $('#other-memo-attachment-container').append(newField);
                otherMemoAttachmentIndex++;
            });
            $('#other-memo-remove-attachment').on('click', function() {
                var $blocks = $('#other-memo-attachment-container .attachment-block');
                if ($blocks.length > 1) {
                    $blocks.last().remove();
                    otherMemoAttachmentIndex--;
                } else if ($blocks.length === 1) {
                    $blocks.last().remove();
                    otherMemoAttachmentIndex = 1;
                }
            });
            $(document).on('change', '.other-memo-attachment-input', function() {
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
})();
</script>
@endsection
