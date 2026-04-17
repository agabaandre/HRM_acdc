/**
 * Other memo create form — loads memo types via API, Select2, dynamic fields.
 * Booted on DOMContentLoaded and livewire:navigated (inline scripts in morphed HTML do not re-run).
 */
(function () {
    'use strict';

    var types = {};

    function otherMemoCreatePagePresent() {
        return !!document.querySelector('[data-apm-livewire-page="other-memos-create"]');
    }

    function getApiListUrl() {
        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        if (!root) return '';
        return (root.getAttribute('data-memo-types-api') || '').trim();
    }

    function getSelectedMemoTypeSlug() {
        var sel = document.getElementById('memo_type_slug');
        if (!sel) return '';
        if (typeof jQuery !== 'undefined') {
            var $s = jQuery(sel);
            if ($s.length) {
                var v = $s.val();
                if (v != null && v !== '') return String(v);
            }
        }
        return String(sel.value || '');
    }

    function resolveType(slug) {
        if (!slug) return null;
        if (types[slug]) return types[slug];
        var lower = slug.toLowerCase();
        var keys = Object.keys(types);
        for (var i = 0; i < keys.length; i++) {
            if (keys[i].toLowerCase() === lower) return types[keys[i]];
        }
        return null;
    }

    function renderFields(schema) {
        var host = document.getElementById('memo-dynamic-fields');
        if (!host) return;

        if (typeof jQuery !== 'undefined') {
            jQuery(host).find('textarea.summernote').each(function () {
                var $t = jQuery(this);
                if ($t.next('.note-editor').length && typeof $t.summernote === 'function') {
                    try { $t.summernote('destroy'); } catch (e) {}
                }
            });
        }

        host.innerHTML = '';

        if (!Array.isArray(schema)) {
            schema = [];
        }
        if (!schema.length) {
            host.innerHTML = '<p class="text-muted mb-0">No fields configured for this type.</p>';
            return;
        }

        schema.forEach(function (f) {
            if (f.enabled === false) return;
            var k = f.field;
            if (!k) return;
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
            jQuery(host).find('textarea.summernote').each(function () {
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
        var slug = getSelectedMemoTypeSlug();
        var t = resolveType(slug);
        var fieldsCard = document.getElementById('memo-fields-card');
        var approversCard = document.getElementById('memo-approvers-card');
        var attCard = document.getElementById('memo-attachments-card');
        if (fieldsCard) fieldsCard.classList.toggle('d-none', !t);
        if (approversCard) approversCard.classList.toggle('d-none', !t);
        if (attCard) attCard.classList.toggle('d-none', !t || !t.attachments_enabled);

        if (t && typeof window.initOtherMemoApprovers === 'function') {
            window.initOtherMemoApprovers();
        }
        if (t) renderFields(t.fields_schema || []);
    }

    function initMemoTypeSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $mt = jQuery('#memo_type_slug');
        if (!$mt.length) return;
        if ($mt.hasClass('select2-hidden-accessible')) {
            try { $mt.select2('destroy'); } catch (e) {}
        }
        $mt.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $mt.data('placeholder') || 'Select memo type',
            allowClear: false
        });
        $mt.off('change.otherMemoType select2:select.otherMemoType')
            .on('change.otherMemoType', onMemoTypeChange)
            .on('select2:select.otherMemoType', onMemoTypeChange);
        onMemoTypeChange();
    }

    function bindSummernoteSubmitOnce() {
        var form = document.getElementById('other-memo-create-form');
        if (!form || form.dataset.apmSummernoteSubmitBound === '1') return;
        form.dataset.apmSummernoteSubmitBound = '1';
        form.addEventListener('submit', function () {
            if (typeof jQuery === 'undefined') return;
            jQuery('#memo-dynamic-fields textarea.summernote').each(function () {
                var $t = jQuery(this);
                if ($t.summernote && $t.next('.note-editor').length) {
                    $t.val($t.summernote('code'));
                }
            });
        });
    }

    function bindAttachmentDelegatesOnce() {
        if (window._apmOtherMemoCreateAttachDelegates || typeof jQuery === 'undefined') return;
        window._apmOtherMemoCreateAttachDelegates = true;

        jQuery(document).on('click.otherMemoCreateAttach', '#other-memo-add-attachment', function () {
            if (!otherMemoCreatePagePresent()) return;
            var idx = window.__apmOtherMemoAttachIdx || 1;
            var newField = '<div class="col-md-4 attachment-block">' +
                '<label class="form-label">Document type</label>' +
                '<input type="text" name="attachments[' + idx + '][type]" class="form-control" required>' +
                '<input type="file" name="attachments[]" class="form-control mt-1 other-memo-attachment-input" ' +
                'accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>' +
                '<small class="text-muted">Max size: 10MB</small></div>';
            jQuery('#other-memo-attachment-container').append(newField);
            window.__apmOtherMemoAttachIdx = idx + 1;
        });

        jQuery(document).on('click.otherMemoCreateAttach', '#other-memo-remove-attachment', function () {
            if (!otherMemoCreatePagePresent()) return;
            var $blocks = jQuery('#other-memo-attachment-container .attachment-block');
            if ($blocks.length > 1) {
                $blocks.last().remove();
                window.__apmOtherMemoAttachIdx = Math.max(1, (window.__apmOtherMemoAttachIdx || 2) - 1);
            } else if ($blocks.length === 1) {
                $blocks.last().remove();
                window.__apmOtherMemoAttachIdx = 1;
            }
        });

        jQuery(document).on('change.otherMemoCreateAttach', '.other-memo-attachment-input', function () {
            if (!otherMemoCreatePagePresent()) return;
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
                jQuery(fileInput).val('');
                return;
            }
            if (file.size > maxSize) {
                if (typeof show_notification === 'function') {
                    show_notification('File size must be less than 10MB.', 'warning');
                } else {
                    alert('File size must be less than 10MB.');
                }
                jQuery(fileInput).val('');
            }
        });
    }

    function bootOtherMemoCreateIfPresent() {
        if (!otherMemoCreatePagePresent()) return;
        if (typeof jQuery === 'undefined') return;

        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        var sel = document.getElementById('memo_type_slug');
        if (!root || !sel) return;

        var apiList = getApiListUrl();
        if (!apiList) return;

        if (root.dataset.apmCreateFetchPending === '1') return;
        root.dataset.apmCreateFetchPending = '1';

        window.__apmOtherMemoAttachIdx = 1;

        fetch(apiList, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (json) {
                if (!json.success || !Array.isArray(json.data)) {
                    throw new Error('Invalid memo types response');
                }
                if (!document.body.contains(sel)) return;

                types = {};
                var $s = jQuery(sel);
                if ($s.hasClass('select2-hidden-accessible')) {
                    try { $s.select2('destroy'); } catch (e) {}
                }
                sel.innerHTML = '<option value=""></option>';
                json.data.forEach(function (t) {
                    if (!t || !t.slug) return;
                    types[t.slug] = t;
                    var o = document.createElement('option');
                    o.value = t.slug;
                    o.textContent = t.name || t.slug;
                    sel.appendChild(o);
                });
                initMemoTypeSelect2();
                bindSummernoteSubmitOnce();
                bindAttachmentDelegatesOnce();
            })
            .catch(function (err) {
                console.error('[APM] Other memo types load failed:', err);
            })
            .finally(function () {
                delete root.dataset.apmCreateFetchPending;
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(bootOtherMemoCreateIfPresent, 0);
    });
    document.addEventListener('livewire:navigated', function () {
        setTimeout(bootOtherMemoCreateIfPresent, 0);
    });
})();
