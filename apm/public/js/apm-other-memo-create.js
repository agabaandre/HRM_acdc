/**
 * Other memo create form — loads memo types via API, Select2, dynamic fields.
 * Booted on DOMContentLoaded and livewire:navigated (inline scripts in morphed HTML do not re-run).
 */
(function () {
    'use strict';

    var types = {};
    var ccEnabledSlugsFromServer = [];
    /** @type {Record<string, boolean>} */
    var ccEnabledBySlug = {};

    function ingestCcSlugMap(map) {
        if (!map || typeof map !== 'object') return;
        Object.keys(map).forEach(function (key) {
            var k = String(key).toLowerCase().trim();
            if (!k) return;
            var on = map[key] === true || map[key] === 1 || map[key] === '1';
            ccEnabledBySlug[k] = on;
            if (on && ccEnabledSlugsFromServer.indexOf(k) === -1) {
                ccEnabledSlugsFromServer.push(k);
            }
        });
    }

    function loadCcFlagsFromDom() {
        ccEnabledSlugsFromServer = [];
        ccEnabledBySlug = {};
        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        if (!root) return;
        try {
            ingestCcSlugMap(JSON.parse(root.getAttribute('data-memo-type-cc-by-slug') || '{}'));
        } catch (e) {}
        try {
            var parsed = JSON.parse(root.getAttribute('data-cc-enabled-slugs') || '[]');
            if (Array.isArray(parsed)) {
                parsed.forEach(function (s) {
                    var k = String(s || '').toLowerCase().trim();
                    if (!k) return;
                    ccEnabledBySlug[k] = true;
                    if (ccEnabledSlugsFromServer.indexOf(k) === -1) {
                        ccEnabledSlugsFromServer.push(k);
                    }
                });
            }
        } catch (e2) {}
    }

    function syncCcEnabledSlugsFromApi(rows) {
        if (!Array.isArray(rows)) return;
        var map = {};
        rows.forEach(function (t) {
            if (!t || !t.slug) return;
            map[t.slug] = isMemoTypeCcEnabled(t);
        });
        ingestCcSlugMap(map);
    }

    function syncReferencedMaxFromApi(rows) {
        if (!Array.isArray(rows)) return;
        var root = document.querySelector('[data-apm-livewire-page="other-memos-create"]');
        if (!root) return;
        var map = {};
        try {
            map = JSON.parse(root.getAttribute('data-memo-type-referenced-max-by-slug') || '{}');
        } catch (e) {}
        rows.forEach(function (t) {
            if (!t || !t.slug) return;
            var n = parseInt(t.referenced_memos_max, 10);
            if (isNaN(n)) n = 0;
            n = Math.max(0, Math.min(10, n));
            map[t.slug] = n;
            map[String(t.slug).toLowerCase().trim()] = n;
        });
        root.setAttribute('data-memo-type-referenced-max-by-slug', JSON.stringify(map));
        if (typeof window.apmToggleOtherMemoReferencedCard === 'function') {
            setTimeout(window.apmToggleOtherMemoReferencedCard, 0);
        }
    }

    function isMemoTypeCcEnabled(typeDef) {
        if (!typeDef) return false;
        var v = typeDef.cc_on_approval_enabled;
        if (v === true || v === 1) return true;
        if (typeof v === 'string') {
            var s = v.trim().toLowerCase();
            if (s === '1' || s === 'true' || s === 'yes' || s === 'on') return true;
        }
        return false;
    }

    /** Slug from the native select option value, not Select2 numeric id. */
    function slugFromMemoTypeSelect() {
        var sel = document.getElementById('memo_type_slug');
        if (!sel || sel.selectedIndex < 0) return '';
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) return '';
        return String(opt.value).trim();
    }

    function isSlugCcEnabled(slug) {
        slug = String(slug || '').toLowerCase().trim();
        if (!slug) return false;
        if (ccEnabledBySlug[slug] === true) return true;
        if (ccEnabledSlugsFromServer.indexOf(slug) !== -1) return true;
        var t = resolveType(slug);
        return t ? isMemoTypeCcEnabled(t) : false;
    }

    function memoTypeCcEnabled(typeDef, slug) {
        if (typeDef && typeDef.slug && isSlugCcEnabled(typeDef.slug)) return true;
        return isSlugCcEnabled(slug || slugFromMemoTypeSelect());
    }

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
        var native = slugFromMemoTypeSelect();
        if (native) return native;
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
        var s = String(slug);
        if (types[s]) return types[s];
        var lower = s.toLowerCase();
        if (types[lower]) return types[lower];
        var keys = Object.keys(types);
        for (var i = 0; i < keys.length; i++) {
            if (keys[i].toLowerCase() === lower) return types[keys[i]];
        }
        return null;
    }

    function storeMemoType(t) {
        if (!t || !t.slug) return;
        types[t.slug] = t;
        types[String(t.slug).toLowerCase()] = t;
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

    function applyMemoTypeSelection(slug, typeDef) {
        slug = String(slug || (typeDef && typeDef.slug) || getSelectedMemoTypeSlug() || '');
        var t = typeDef || resolveType(slug);
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
        toggleOtherMemoCcCard(t, slug);
        if (typeof window.apmToggleOtherMemoReferencedCard === 'function') {
            window.apmToggleOtherMemoReferencedCard();
        } else {
            applyReferencedMemosFromType(t);
        }
    }

    function applyReferencedMemosFromType(typeDef) {
        if (typeof window.apmToggleOtherMemoReferencedCard === 'function') {
            window.apmToggleOtherMemoReferencedCard();
            return;
        }
        var card = document.getElementById('memo-referenced-memos-card');
        if (!card) return;
        var max = typeDef ? (parseInt(typeDef.referenced_memos_max, 10) || 0) : 0;
        max = Math.max(0, Math.min(10, max));
        card.setAttribute('data-max-referenced', String(max));
        card.classList.toggle('d-none', max < 1);
        if (max >= 1 && typeof window.apmInitReferencedMemosUi === 'function') {
            window.apmInitReferencedMemosUi(max);
        }
    }

    function bootOtherMemoReferencedOnEdit() {
        if (!document.querySelector('[data-apm-livewire-page="other-memos-edit"]')) return;
        var card = document.getElementById('memo-referenced-memos-card');
        if (!card || card.classList.contains('d-none')) return;
        if (typeof window.apmInitReferencedMemosUi === 'function') {
            window.apmInitReferencedMemosUi(parseInt(card.getAttribute('data-max-referenced') || '0', 10));
        }
    }

    function onMemoTypeChange() {
        applyMemoTypeSelection(getSelectedMemoTypeSlug(), null);
    }

    function onMemoTypeSelect2Pick(e) {
        var slug = slugFromMemoTypeSelect();
        if (!slug && e && e.params && e.params.data && e.params.data.element) {
            var node = e.params.data.element;
            var el = node.jquery ? node[0] : node;
            if (el && el.value) slug = String(el.value).trim();
        }
        if (!slug) slug = getSelectedMemoTypeSlug();
        applyMemoTypeSelection(slug, resolveType(slug));
    }

    function resetOtherMemoCcFields() {
        var includeCb = document.getElementById('cc_include');
        if (includeCb) includeCb.checked = false;
        var opts = document.getElementById('memo-cc-options-wrap');
        if (opts) opts.classList.add('d-none');
        if (typeof jQuery !== 'undefined') {
            var $sel = jQuery('#cc_staff_ids');
            if ($sel.length) {
                $sel.val(null);
                if ($sel.hasClass('select2-hidden-accessible')) {
                    try { $sel.trigger('change'); } catch (e) {}
                }
            }
        }
    }

    function toggleOtherMemoCcCard(typeDef, slug) {
        var card = document.getElementById('memo-cc-card');
        if (!card) return;

        slug = String(slug || getSelectedMemoTypeSlug() || (typeDef ? typeDef.slug : '') || '');
        var enabled = memoTypeCcEnabled(typeDef, slug);

        card.classList.toggle('d-none', !enabled);

        if (enabled) {
            initOtherMemoCcUi();
        } else {
            resetOtherMemoCcFields();
            if (typeof jQuery !== 'undefined') {
                var $sel = jQuery('#cc_staff_ids');
                if ($sel.length && $sel.hasClass('select2-hidden-accessible')) {
                    try { $sel.select2('destroy'); } catch (e) {}
                }
            }
        }
    }

    function getOtherMemoCcMode() {
        var specific = document.getElementById('cc_mode_specific');
        if (specific && specific.checked) return 'specific';
        return 'all';
    }

    function syncOtherMemoCcInclude() {
        var includeCb = document.getElementById('cc_include');
        var opts = document.getElementById('memo-cc-options-wrap');
        if (!includeCb || !opts) return;
        opts.classList.toggle('d-none', !includeCb.checked);
        if (includeCb.checked) {
            syncOtherMemoCcMode();
            runWhenSelect2Ready(initOtherMemoCcStaffSelect);
        }
    }

    function syncOtherMemoCcMode() {
        var mode = getOtherMemoCcMode();
        var allFields = document.getElementById('memo-cc-all-fields');
        var specificWrap = document.getElementById('memo-cc-specific-wrap');
        if (allFields) allFields.classList.toggle('d-none', mode !== 'all');
        if (specificWrap) specificWrap.classList.toggle('d-none', mode !== 'specific');
        if (typeof jQuery !== 'undefined') {
            var $sel = jQuery('#cc_staff_ids');
            if ($sel.length) {
                var specificOn = mode === 'specific';
                $sel.prop('disabled', !specificOn);
                if (specificOn) {
                    runWhenSelect2Ready(initOtherMemoCcStaffSelect);
                }
            }
        }
    }

    function initOtherMemoCcStaffSelect() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        if (getOtherMemoCcMode() !== 'specific') return;
        var includeCb = document.getElementById('cc_include');
        if (includeCb && !includeCb.checked) return;
        var $sel = jQuery('#cc_staff_ids');
        if (!$sel.length || !document.body.contains($sel[0]) || $sel.prop('disabled')) return;
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

    function initOtherMemoCcUi() {
        syncOtherMemoCcInclude();
        runWhenSelect2Ready(initOtherMemoCcStaffSelect);
    }

    function bindMemoTypeChangeForCcOnce() {
        if (window._apmMemoTypeChangeForCc) return;
        window._apmMemoTypeChangeForCc = true;
        document.addEventListener('change', function (e) {
            if (!otherMemoCreatePagePresent()) return;
            if (!e.target || e.target.id !== 'memo_type_slug') return;
            var slug = slugFromMemoTypeSelect();
            applyMemoTypeSelection(slug, resolveType(slug));
        }, true);
    }

    function bindOtherMemoCcDelegatesOnce() {
        if (window._apmOtherMemoCcDelegates) return;
        window._apmOtherMemoCcDelegates = true;
        document.addEventListener('change', function (e) {
            if (!otherMemoFormPagePresent()) return;
            if (!e.target) return;
            if (e.target.id === 'cc_include') {
                syncOtherMemoCcInclude();
                return;
            }
            if (e.target.id === 'cc_mode_all' || e.target.id === 'cc_mode_specific') {
                syncOtherMemoCcMode();
            }
        });
    }

    function bootOtherMemoCcOnEdit() {
        var root = document.querySelector('[data-apm-livewire-page="other-memos-edit"]');
        if (!root || root.dataset.ccOnApproval !== '1') return;
        var card = document.getElementById('memo-cc-card');
        if (card) card.classList.remove('d-none');
        initOtherMemoCcUi();
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
            .on('select2:select.otherMemoType', onMemoTypeSelect2Pick);
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
                    loadCcFlagsFromDom();
                    bindMemoTypeChangeForCcOnce();
                    bindOtherMemoCcDelegatesOnce();
                    bootOtherMemoCreateIfPresent();
                    bootOtherMemoCcOnEdit();
                    bootOtherMemoReferencedOnEdit();
                }, 0);
            });
        });
    }

    function bootOtherMemoCreateIfPresent() {
        if (!otherMemoCreatePagePresent()) return;
        loadCcFlagsFromDom();
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

                var previousSlug = getSelectedMemoTypeSlug();
                types = {};
                var $s = jQuery(sel);
                if ($s.hasClass('select2-hidden-accessible')) {
                    try { $s.select2('destroy'); } catch (e) {}
                }
                sel.innerHTML = '<option value=""></option>';
                syncCcEnabledSlugsFromApi(json.data);
                syncReferencedMaxFromApi(json.data);
                json.data.forEach(function (t) {
                    if (!t || !t.slug) return;
                    storeMemoType(t);
                    var o = document.createElement('option');
                    o.value = t.slug;
                    o.textContent = t.name || t.slug;
                    if (isMemoTypeCcEnabled(t)) {
                        o.setAttribute('data-cc-enabled', '1');
                    }
                    var refMax = parseInt(t.referenced_memos_max, 10);
                    if (!isNaN(refMax) && refMax > 0) {
                        o.setAttribute('data-referenced-max', String(Math.min(10, refMax)));
                    }
                    sel.appendChild(o);
                });
                if (previousSlug && types[previousSlug]) {
                    sel.value = previousSlug;
                }
                scheduleMemoTypeSelect2Init();
                applyMemoTypeSelection(getSelectedMemoTypeSlug(), resolveType(getSelectedMemoTypeSlug()));
                if (typeof window.apmToggleOtherMemoCcCard === 'function') {
                    setTimeout(window.apmToggleOtherMemoCcCard, 0);
                }
                if (typeof window.apmToggleOtherMemoReferencedCard === 'function') {
                    setTimeout(window.apmToggleOtherMemoReferencedCard, 0);
                }
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

    window.apmToggleOtherMemoCcCard = function () {
        loadCcFlagsFromDom();
        toggleOtherMemoCcCard(null, slugFromMemoTypeSelect());
    };

    document.addEventListener('DOMContentLoaded', scheduleBootOtherMemoCreate);
    document.addEventListener('livewire:navigated', scheduleBootOtherMemoCreate);
    document.addEventListener('livewire:navigating', cleanupOtherMemoCreateBeforeNavigate);
})();
