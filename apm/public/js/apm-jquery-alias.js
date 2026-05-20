/**
 * Restore jQuery $ alias after Livewire navigate/prefetch (some bundles drop $).
 * Also provides apmOnJQuery(fn) — runs on DOMContentLoaded and livewire:navigated.
 */
(function () {
    'use strict';

    function ensureJQueryAlias() {
        if (typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
    }

    window.apmOnJQuery = function (fn) {
        if (typeof fn !== 'function') {
            return;
        }

        function run() {
            ensureJQueryAlias();
            if (typeof window.jQuery === 'undefined') {
                return;
            }
            fn(window.jQuery);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }
        document.addEventListener('livewire:navigated', run);
    };

    ensureJQueryAlias();
    document.addEventListener('livewire:navigated', ensureJQueryAlias);
})();
