/**
 * Shared helpers for APM Vue + Vuetify pages under Livewire wire:navigate.
 * Inline <script> tags in morphed HTML do not re-run; page config lives in a JSON block.
 */
(function () {
    'use strict';

    const instances = Object.create(null);

    function readConfig(mountEl) {
        if (!mountEl) {
            return null;
        }
        const script = mountEl.querySelector('script.apm-page-config[type="application/json"]');
        if (script && script.textContent) {
            try {
                const parsed = JSON.parse(script.textContent);
                if (parsed && typeof parsed === 'object') {
                    return parsed;
                }
            } catch (e) {
                // Fall through to attribute-based config.
            }
        }

        const raw = mountEl.getAttribute('data-apm-page-config');
        if (!raw) {
            return null;
        }
        try {
            const parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (e) {
            return null;
        }
    }

    function destroyInstance(mountId) {
        const app = instances[mountId];
        if (!app) {
            return;
        }
        try {
            app.unmount();
        } catch (e) {
            // Body may already have been replaced by Livewire navigate.
        }
        delete instances[mountId];
    }

    function destroyAllInstances() {
        Object.keys(instances).forEach(destroyInstance);
    }

    function scheduleBoot(mountId, bootFn, attempt) {
        const tries = attempt || 0;
        const mountEl = document.getElementById(mountId);

        if (!mountEl) {
            destroyInstance(mountId);
            bootFn(null, null);
            return;
        }

        if (typeof Vue === 'undefined' || typeof Vuetify === 'undefined') {
            if (tries < 60) {
                setTimeout(function () {
                    scheduleBoot(mountId, bootFn, tries + 1);
                }, 50);
            }
            return;
        }

        const cfg = readConfig(mountEl);
        if (!cfg) {
            if (tries < 60) {
                setTimeout(function () {
                    scheduleBoot(mountId, bootFn, tries + 1);
                }, 50);
            }
            return;
        }

        bootFn(mountEl, cfg);
    }

    function bind(mountId, bootFn) {
        function run() {
            requestAnimationFrame(function () {
                scheduleBoot(mountId, bootFn, 0);
            });
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', run);
        } else {
            run();
        }

        document.addEventListener('livewire:navigated', run);
        document.addEventListener('alpine:navigated', run);
    }

    document.addEventListener('livewire:navigating', destroyAllInstances);
    document.addEventListener('alpine:navigating', destroyAllInstances);

    window.ApmVuetifyPage = {
        readConfig: readConfig,
        scheduleBoot: scheduleBoot,
        bind: bind,
        destroy: destroyInstance,
        destroyAll: destroyAllInstances,
        register: function (mountId, app) {
            if (app) {
                instances[mountId] = app;
            }
        },
    };
})();
