/**
 * Other memo create form — loads memo types via API, Select2, dynamic fields.
 * Booted on DOMContentLoaded and livewire:navigated (inline scripts in morphed HTML do not re-run).
 */
(function () {
    'use strict';

    var types = {};

    function otherMemoFormPagePresent() {
        return !!document.querySelector(
            '[data-apm-livewire-page="other-memos-create"], [data-apm-livewire-page="other-memos-edit"]'
        );
    }

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
        var uploadOnly = isUploadType(slug);
        if (fieldsCard) fieldsCard.classList.toggle('d-none', !t || uploadOnly);
        if (approversCard) approversCard.classList.toggle('d-none', !t);
        if (attCard) attCard.classList.toggle('d-none', !t || !t.attachments_enabled);

        if (t && typeof window.initOtherMemoApprovers === 'function') {
            if (typeof window.runOtherMemoApproversWhenSelect2Ready === 'function') {
                window.runOtherMemoApproversWhenSelect2Ready(window.initOtherMemoApprovers);
            } else {
                window.initOtherMemoApprovers();
            }
        }
        if (t) renderFields(t.fields_schema || []);
        applyUploadTypeAttachmentRules(slug);
        toggleOtherMemoCcCard(t);
    }

    function toggleOtherMemoCcCard(typeDef) {
        var card = document.getElementById('memo-cc-card');
        if (!card) return;
        var enabled = typeDef && !!typeDef.cc_on_approval_enabled;
        card.classList.toggle('d-none', !enabled);
        if (enabled) {
            initOtherMemoCcUi(typeDef);
        }
    }

    function initOtherMemoCcStaffSelect() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $sel = jQuery('#cc_staff_ids');
        if (!$sel.length || !document.body.contains($sel[0])) return;
        if ($sel.hasClass('select2-hidden-accessible')) {
            try { $sel.select2('destroy'); } catch (e) {}
        }
        $sel.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $sel.data('placeholder') || 'Select staff to CC',
            allowClear: true
        });
    }

    function syncOtherMemoCcAllStaffToggle() {
        var allCb = document.getElementById('cc_all_staff');
        var wrap = document.getElementById('memo-cc-specific-wrap');
        var preview = document.getElementById('memo-cc-all-staff-preview');
        if (!allCb) return;
        var allOn = allCb.checked;
        if (wrap) wrap.classList.toggle('d-none', allOn);
        if (preview) preview.classList.toggle('d-none', !allOn);
        if (typeof jQuery !== 'undefined') {
            var $sel = jQuery('#cc_staff_ids');
            if ($sel.length) {
                $sel.prop('disabled', allOn);
                if (allOn) {
                    $sel.val(null).trigger('change');
                }
            }
        }
    }

    function updateOtherMemoCcAllStaffPreview(typeDef) {
        var el = document.getElementById('memo-cc-all-staff-preview-text');
        if (!el) return;
        var heading = (typeDef && typeDef.cc_all_staff_heading) ? String(typeDef.cc_all_staff_heading).trim() : '';
        var label = (typeDef && typeDef.cc_all_staff_label) ? String(typeDef.cc_all_staff_label).trim() : 'All Africa CDC Staff';
        var parts = [];
        if (heading) parts.push(heading);
        if (label) parts.push(label);
        el.textContent = parts.length ? ('Printed as: ' + parts.join(' · ')) : '';
    }

    function initOtherMemoCcUi(typeDef) {
        syncOtherMemoCcAllStaffToggle();
        updateOtherMemoCcAllStaffPreview(typeDef || null);
        runWhenSelect2Ready(initOtherMemoCcStaffSelect);
    }

    function bindOtherMemoCcDelegatesOnce() {
        if (window._apmOtherMemoCcDelegates) return;
        window._apmOtherMemoCcDelegates = true;
        document.addEventListener('change', function (e) {
            if (!otherMemoFormPagePresent()) return;
            if (e.target && e.target.id === 'cc_all_staff') {
                syncOtherMemoCcAllStaffToggle();
            }
        });
    }

    function bootOtherMemoCcOnEdit() {
        var root = document.querySelector('[data-apm-livewire-page="other-memos-edit"]');
        if (!root || root.dataset.ccOnApproval !== '1') return;
        var card = document.getElementById('memo-cc-card');
        if (card) card.classList.remove('d-none');
        initOtherMemoCcUi(null);
    }

    function isUploadType(slug) {
        return String(slug || '').toLowerCase() === 'upload';
    }

    function applyUploadTypeAttachmentRules(slug) {
        if (typeof jQuery === 'undefined') return;
        var upload = isUploadType(slug);
        var $container = jQuery('#other-memo-attachment-container');
        var $addBtn = jQuery('#other-memo-add-attachment');
        var $note = $container.siblings('p.small.text-muted');
        var $files = $container.find('input[type="file"]');

        if (upload) {
            $addBtn.prop('disabled', true).attr('title', 'Upload memo allows one PDF only');
            if (!$container.find('.attachment-block').length) {
                $addBtn.trigger('click');
            }
            // Keep first only
            $container.find('.attachment-block:gt(0)').remove();
            $container.find('input[type="text"][name^="attachments["]').attr('placeholder', 'Document type (e.g. Signed Contract)');
            $files.attr('accept', '.pdf,application/pdf');
            $note.text('Upload type: exactly one PDF file is required (max 10MB).');
        } else {
            $addBtn.prop('disabled', false).removeAttr('title');
            $files.attr('accept', '.pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*');
            $note.text('Max size 10 MB per file. Allowed: PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, DOCX.');
        }
    }

    function runWhenSelect2Ready(fn) {
        var tries = 0;
        (function poll() {
            if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2) {
                fn();
            } else if (tries++ < 240) {
                setTimeout(poll, 25);
            }
        })();
    }

    function initMemoTypeSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $mt = jQuery('#memo_type_slug');
        if (!$mt.length || !document.body.contains($mt[0])) return;
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

    function scheduleMemoTypeSelect2Init() {
        runWhenSelect2Ready(initMemoTypeSelect2);
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

    function nextOtherMemoAttachmentIndex() {
        var max = -1;
        var container = document.getElementById('other-memo-attachment-container');
        if (!container) return 0;
        container.querySelectorAll('input[type="file"][name^="attachments["]').forEach(function (inp) {
            var m = inp.name.match(/^attachments\[(\d+)\]\[file\]$/);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return max + 1;
    }

    function bindAttachmentDelegatesOnce() {
        if (window._apmOtherMemoCreateAttachDelegates || typeof jQuery === 'undefined') return;
        window._apmOtherMemoCreateAttachDelegates = true;

        jQuery(document).on('click.otherMemoCreateAttach', '#other-memo-add-attachment', function () {
            if (!otherMemoCreatePagePresent()) return;
            if (isUploadType(getSelectedMemoTypeSlug()) && jQuery('#other-memo-attachment-container .attachment-block').length >= 1) {
                if (typeof show_notification === 'function') {
                    show_notification('Upload memo allows only one PDF attachment.', 'warning');
                }
                return;
            }
            var idx = nextOtherMemoAttachmentIndex();
            var newField = '<div class="col-md-4 attachment-block">' +
                '<label class="form-label">Document type</label>' +
                '<input type="text" name="attachments[' + idx + '][type]" class="form-control" required>' +
                '<input type="file" name="attachments[' + idx + '][file]" class="form-control mt-1 other-memo-attachment-input" ' +
                'accept=".pdf,.jpg,.jpeg,.png,.ppt,.pptx,.xls,.xlsx,.doc,.docx,image/*" required>' +
                '<small class="text-muted">Max size: 10MB</small></div>';
            jQuery('#other-memo-attachment-container').append(newField);
        });

        jQuery(document).on('click.otherMemoCreateAttach', '#other-memo-remove-attachment', function () {
            if (!otherMemoCreatePagePresent()) return;
            var $blocks = jQuery('#other-memo-attachment-container .attachment-block');
            if ($blocks.length > 1) {
                $blocks.last().remove();
            } else if ($blocks.length === 1) {
                $blocks.last().remove();
            }
        });

        jQuery(document).on('change.otherMemoCreateAttach', '.other-memo-attachment-input', function () {
            if (!otherMemoCreatePagePresent()) return;
            var fileInput = this;
            var file = fileInput.files[0];
            if (!file) return;
            var maxSize = 10 * 1024 * 1024;
            var ext = file.name.split('.').pop().toLowerCase();
            var uploadOnly = isUploadType(getSelectedMemoTypeSlug());
            var allowedExtensions = uploadOnly ? ['pdf'] : ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
            if (allowedExtensions.indexOf(ext) === -1) {
                if (typeof show_notification === 'function') {
                    show_notification(uploadOnly ? 'Upload memo allows PDF files only.' : 'Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, or DOCX files are allowed.', 'warning');
                } else {
                    alert(uploadOnly ? 'Upload memo allows PDF files only.' : 'Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, or DOCX files are allowed.');
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

    function cleanupOtherMemoCreateBeforeNavigate() {
        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        if (root) {
            if (root._apmOtherMemoCreateAbort) {
                root._apmOtherMemoCreateAbort.abort();
                root._apmOtherMemoCreateAbort = null;
            }
            delete root.dataset.apmCreateFetchPending;
        }
        if (typeof jQuery !== 'undefined') {
            var $mt = jQuery('#memo_type_slug');
            if ($mt.length && $mt.hasClass('select2-hidden-accessible')) {
                try { $mt.select2('destroy'); } catch (e) {}
            }
        }
    }

    function scheduleBootOtherMemoCreate() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setTimeout(function () {
                    bootOtherMemoCreateIfPresent();
                    bootOtherMemoCcOnEdit();
                }, 0);
            });
        });
    }

    function bootOtherMemoCreateIfPresent() {
        if (!otherMemoCreatePagePresent()) return;
        bindOtherMemoCcDelegatesOnce();
        if (typeof jQuery === 'undefined') return;

        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        var sel = document.getElementById('memo_type_slug');
        if (!root || !sel) return;

        var apiList = getApiListUrl();
        if (!apiList) return;

        if (root._apmOtherMemoCreateAbort) {
            root._apmOtherMemoCreateAbort.abort();
        }
        var ctrl = new AbortController();
        root._apmOtherMemoCreateAbort = ctrl;

        if (root.dataset.apmCreateFetchPending === '1') {
            scheduleMemoTypeSelect2Init();
            return;
        }
        root.dataset.apmCreateFetchPending = '1';

        jQuery('#other-memo-attachment-container').empty();

        fetch(apiList, {
            signal: ctrl.signal,
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
                scheduleMemoTypeSelect2Init();
                bindSummernoteSubmitOnce();
                bindAttachmentDelegatesOnce();
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                console.error('[APM] Other memo types load failed:', err);
            })
            .finally(function () {
                if (root && root._apmOtherMemoCreateAbort === ctrl) {
                    root._apmOtherMemoCreateAbort = null;
                }
                delete root.dataset.apmCreateFetchPending;
            });
    }

    document.addEventListener('DOMContentLoaded', scheduleBootOtherMemoCreate);
    document.addEventListener('livewire:navigated', scheduleBootOtherMemoCreate);
    document.addEventListener('livewire:navigating', cleanupOtherMemoCreateBeforeNavigate);
})();
