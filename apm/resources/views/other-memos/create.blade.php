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
<div class="other-memo-form-page">
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
            <form method="post" action="{{ route('other-memos.store') }}" id="other-memo-create-form">
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
@endsection

@push('scripts')
<script>
(function() {
    var apiList = @json(route('memo-type-definitions.api.index')) + '?active_only=1';
    var types = {};

    function destroyOtherMemoSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $mt = jQuery('#memo_type_slug');
        if ($mt.length && $mt.hasClass('select2-hidden-accessible')) {
            $mt.select2('destroy');
        }
        jQuery('#approver-rows-container select.approver-staff-id').each(function() {
            if (jQuery(this).hasClass('select2-hidden-accessible')) {
                jQuery(this).select2('destroy');
            }
        });
    }

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
        if (typeof jQuery !== 'undefined') {
            jQuery(host).find('textarea.summernote').each(function() {
                if (!jQuery(this).next('.note-editor').length) {
                    jQuery(this).summernote({ height: 200, dialogsInBody: true, toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'italic', 'underline', 'clear']],
                        ['para', ['ul', 'ol']],
                        ['insert', ['link', 'picture']],
                        ['view', ['codeview', 'help']]
                    ]});
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

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('livewire:navigated', function() {
        document.getElementById('memo_type_slug')?.removeAttribute('data-apm-other-memo-boot');
        document.getElementById('other-memo-create-form')?.removeAttribute('data-apm-summernote-submit-bound');
        boot();
        if (typeof jQuery !== 'undefined' && typeof window.initOtherMemoStaffSelect2 === 'function') {
            jQuery('#approver-rows-container select.approver-staff-id').each(function() {
                window.initOtherMemoStaffSelect2(jQuery(this));
            });
        }
    });
    document.addEventListener('livewire:navigating', destroyOtherMemoSelect2);
})();
</script>
@endpush
