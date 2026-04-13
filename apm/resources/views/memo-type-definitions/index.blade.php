@extends('layouts.app')

@section('title', 'Other memo types')

@section('header', 'Other memo types')

@section('header-actions')
<button type="button" class="btn btn-success" id="memo-type-add-btn">
    <i class="bx bx-plus me-1"></i>Add memo type
</button>
@endsection

@section('content')
<style>
    .memo-type-table-col-name {
        max-width: 400px;
        width: 400px;
        white-space: normal !important;
        word-break: break-word;
        overflow-wrap: anywhere;
        vertical-align: top;
    }
</style>
<div data-apm-livewire-page="memo-type-definitions">
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0"><i class="bx bx-file-blank me-2 text-primary"></i>Memo type catalogue</h5>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="search" id="memo-type-search" class="form-control form-control-sm" style="min-width:220px" placeholder="Search name, slug, ref prefix…" autocomplete="off">
            <button type="button" class="btn btn-sm btn-outline-primary" id="memo-type-reload">
                <i class="bx bx-refresh"></i> Reload
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="memo-type-table">
                <thead class="table-light">
                    <tr>
                        <th style="width:48px">#</th>
                        <th class="memo-type-table-col-name">Name</th>
                        <th>On create</th>
                        <th>Attachments</th>
                        <th>Slug</th>
                        <th>Ref prefix</th>
                        <th>Scope</th>
                        <th>Signature</th>
                        <th>Fields</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="memo-type-tbody">
                    <tr id="memo-type-loading-row">
                        <td colspan="10" class="text-center py-4 text-muted">
                            <span class="spinner-border spinner-border-sm me-2"></span> Loading…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer small text-muted">
        System types cannot be deleted (slug is fixed). Use <strong>Edit</strong> to turn <strong>Available on other memo create</strong> on or off and to adjust fields. Custom types can also be deleted.
    </div>
</div>

{{-- View --}}
<div class="modal fade" id="memo-type-view-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="memo-type-view-title">Memo type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-3">
                    <dt class="col-sm-3">Ref prefix</dt>
                    <dd class="col-sm-9" id="memo-type-view-ref"></dd>
                    <dt class="col-sm-3">Scope</dt>
                    <dd class="col-sm-9" id="memo-type-view-scope"></dd>
                    <dt class="col-sm-3">On create</dt>
                    <dd class="col-sm-9" id="memo-type-view-active"></dd>
                    <dt class="col-sm-3">Approval attachments</dt>
                    <dd class="col-sm-9" id="memo-type-view-attachments"></dd>
                    <dt class="col-sm-3">Signature style</dt>
                    <dd class="col-sm-9" id="memo-type-view-sig"></dd>
                    <dt class="col-sm-3">Slug</dt>
                    <dd class="col-sm-9"><code id="memo-type-view-slug"></code></dd>
                </dl>
                <h6 class="border-bottom pb-2">Field schema</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="memo-type-view-fields-table">
                        <thead><tr><th>Field key</th><th>Display label</th><th>Type</th><th>Required</th><th>On form</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <h6 class="border-bottom pb-2 mt-4">Live preview (sample form)</h6>
                <p class="small text-muted">Rich text fields use Summernote as they will in memo forms.</p>
                <div id="memo-type-preview-host" class="border rounded p-3 bg-light"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Create / Edit --}}
<div class="modal fade" id="memo-type-form-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="memo-type-form-title">Memo type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="memo-type-form">
                    <input type="hidden" id="memo-type-form-id" value="">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="memo-type-form-name" required maxlength="500">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Slug</label>
                            <input type="text" class="form-control" id="memo-type-form-slug" placeholder="auto from name if empty" maxlength="191" pattern="[a-z0-9]+(?:-[a-z0-9]+)*">
                            <div class="form-text" id="memo-type-form-slug-hint-custom">Lowercase letters, numbers, hyphens.</div>
                            <div class="form-text text-muted" id="memo-type-form-slug-hint-system" style="display:none">System types: slug cannot be changed.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reference prefix</label>
                            <input type="text" class="form-control" id="memo-type-form-ref" maxlength="32" placeholder="e.g. ARF-">
                            <div class="form-text">Used in the document ref (letters/numbers only in the number, e.g. <code>ARF</code> from <code>ARF-</code>).</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Signature position <span class="text-danger">*</span></label>
                            <select class="form-select" id="memo-type-form-signature" required></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sort order</label>
                            <input type="number" class="form-control" id="memo-type-form-sort" min="0" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="memo-type-form-desc" rows="2" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="memo-type-form-active" checked>
                                <label class="form-check-label" for="memo-type-form-active">Available on other memo create</label>
                            </div>
                            <p class="form-text mb-2">When unchecked, this type is <strong>disabled</strong> for users — it will not appear in the memo type list on the create page.</p>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="memo-type-form-division-specific">
                                <label class="form-check-label" for="memo-type-form-division-specific">Division-specific reference</label>
                            </div>
                            <p class="small text-muted mb-0">If checked, issued numbers include the creator&rsquo;s division short name: <code>AU/CDC/<em>DIV</em>/IM/<em>REF</em>/YY/####</code>. If unchecked: <code>AU/CDC/IM/<em>REF</em>/YY/####</code> (YY = two-digit year).</p>
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="memo-type-form-attachments">
                                <label class="form-check-label" for="memo-type-form-attachments">Enable approval attachments</label>
                            </div>
                            <p class="small text-muted mb-0">When on, creators can attach supporting documents on other memo create/edit (same file rules as matrix activities).</p>
                        </div>
                    </div>
                    <h6 class="mt-4 border-bottom pb-2">Fields <span class="text-danger">*</span></h6>
                    <p class="small text-muted">Each row: internal key (<code>snake_case</code>), label, and input type. Uncheck <strong>On form</strong> to hide a field from memo create/edit/PDF while keeping its definition (e.g. reserved for later).</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="memo-type-fields-editor">
                            <thead><tr><th>Field key</th><th>Display label</th><th>Field type</th><th>Required</th><th>On form</th><th></th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="memo-type-field-add-row"><i class="bx bx-plus"></i> Add field</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="memo-type-form-save"><i class="bx bx-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>
</div>

<script>
(function() {
    var listUrl = @json(route('memo-type-definitions.api.index'));
    function apiItemUrl(id) {
        return listUrl.replace(/\/?$/, '') + '/' + encodeURIComponent(id);
    }
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';

    var signatureOptions = @json(\App\Models\MemoTypeDefinition::SIGNATURE_STYLES);
    var fieldTypeOptions = @json(\App\Models\MemoTypeDefinition::FIELD_TYPES);

    function escHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    /** Same stack as activities: layouts footer defines show_notification → Lobibox.notify */
    function memoTypeNotify(message, msgtype) {
        msgtype = msgtype || 'info';
        if (msgtype === 'danger') {
            msgtype = 'error';
        }
        if (typeof show_notification === 'function') {
            show_notification(message, msgtype);
        } else {
            window.alert(message);
        }
    }

    function memoTypeSaveErrorMessage(j) {
        var msg = (j && j.message) ? j.message : 'Save failed';
        if (j && j.errors && typeof j.errors === 'object') {
            var lines = [];
            Object.keys(j.errors).forEach(function(k) {
                var v = j.errors[k];
                if (Array.isArray(v)) {
                    lines.push(v.join(' '));
                } else if (typeof v === 'string') {
                    lines.push(v);
                }
            });
            if (lines.length) {
                return lines.slice(0, 6).join(' ');
            }
        }
        return msg;
    }

    function setFormSystemSlugMode(isSystem) {
        var slugEl = document.getElementById('memo-type-form-slug');
        slugEl.readOnly = !!isSystem;
        slugEl.classList.toggle('bg-light', !!isSystem);
        document.getElementById('memo-type-form-slug-hint-custom').style.display = isSystem ? 'none' : '';
        document.getElementById('memo-type-form-slug-hint-system').style.display = isSystem ? '' : 'none';
    }

    function destroySummernoteIn($root) {
        if (typeof jQuery === 'undefined') return;
        $root.find('textarea.memo-type-sn').each(function() {
            var $t = jQuery(this);
            if ($t.next('.note-editor').length && typeof $t.summernote === 'function') {
                try { $t.summernote('destroy'); } catch (e) {}
            }
        });
    }

    function summernoteOptions() {
        return {
            placeholder: 'Sample rich text…',
            height: 160,
            minHeight: 120,
            dialogsInBody: true,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture']],
                ['view', ['codeview', 'help']]
            ],
            callbacks: {
                onImageUpload: function(files) {
                    for (var i = 0; i < files.length; i++) {
                        if (typeof uploadImage === 'function') uploadImage(files[i], this);
                    }
                }
            }
        };
    }

    function loadList() {
        var q = (document.getElementById('memo-type-search').value || '').trim();
        var url = listUrl + (q ? ('?q=' + encodeURIComponent(q)) : '');
        var tbody = document.getElementById('memo-type-tbody');
        tbody.innerHTML = '<tr><td colspan="10" class="text-center py-3 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> Loading…</td></tr>';
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(json) {
                if (!json.success || !Array.isArray(json.data)) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Failed to load</td></tr>';
                    memoTypeNotify('Could not load memo types.', 'error');
                    return;
                }
                if (json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No memo types found.</td></tr>';
                    return;
                }
                var rows = json.data.map(function(m, idx) {
                    var fc = (m.fields_schema || []).filter(function(f) { return f.enabled !== false; }).length;
                    var sysBadge = m.is_system ? '<span class="badge bg-secondary">System</span>' : '<span class="badge bg-info">Custom</span>';
                    var scopeBadge = m.is_division_specific ? '<span class="badge bg-warning text-dark">Division</span>' : '<span class="badge bg-light text-dark">Org-wide</span>';
                    var createBadge = m.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">Disabled</span>';
                    var attBadge = m.attachments_enabled ? '<span class="badge bg-primary">On</span>' : '<span class="badge bg-light text-dark">Off</span>';
                    var actions = '<button type="button" class="btn btn-sm btn-outline-info me-1 memo-type-btn-view" data-id="' + m.id + '">View</button>';
                    actions += '<button type="button" class="btn btn-sm btn-outline-primary me-1 memo-type-btn-edit" data-id="' + m.id + '">Edit</button>';
                    if (!m.is_system) {
                        actions += '<button type="button" class="btn btn-sm btn-outline-danger memo-type-btn-del" data-id="' + m.id + '">Delete</button>';
                    }
                    var trClass = m.is_active ? '' : ' class="table-light text-muted"';
                    return '<tr' + trClass + '>' +
                        '<td>' + (idx + 1) + '</td>' +
                        '<td class="memo-type-table-col-name">' + escHtml(m.name) + ' ' + sysBadge + '</td>' +
                        '<td>' + createBadge + '</td>' +
                        '<td>' + attBadge + '</td>' +
                        '<td><code>' + escHtml(m.slug) + '</code></td>' +
                        '<td>' + escHtml(m.ref_prefix || '—') + '</td>' +
                        '<td>' + scopeBadge + '</td>' +
                        '<td>' + escHtml(m.signature_style_label || m.signature_style) + '</td>' +
                        '<td><span class="badge bg-light text-dark">' + fc + '</span></td>' +
                        '<td class="text-end text-nowrap">' + actions + '</td>' +
                        '</tr>';
                }).join('');
                tbody.innerHTML = rows;
            })
            .catch(function() {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger py-3">Network error</td></tr>';
                memoTypeNotify('Network error while loading memo types.', 'error');
            });
    }

    function fillSignatureSelect($sel, selected) {
        $sel.empty();
        Object.keys(signatureOptions).forEach(function(k) {
            $sel.append(jQuery('<option>', { value: k, text: signatureOptions[k] }));
        });
        if (selected) $sel.val(selected);
    }

    function fillFieldTypeSelect($sel, selected) {
        $sel.empty();
        Object.keys(fieldTypeOptions).forEach(function(k) {
            $sel.append(jQuery('<option>', { value: k, text: fieldTypeOptions[k] }));
        });
        if (selected) $sel.val(selected);
    }

    function addFieldEditorRow(field, display, ftype, required, enabled) {
        var $tbody = jQuery('#memo-type-fields-editor tbody');
        var $tr = jQuery('<tr>');
        var onForm = enabled !== false;
        $tr.append(jQuery('<td>').append(jQuery('<input>', { type: 'text', class: 'form-control form-control-sm fld-key', value: field || '', placeholder: 'e.g. title', required: true })));
        $tr.append(jQuery('<td>').append(jQuery('<input>', { type: 'text', class: 'form-control form-control-sm fld-display', value: display || '', placeholder: 'Label', required: true })));
        var $ft = jQuery('<select>', { class: 'form-select form-select-sm fld-type' });
        fillFieldTypeSelect($ft, ftype || 'text');
        $tr.append(jQuery('<td>').append($ft));
        $tr.append(jQuery('<td>').append(jQuery('<input>', { type: 'checkbox', class: 'form-check-input fld-req', checked: !!required })));
        $tr.append(jQuery('<td>').append(jQuery('<input>', { type: 'checkbox', class: 'form-check-input fld-enabled', title: 'Show on memo form', checked: onForm })));
        $tr.append(jQuery('<td>').append(jQuery('<button>', { type: 'button', class: 'btn btn-sm btn-outline-danger fld-remove', html: '<i class="bx bx-trash"></i>' })));
        $tbody.append($tr);
    }

    function readFieldsFromEditor() {
        var out = [];
        jQuery('#memo-type-fields-editor tbody tr').each(function() {
            var $tr = jQuery(this);
            var field = ($tr.find('.fld-key').val() || '').trim();
            var display = ($tr.find('.fld-display').val() || '').trim();
            var field_type = $tr.find('.fld-type').val();
            var required = $tr.find('.fld-req').is(':checked');
            var enabled = $tr.find('.fld-enabled').is(':checked');
            if (field && display && field_type) out.push({ field: field, display: display, field_type: field_type, required: required, enabled: enabled });
        });
        return out;
    }

    function buildPreview(fields) {
        var host = document.getElementById('memo-type-preview-host');
        destroySummernoteIn(jQuery(host));
        host.innerHTML = '';
        if (!fields || !fields.length) {
            host.innerHTML = '<p class="text-muted small mb-0">No fields.</p>';
            return;
        }
        fields.forEach(function(f) {
            if (f.enabled === false) return;
            var id = 'mt-prev-' + f.field.replace(/[^a-z0-9_]/gi, '_');
            var wrap = document.createElement('div');
            wrap.className = 'mb-3';
            var lbl = document.createElement('label');
            lbl.className = 'form-label fw-semibold';
            lbl.textContent = f.display + (f.required ? ' *' : '');
            wrap.appendChild(lbl);
            if (f.field_type === 'text_summernote') {
                var ta = document.createElement('textarea');
                ta.className = 'form-control memo-type-sn';
                ta.id = id;
                ta.rows = 3;
                wrap.appendChild(ta);
            } else if (f.field_type === 'textarea') {
                var ta2 = document.createElement('textarea');
                ta2.className = 'form-control';
                ta2.rows = 3;
                ta2.placeholder = 'Sample…';
                wrap.appendChild(ta2);
            } else if (f.field_type === 'number') {
                var num = document.createElement('input');
                num.type = 'number';
                num.className = 'form-control';
                wrap.appendChild(num);
            } else if (f.field_type === 'date') {
                var dt = document.createElement('input');
                dt.type = 'date';
                dt.className = 'form-control';
                wrap.appendChild(dt);
            } else if (f.field_type === 'email') {
                var em = document.createElement('input');
                em.type = 'email';
                em.className = 'form-control';
                em.placeholder = 'email@example.org';
                wrap.appendChild(em);
            } else {
                var tx = document.createElement('input');
                tx.type = 'text';
                tx.className = 'form-control';
                tx.placeholder = 'Sample text';
                wrap.appendChild(tx);
            }
            host.appendChild(wrap);
        });
    }

    function openViewModal(m) {
        document.getElementById('memo-type-view-title').textContent = m.name;
        document.getElementById('memo-type-view-ref').textContent = m.ref_prefix || '—';
        document.getElementById('memo-type-view-scope').textContent = m.is_division_specific ? 'Division-specific (ref includes division code)' : 'Organisation-wide';
        document.getElementById('memo-type-view-active').textContent = m.is_active ? 'Yes — listed on other memo create' : 'No — disabled for create';
        document.getElementById('memo-type-view-attachments').textContent = m.attachments_enabled ? 'Yes — file uploads on memo forms' : 'No';
        document.getElementById('memo-type-view-sig').textContent = m.signature_style_label || m.signature_style;
        document.getElementById('memo-type-view-slug').textContent = m.slug;
        var $ftbody = jQuery('#memo-type-view-fields-table tbody');
        $ftbody.empty();
        (m.fields_schema || []).forEach(function(f) {
            var onForm = f.enabled !== false ? 'Yes' : 'No';
            $ftbody.append('<tr><td><code>' + escHtml(f.field) + '</code></td><td>' + escHtml(f.display) + '</td><td>' + escHtml(fieldTypeOptions[f.field_type] || f.field_type) + '</td><td>' + (f.required ? 'Yes' : 'No') + '</td><td>' + onForm + '</td></tr>');
        });
        buildPreview(m.fields_schema || []);
        var modal = new bootstrap.Modal(document.getElementById('memo-type-view-modal'));
        modal.show();
    }

    function memoTypeCatalogPagePresent() {
        return !!document.querySelector('[data-apm-livewire-page="memo-type-definitions"]');
    }

    function openMemoTypeAddModal() {
        document.getElementById('memo-type-form-title').textContent = 'Add memo type';
        document.getElementById('memo-type-form-id').value = '';
        setFormSystemSlugMode(false);
        fillSignatureSelect(jQuery('#memo-type-form-signature'), 'top_right');
        jQuery('#memo-type-fields-editor tbody').empty();
        document.getElementById('memo-type-form-division-specific').checked = false;
        document.getElementById('memo-type-form-attachments').checked = false;
        addFieldEditorRow('title', 'Title', 'text', true, true);
        addFieldEditorRow('body', 'Body', 'text_summernote', true, true);
        new bootstrap.Modal(document.getElementById('memo-type-form-modal')).show();
    }

    function saveMemoTypeForm() {
        var id = document.getElementById('memo-type-form-id').value;
        var fields = readFieldsFromEditor();
        if (!fields.length) {
            memoTypeNotify('Add at least one field.', 'warning');
            return;
        }
        var payload = {
            name: document.getElementById('memo-type-form-name').value.trim(),
            slug: document.getElementById('memo-type-form-slug').value.trim() || null,
            ref_prefix: document.getElementById('memo-type-form-ref').value.trim() || null,
            description: document.getElementById('memo-type-form-desc').value.trim() || null,
            signature_style: document.getElementById('memo-type-form-signature').value,
            sort_order: parseInt(document.getElementById('memo-type-form-sort').value, 10) || 0,
            is_active: document.getElementById('memo-type-form-active').checked,
            is_division_specific: document.getElementById('memo-type-form-division-specific').checked,
            attachments_enabled: document.getElementById('memo-type-form-attachments').checked,
            fields_schema: fields
        };
        var url = id ? apiItemUrl(id) : listUrl;
        var method = id ? 'PUT' : 'POST';
        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        }).then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
          .then(function(res) {
            if (res.ok && res.j.success) {
                memoTypeNotify(res.j.message || 'Memo type saved.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('memo-type-form-modal')).hide();
                loadList();
            } else {
                memoTypeNotify(memoTypeSaveErrorMessage(res.j), 'error');
            }
          })
          .catch(function() {
            memoTypeNotify('Could not reach the server. Check your connection and try again.', 'error');
          });
    }

    /** Single delegated bindings for wire:navigate (new DOM each visit; layout script stacks do not re-run). */
    function bindMemoTypeCatalogGlobalsOnce() {
        if (window.__apmMemoTypeCatalogGlobalsBound) return;
        window.__apmMemoTypeCatalogGlobalsBound = true;

        document.addEventListener('click', function(e) {
            if (!memoTypeCatalogPagePresent()) return;

            if (e.target.closest('#memo-type-add-btn')) {
                e.preventDefault();
                openMemoTypeAddModal();
                return;
            }
            if (e.target.closest('#memo-type-reload')) {
                e.preventDefault();
                loadList();
                return;
            }
            if (e.target.closest('#memo-type-form-save')) {
                e.preventDefault();
                saveMemoTypeForm();
                return;
            }
            if (e.target.closest('#memo-type-field-add-row')) {
                e.preventDefault();
                addFieldEditorRow('', '', 'text', false, true);
                return;
            }
            var fldRem = e.target.closest('#memo-type-fields-editor .fld-remove');
            if (fldRem) {
                e.preventDefault();
                jQuery(fldRem).closest('tr').remove();
                return;
            }

            var inTbody = e.target.closest('#memo-type-tbody');
            if (!inTbody) return;

            var btnView = e.target.closest('.memo-type-btn-view');
            if (btnView) {
                e.preventDefault();
                var idV = btnView.getAttribute('data-id');
                fetch(apiItemUrl(idV), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        if (json.success && json.data) {
                            openViewModal(json.data);
                        } else {
                            memoTypeNotify('Could not load memo type details.', 'error');
                        }
                    })
                    .catch(function() {
                        memoTypeNotify('Network error while loading details.', 'error');
                    });
                return;
            }
            var btnEdit = e.target.closest('.memo-type-btn-edit');
            if (btnEdit) {
                e.preventDefault();
                var id = btnEdit.getAttribute('data-id');
                fetch(apiItemUrl(id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(json) {
                        if (!json.success || !json.data) {
                            memoTypeNotify('Could not load this memo type for editing.', 'error');
                            return;
                        }
                        var m = json.data;
                        document.getElementById('memo-type-form-title').textContent = 'Edit memo type';
                        document.getElementById('memo-type-form-id').value = m.id;
                        document.getElementById('memo-type-form-name').value = m.name;
                        document.getElementById('memo-type-form-slug').value = m.slug;
                        document.getElementById('memo-type-form-ref').value = m.ref_prefix || '';
                        document.getElementById('memo-type-form-desc').value = m.description || '';
                        document.getElementById('memo-type-form-sort').value = m.sort_order;
                        document.getElementById('memo-type-form-active').checked = !!m.is_active;
                        document.getElementById('memo-type-form-division-specific').checked = !!m.is_division_specific;
                        document.getElementById('memo-type-form-attachments').checked = !!m.attachments_enabled;
                        fillSignatureSelect(jQuery('#memo-type-form-signature'), m.signature_style);
                        var $tb = jQuery('#memo-type-fields-editor tbody');
                        $tb.empty();
                        (m.fields_schema || []).forEach(function(f) {
                            addFieldEditorRow(f.field, f.display, f.field_type, f.required, f.enabled);
                        });
                        setFormSystemSlugMode(!!m.is_system);
                        new bootstrap.Modal(document.getElementById('memo-type-form-modal')).show();
                    })
                    .catch(function() {
                        memoTypeNotify('Network error while loading memo type.', 'error');
                    });
                return;
            }
            var btnDel = e.target.closest('.memo-type-btn-del');
            if (btnDel) {
                e.preventDefault();
                if (!confirm('Delete this memo type?')) return;
                var idDel = btnDel.getAttribute('data-id');
                fetch(apiItemUrl(idDel), {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); }).then(function(json) {
                    if (json.success) {
                        memoTypeNotify(json.message || 'Memo type deleted.', 'success');
                        loadList();
                    } else {
                        memoTypeNotify(json.message || 'Delete failed', 'error');
                    }
                }).catch(function() {
                    memoTypeNotify('Network error while deleting.', 'error');
                });
            }
        });

        document.addEventListener('keyup', function(ev) {
            if (!memoTypeCatalogPagePresent()) return;
            if (ev.target && ev.target.id === 'memo-type-search' && ev.key === 'Enter') loadList();
        });

        document.addEventListener('hidden.bs.modal', function(ev) {
            if (ev.target && ev.target.id === 'memo-type-view-modal') {
                destroySummernoteIn(jQuery('#memo-type-preview-host'));
                var h = document.getElementById('memo-type-preview-host');
                if (h) h.innerHTML = '';
            }
            if (ev.target && ev.target.id === 'memo-type-form-modal') {
                var form = document.getElementById('memo-type-form');
                if (form) form.reset();
                var fid = document.getElementById('memo-type-form-id');
                if (fid) fid.value = '';
                var divSpec = document.getElementById('memo-type-form-division-specific');
                if (divSpec) divSpec.checked = false;
                var attEn = document.getElementById('memo-type-form-attachments');
                if (attEn) attEn.checked = false;
                setFormSystemSlugMode(false);
                jQuery('#memo-type-fields-editor tbody').empty();
            }
        });

        document.addEventListener('shown.bs.modal', function(ev) {
            if (ev.target && ev.target.id === 'memo-type-view-modal') {
                jQuery('#memo-type-preview-host textarea.memo-type-sn').each(function() {
                    if (!jQuery(this).next('.note-editor').length) jQuery(this).summernote(summernoteOptions());
                });
            }
        });
    }

    function bootMemoTypeCatalog() {
        if (!memoTypeCatalogPagePresent()) return;
        bindMemoTypeCatalogGlobalsOnce();
        loadList();
    }

    document.addEventListener('DOMContentLoaded', bootMemoTypeCatalog);
    document.addEventListener('livewire:navigated', bootMemoTypeCatalog);
})();
</script>
@endsection
