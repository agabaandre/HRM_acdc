/**
 * APM shared filter state (matrices pattern).
 * - Set filter values from URL before initializing Select2 so reload shows correct state.
 * - Do not use class "select2" on filter form selects so the global footer does not init them first.
 * - Call APMFilters.init(containerSelector, options) on DOMContentLoaded / livewire:navigated.
 */
(function () {
    'use strict';

    function currentYear() {
        return String(new Date().getFullYear());
    }

    function currentQuarter() {
        return 'Q' + Math.ceil((new Date().getMonth() + 1) / 3);
    }

    /**
     * Set form field values from URL params. Uses native DOM so values are set before any Select2.
     * @param {string} containerSelector - CSS selector for the filter container (e.g. '#activityFilters').
     * @param {Object} options - { fields: [{ param, id, default?: string|function }], tabParam?: string, tabDefault?: string }
     */
    function setFilterStateFromUrl(containerSelector, options) {
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return;
        var params = new URLSearchParams(window.location.search);
        var fields = options.fields || [];
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            var el = document.getElementById(f.id);
            if (!el) continue;
            var paramVal = params.get(f.param);
            var val;
            if (paramVal !== null && paramVal !== undefined) {
                val = paramVal;
            } else if (typeof f.default === 'function') {
                val = f.default();
            } else if (f.default !== undefined) {
                val = f.default;
            } else {
                val = '';
            }
            el.value = val;
        }
        if (options.tabParam !== undefined) {
            var tabEl = document.getElementById(options.tabParam);
            if (tabEl) {
                var tabVal = params.get('tab');
                tabEl.value = (tabVal !== null && tabVal !== undefined) ? tabVal : (options.tabDefault || '');
            }
        }
    }

    /**
     * Initialize Select2 on filter selects inside container. Call after setFilterStateFromUrl.
     * @param {string} containerSelector
     * @param {Object} options - { selectSelector: string, theme?: string, width?: string, dataAttr?: string }
     */
    function initFilterSelect2(containerSelector, options) {
        var $ = window.jQuery || window.$;
        if (!$ || !$.fn.select2) return;
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return;
        var selectSelector = options.selectSelector || '.apm-filter-select';
        var theme = options.theme || 'bootstrap-5';
        var width = options.width || '100%';
        var dataAttr = options.dataAttr || 'data-apm-select2-inited';
        if (container.getAttribute(dataAttr)) return;
        var selects = container.querySelectorAll(selectSelector);
        selects.forEach(function (sel) {
            var $sel = $(sel);
            if ($sel.data('select2')) $sel.select2('destroy');
        });
        $(selects).select2({ theme: theme, width: width });
        // Matrices-style: ensure original select is hidden (avoid duplicate on reload)
        selects.forEach(function (sel) {
            sel.style.position = 'absolute';
            sel.style.width = '1px';
            sel.style.height = '1px';
            sel.style.margin = '-1px';
            sel.style.padding = '0';
            sel.style.overflow = 'hidden';
            sel.style.clip = 'rect(0,0,0,0)';
            sel.style.opacity = '0';
            sel.style.pointerEvents = 'none';
        });
        container.setAttribute(dataAttr, '1');
    }

    /**
     * Full init: set state from URL then init Select2. Matrices pattern for reload persistence.
     * @param {string} containerSelector - e.g. '#activityFilters'
     * @param {Object} options - { fields: [...], selectSelector, tabParam?, tabDefault?, theme?, width? }
     */
    function init(containerSelector, options) {
        options = options || {};
        setFilterStateFromUrl(containerSelector, options);
        initFilterSelect2(containerSelector, options);
    }

    /**
     * Clear the "already inited" flag so Select2 can be re-inited (e.g. after livewire:navigated).
     */
    function clearInited(containerSelector, dataAttr) {
        dataAttr = dataAttr || 'data-apm-select2-inited';
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (container) container.removeAttribute(dataAttr);
    }

    window.APMFilters = {
        init: init,
        setFilterStateFromUrl: setFilterStateFromUrl,
        initFilterSelect2: initFilterSelect2,
        clearInited: clearInited,
        currentYear: currentYear,
        currentQuarter: currentQuarter
    };
})();
