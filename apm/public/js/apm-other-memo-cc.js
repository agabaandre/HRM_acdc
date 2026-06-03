/**
 * Other memo create — CC card visibility (standalone; does not depend on memo type object resolution).
 */
(function () {
    'use strict';

    function createPageRoot() {
        return document.querySelector('[data-apm-livewire-page="other-memos-create"]');
    }

    function loadCcMap() {
        var root = createPageRoot();
        if (!root) return {};
        var out = {};
        try {
            var raw = JSON.parse(root.getAttribute('data-memo-type-cc-by-slug') || '{}');
            Object.keys(raw).forEach(function (key) {
                var k = String(key).toLowerCase().trim();
                if (!k) return;
                out[k] = raw[key] === true || raw[key] === 1 || raw[key] === '1';
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

    function isCcEnabledForSlug(slug, map) {
        slug = String(slug || '').toLowerCase().trim();
        if (!slug) return false;
        return map[slug] === true;
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

    function initCcStaffSelect2() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
        var $sel = jQuery('#cc_staff_ids');
        if (!$sel.length || !document.body.contains($sel[0])) return;
        var card = document.getElementById('memo-cc-card');
        if (!card || card.classList.contains('d-none')) return;
        if ($sel.hasClass('select2-hidden-accessible')) {
            try { $sel.select2('destroy'); } catch (e) {}
        }
        $sel.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: $sel.data('placeholder') || 'Search and select staff…',
            allowClear: true
        });
    }

    function syncCcOptionsVisibility() {
        var includeCb = document.getElementById('cc_include');
        var opts = document.getElementById('memo-cc-options-wrap');
        if (!includeCb || !opts) return;
        opts.classList.toggle('d-none', !includeCb.checked);
        if (includeCb.checked) {
            var specific = document.getElementById('cc_mode_specific');
            var allFields = document.getElementById('memo-cc-all-fields');
            var specificWrap = document.getElementById('memo-cc-specific-wrap');
            var specificOn = specific && specific.checked;
            if (allFields) allFields.classList.toggle('d-none', specificOn);
            if (specificWrap) specificWrap.classList.toggle('d-none', !specificOn);
            if (specificOn) runWhenSelect2Ready(initCcStaffSelect2);
        }
    }

    function toggleCcCard() {
        var card = document.getElementById('memo-cc-card');
        if (!card) return;
        var map = loadCcMap();
        var slug = selectedMemoTypeSlug();
        var show = isCcEnabledForSlug(slug, map);
        card.classList.toggle('d-none', !show);
        if (show) {
            syncCcOptionsVisibility();
            runWhenSelect2Ready(initCcStaffSelect2);
        }
    }

    function bindCcFormDelegatesOnce() {
        if (window._apmOtherMemoCcFormBound) return;
        window._apmOtherMemoCcFormBound = true;
        document.addEventListener('change', function (e) {
            if (!createPageRoot()) return;
            if (!e.target) return;
            var id = e.target.id;
            if (id === 'cc_include' || id === 'cc_mode_all' || id === 'cc_mode_specific') {
                syncCcOptionsVisibility();
            }
        });
    }

    function bindMemoTypeCcOnce() {
        if (window._apmOtherMemoCcTypeBound) return;
        window._apmOtherMemoCcTypeBound = true;
        document.addEventListener('change', function (e) {
            if (!createPageRoot()) return;
            if (e.target && e.target.id === 'memo_type_slug') {
                toggleCcCard();
            }
        }, true);
        if (typeof jQuery !== 'undefined') {
            jQuery(document).on('select2:select.apmOtherMemoCc', '#memo_type_slug', function () {
                setTimeout(toggleCcCard, 0);
            });
            jQuery(document).on('change.apmOtherMemoCc', '#memo_type_slug', function () {
                setTimeout(toggleCcCard, 0);
            });
        }
    }

    function bootOtherMemoCc() {
        if (!createPageRoot()) return;
        bindCcFormDelegatesOnce();
        bindMemoTypeCcOnce();
        toggleCcCard();
        var polls = 0;
        var timer = setInterval(function () {
            toggleCcCard();
            if (++polls >= 24) clearInterval(timer);
        }, 250);
    }

    window.apmToggleOtherMemoCcCard = toggleCcCard;

    document.addEventListener('DOMContentLoaded', bootOtherMemoCc);
    document.addEventListener('livewire:navigated', bootOtherMemoCc);
})();
