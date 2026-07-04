/**
 * Vuetify matrix list filters — same pattern as memo filters, matrix-specific fields.
 */
(function () {
    'use strict';

    const REGISTRY = {};

    function readConfig(mountEl) {
        const script = mountEl.querySelector('.apm-matrix-filters-config');
        if (!script) return null;
        try {
            return JSON.parse(script.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function valuesFromUrl(cfg) {
        const params = new URLSearchParams(window.location.search);
        const values = { ...(cfg.values || {}) };
        (cfg.fields || []).forEach((field) => {
            const param = field.param || field.key;
            if (params.has(param)) {
                values[field.key] = params.get(param) ?? '';
            }
        });
        return values;
    }

    function defaultsFor(cfg) {
        const d = { year: '', quarter: '', division: '', focal_person: '', status: 'active' };
        (cfg.fields || []).forEach((field) => {
            if (Object.prototype.hasOwnProperty.call(cfg.values || {}, field.key)) {
                d[field.key] = cfg.values[field.key] ?? d[field.key] ?? '';
            }
        });
        return d;
    }

    function bootMatrixListFilters(mountEl, cfg) {
        if (!mountEl || !cfg) return;

        const filterId = cfg.filterId || 'matrixFilters';
        if (REGISTRY[filterId]) {
            try {
                REGISTRY[filterId].unmount();
            } catch (e) { /* ignore */ }
        }

        mountEl.innerHTML = '';

        const { createApp, ref, watch, onMounted, nextTick } = Vue;
        const { createVuetify } = Vuetify;

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48',
                            secondary: '#64748b',
                            surface: '#ffffff',
                            background: '#f8fafc',
                        },
                    },
                },
            },
            defaults: {
                VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                VAutocomplete: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const model = ref({ ...defaultsFor(cfg), ...valuesFromUrl(cfg) });
                const filterReady = ref(false);

                function syncHiddenFields() {
                    const syncRoot = document.getElementById(`${filterId}-sync`);
                    if (!syncRoot) return;
                    (cfg.fields || []).forEach((field) => {
                        const el = syncRoot.querySelector(`[data-sync="${field.key}"]`);
                        if (el) {
                            el.value = model.value[field.key] ?? '';
                        }
                        if (field.domId) {
                            const legacy = document.getElementById(field.domId);
                            if (legacy) legacy.value = model.value[field.key] ?? '';
                        }
                    });
                }

                function emitApply() {
                    syncHiddenFields();
                    document.dispatchEvent(new CustomEvent('apm-matrix-filters:apply', {
                        detail: { filterId, values: { ...model.value } },
                    }));
                }

                function applyFilters() {
                    if (!filterReady.value) return;
                    emitApply();
                }

                ['year', 'quarter', 'division', 'focal_person', 'status'].forEach((key) => {
                    watch(() => model.value[key], () => applyFilters());
                });

                onMounted(async () => {
                    syncHiddenFields();
                    await nextTick();
                    filterReady.value = true;
                });

                REGISTRY[filterId] = {
                    getValues: () => ({ ...model.value }),
                    apply: applyFilters,
                    unmount: () => app.unmount(),
                };

                return {
                    cfg,
                    model,
                    applyFilters,
                };
            },
            template: `
<v-app class="apm-memo-filters-app" theme="apmLight">
  <v-card class="apm-memo-filters-card" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.year"
            :items="cfg.options.years || []"
            item-title="label"
            item-value="value"
            label="Year"
            prepend-inner-icon="mdi-calendar"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.quarter"
            :items="cfg.options.quarters || []"
            item-title="label"
            item-value="value"
            label="Quarter"
            prepend-inner-icon="mdi-clock-outline"
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="6" lg="3">
          <v-autocomplete
            v-model="model.division"
            :items="cfg.options.divisions || []"
            item-title="label"
            item-value="value"
            label="Division"
            prepend-inner-icon="mdi-office-building"
            clearable
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" sm="6" md="6" lg="3">
          <v-autocomplete
            v-model="model.focal_person"
            :items="cfg.options.focalPersons || []"
            item-title="label"
            item-value="value"
            label="Focal Person"
            prepend-inner-icon="mdi-account-tie"
            clearable
          ></v-autocomplete>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.status"
            :items="cfg.options.matrixStatuses || []"
            item-title="label"
            item-value="value"
            label="Matrix Status"
            prepend-inner-icon="mdi-filter-outline"
          ></v-select>
        </v-col>
      </v-row>
      <v-row class="mt-1">
        <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">Filter</v-btn>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</v-app>
            `,
        }).use(vuetify);

        app.mount(mountEl);
    }

    function bootAll() {
        document.querySelectorAll('.apm-matrix-filters-mount').forEach((mountEl) => {
            const cfg = readConfig(mountEl);
            if (cfg) bootMatrixListFilters(mountEl, cfg);
        });
    }

    window.ApmMatrixListFilters = {
        registry: REGISTRY,
        getValues(filterId) {
            return REGISTRY[filterId] ? REGISTRY[filterId].getValues() : null;
        },
        boot: bootMatrixListFilters,
        bootAll,
        destroy(filterId) {
            if (REGISTRY[filterId]) {
                REGISTRY[filterId].unmount();
                delete REGISTRY[filterId];
            }
        },
    };

    document.addEventListener('DOMContentLoaded', bootAll);
    document.addEventListener('livewire:navigated', bootAll);
    document.addEventListener('alpine:navigated', bootAll);
    document.addEventListener('livewire:navigating', () => {
        Object.keys(REGISTRY).forEach((id) => window.ApmMatrixListFilters.destroy(id));
    });
})();
