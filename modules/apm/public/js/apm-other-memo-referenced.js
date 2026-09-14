/**
 * Other memo create — referenced approved memos card (standalone; mirrors apm-other-memo-cc.js).
 * Does not rely on memo type object resolution from the async API fetch.
 */
(function () {
    'use strict';

    function createPageRoot() {
        return document.querySelector('[data-apm-livewire-page="other-memos-create"]');
    }

    function loadReferencedMaxMap() {
        var root = createPageRoot();
        if (!root) return {};
        var out = {};
        try {
            var raw = JSON.parse(root.getAttribute('data-memo-type-referenced-max-by-slug') || '{}');
            Object.keys(raw).forEach(function (key) {
                var k = String(key).toLowerCase().trim();
                if (!k) return;
                var n = parseInt(raw[key], 10);
                out[k] = isNaN(n) ? 0 : Math.max(0, Math.min(10, n));
            });
        } catch (e) {}
        return out;
    }

    function selectedMemoTypeSlug() {
        var sel = document.getElementById('memo_type_slug');
        if (!sel) return '';
        if (sel.selectedIndex >= 0 && sel.options[sel.selectedIndex] && sel.options[sel.selectedIndex].value) {
            return String(sel.options[sel.selectedIndex].value).trim();
        }
        if (typeof jQuery !== 'undefined') {
            var v = jQuery(sel).val();
            if (v != null && v !== '') return String(v).trim();
        }
        return String(sel.value || '').trim();
    }

    function referencedMaxForSlug(slug, map) {
        slug = String(slug || '').toLowerCase().trim();
        if (!slug) return 0;
        if (map[slug] !== undefined) return map[slug];

        var sel = document.getElementById('memo_type_slug');
        if (sel && sel.selectedIndex >= 0) {
            var opt = sel.options[sel.selectedIndex];
            if (opt && opt.getAttribute('data-referenced-max') != null) {
                var fromOpt = parseInt(opt.getAttribute('data-referenced-max'), 10);
                if (!isNaN(fromOpt)) return Math.max(0, Math.min(10, fromOpt));
            }
        }

        return 0;
    }

    function appendReferencedMemoLinkRow(value) {
        var container = document.getElementById('referenced-memo-links-container');
        if (!container) return;
        var n = container.querySelectorAll('.referenced-memo-link-row').length + 1;
        var wrap = document.createElement('div');
        wrap.className = 'mb-2 referenced-memo-link-row';
        var lab = document.createElement('label');
        lab.className = 'form-label small text-muted mb-1';
        lab.textContent = 'Reference ' + n;
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.name = 'referenced_memo_links[]';
        inp.className = 'form-control referenced-memo-link-input';
        inp.placeholder = 'Paste memo URL from your browser (approved memos only)';
        inp.value = value || '';
        wrap.appendChild(lab);
        wrap.appendChild(inp);
        container.appendChild(wrap);
    }

    function renumberReferencedMemoLabels() {
        var rows = document.querySelectorAll('#referenced-memo-links-container .referenced-memo-link-row');
        rows.forEach(function (row, idx) {
            var lab = row.querySelector('label');
            if (lab) lab.textContent = 'Reference ' + (idx + 1);
        });
    }

    function syncReferencedMemoRowButtons(max) {
        var rows = document.querySelectorAll('.referenced-memo-link-row');
        var addBtn = document.getElementById('referenced-memo-add-link');
        var remBtn = document.getElementById('referenced-memo-remove-link');
        if (addBtn) addBtn.disabled = rows.length >= max;
        if (remBtn) remBtn.disabled = rows.length <= 1;
    }

    function initReferencedMemosUi(max) {
        max = Math.max(0, Math.min(10, parseInt(max, 10) || 0));
        var label = document.getElementById('referenced-memos-max-label');
        if (label) label.textContent = String(max);
        var container = document.getElementById('referenced-memo-links-container');
        if (container && container.querySelectorAll('.referenced-memo-link-row').length === 0 && max > 0) {
            appendReferencedMemoLinkRow('');
        }
        renumberReferencedMemoLabels();
        syncReferencedMemoRowButtons(max);
    }

    function bindReferencedMemoLinkDelegatesOnce() {
        if (window._apmOtherMemoReferencedLinkBound) return;
        window._apmOtherMemoReferencedLinkBound = true;
        document.addEventListener('click', function (e) {
            if (!createPageRoot()) return;
            var card = document.getElementById('memo-referenced-memos-card');
            if (!card || card.classList.contains('d-none')) return;
            var max = parseInt(card.getAttribute('data-max-referenced') || '0', 10);
            if (e.target.closest('#referenced-memo-add-link')) {
                e.preventDefault();
                if (document.querySelectorAll('.referenced-memo-link-row').length >= max) return;
                appendReferencedMemoLinkRow('');
                renumberReferencedMemoLabels();
                syncReferencedMemoRowButtons(max);
            }
            if (e.target.closest('#referenced-memo-remove-link')) {
                e.preventDefault();
                var all = document.querySelectorAll('.referenced-memo-link-row');
                if (all.length <= 1) return;
                all[all.length - 1].remove();
                renumberReferencedMemoLabels();
                syncReferencedMemoRowButtons(max);
            }
        });
    }

    function toggleReferencedCard() {
        var card = document.getElementById('memo-referenced-memos-card');
        if (!card) return;
        var map = loadReferencedMaxMap();
        var slug = selectedMemoTypeSlug();
        var max = referencedMaxForSlug(slug, map);
        card.setAttribute('data-max-referenced', String(max));
        card.classList.toggle('d-none', max < 1);
        if (max >= 1) {
            var container = document.getElementById('referenced-memo-links-container');
            if (container && !container.querySelector('.referenced-memo-link-row')) {
                container.innerHTML = '';
                appendReferencedMemoLinkRow('');
            }
            initReferencedMemosUi(max);
        }
    }

    function bindMemoTypeReferencedOnce() {
        if (window._apmOtherMemoReferencedTypeBound) return;
        window._apmOtherMemoReferencedTypeBound = true;
        document.addEventListener('change', function (e) {
            if (!createPageRoot()) return;
            if (e.target && e.target.id === 'memo_type_slug') {
                toggleReferencedCard();
            }
        }, true);
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('select2:select.apmOtherMemoReferenced', '#memo_type_slug', function () {
                setTimeout(toggleReferencedCard, 0);
            });
            jQuery(document).on('change.apmOtherMemoReferenced', '#memo_type_slug', function () {
                setTimeout(toggleReferencedCard, 0);
            });
        }
    }

    function bootOtherMemoReferenced() {
        if (!createPageRoot()) return;
        bindReferencedMemoLinkDelegatesOnce();
        bindMemoTypeReferencedOnce();
        toggleReferencedCard();
        var polls = 0;
        var timer = setInterval(function () {
            toggleReferencedCard();
            if (++polls >= 24) clearInterval(timer);
        }, 250);
    }

    window.apmToggleOtherMemoReferencedCard = toggleReferencedCard;
    window.apmInitReferencedMemosUi = initReferencedMemosUi;

    document.addEventListener('DOMContentLoaded', bootOtherMemoReferenced);
    document.addEventListener('livewire:navigated', bootOtherMemoReferenced);
})();
