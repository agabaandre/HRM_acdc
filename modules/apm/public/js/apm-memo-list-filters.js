/**
 * Reusable Vuetify memo list filters (activities pattern).
 * Syncs to hidden DOM fields + dispatches apm-memo-filters:apply for existing tab loaders.
 */
(function () {
    'use strict';

    const REGISTRY = {};

    const STATUS_OPTIONS = [
        { title: 'All', value: '' },
        { title: 'Draft', value: 'draft' },
        { title: 'Pending', value: 'pending' },
        { title: 'Approved', value: 'approved' },
        { title: 'Rejected', value: 'rejected' },
        { title: 'Returned', value: 'returned' },
        { title: 'Archived', value: 'archived' },
    ];

    function currentYear() {
        return String(new Date().getFullYear());
    }

    function currentQuarter() {
        return 'Q' + Math.ceil((new Date().getMonth() + 1) / 3);
    }

    function readConfig(mountEl) {
        const script = mountEl.querySelector('.apm-memo-filters-config');
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
        if (cfg.tabInputId && params.has('tab')) {
            values.tab = params.get('tab') || '';
        }
        return values;
    }

    function defaultsFor(cfg) {
        const d = {};
        (cfg.fields || []).forEach((field) => {
            if (field.key === 'year' && field.defaultYear) {
                d.year = String(field.defaultYear);
            } else if (field.key === 'quarter' && field.defaultQuarter) {
                d.quarter = field.defaultQuarter;
            } else {
                d[field.key] = '';
            }
        });
        return d;
    }

    function bootMemoListFilters(mountEl, cfg) {
        if (!mountEl || !cfg) return;

        const filterId = cfg.filterId || 'memoFilters';
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
                VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: true },
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
                    document.dispatchEvent(new CustomEvent('apm-memo-filters:apply', {
                        detail: { filterId, values: { ...model.value } },
                    }));
                }

                function applyFilters() {
                    if (!filterReady.value) {
                        return;
                    }
                    emitApply();
                }

                function resetFilters() {
                    const target = cfg.resetUrl || window.location.pathname;
                    if (typeof Livewire !== 'undefined' && Livewire.navigate) {
                        Livewire.navigate(target);
                    } else {
                        window.location.href = target;
                    }
                }

                let docTimer = null;
                watch(() => model.value.document_number, () => {
                    clearTimeout(docTimer);
                    docTimer = setTimeout(applyFilters, 1000);
                });

                watch(() => model.value.search, () => {
                    clearTimeout(docTimer);
                    docTimer = setTimeout(applyFilters, 500);
                });

                const autoApplyKeys = ['year', 'quarter', 'staff_id', 'division_id', 'status', 'fund_type_id', 'request_type_id', 'memo_type', 'service_type', 'overall_status'];
                autoApplyKeys.forEach((key) => {
                    watch(() => model.value[key], () => {
                        if (cfg.fields.some((f) => f.key === key)) {
                            applyFilters();
                        }
                    });
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
                    STATUS_OPTIONS,
                    applyFilters,
                    resetFilters,
                };
            },
            template: `
<v-app class="apm-memo-filters-app" theme="apmLight">
  <v-card class="apm-memo-filters-card" elevation="0">
    <v-card-text class="pa-4">
      <v-row v-if="cfg.showSearch" dense>
        <v-col cols="12">
          <v-text-field
            v-model="model.search"
            :label="cfg.searchLabel || 'Search title'"
            :placeholder="cfg.searchPlaceholder || 'Search…'"
            prepend-inner-icon="mdi-magnify"
            clearable
            @keyup.enter="applyFilters"
          ></v-text-field>
        </v-col>
      </v-row>
      <v-row dense class="mt-1">
        <v-col v-if="cfg.showDocumentNumber" cols="12" sm="12" md="8" lg="4">
          <v-text-field
            v-model="model.document_number"
            label="Document #"
            placeholder="Enter document number"
            prepend-inner-icon="mdi-file-document-outline"
            clearable
            @keyup.enter="applyFilters"
          ></v-text-field>
        </v-col>
        <v-col v-if="cfg.showStaff" cols="12" sm="12" md="8" lg="4">
          <v-autocomplete
            v-model="model.staff_id"
            :items="cfg.options.staff || []"
            item-title="label"
            item-value="value"
            :label="cfg.staffLabel || 'Staff'"
            prepend-inner-icon="mdi-account"
            clearable
          ></v-autocomplete>
        </v-col>
        <v-col v-if="cfg.showYear" cols="12" sm="8" md="6" lg="2">
          <v-select
            v-model="model.year"
            :items="cfg.options.years || []"
            item-title="label"
            item-value="value"
            label="Year"
            prepend-inner-icon="mdi-calendar"
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showQuarter" cols="12" sm="8" md="6" lg="2">
          <v-select
            v-model="model.quarter"
            :items="cfg.options.quarters || []"
            item-title="label"
            item-value="value"
            label="Quarter"
            prepend-inner-icon="mdi-clock-outline"
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showDivision" cols="12" sm="12" md="8" lg="4">
          <v-autocomplete
            v-model="model.division_id"
            :items="cfg.options.divisions || []"
            item-title="label"
            item-value="value"
            label="Division"
            prepend-inner-icon="mdi-office-building"
            clearable
          ></v-autocomplete>
        </v-col>
        <v-col v-if="cfg.showStatus" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.status"
            :items="STATUS_OPTIONS"
            :label="cfg.statusLabel || 'Status'"
            prepend-inner-icon="mdi-information-outline"
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showOverallStatus" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.overall_status"
            :items="STATUS_OPTIONS"
            label="Status"
            prepend-inner-icon="mdi-information-outline"
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showFundType" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.fund_type_id"
            :items="cfg.options.fundTypes || []"
            item-title="label"
            item-value="value"
            label="Fund type"
            prepend-inner-icon="mdi-wallet"
            clearable
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showRequestType" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.request_type_id"
            :items="cfg.options.requestTypes || []"
            item-title="label"
            item-value="value"
            label="Request type"
            prepend-inner-icon="mdi-tag"
            clearable
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showMemoType" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.memo_type"
            :items="cfg.options.memoTypes || []"
            item-title="label"
            item-value="value"
            label="Memo type"
            prepend-inner-icon="mdi-file-tree"
            clearable
          ></v-select>
        </v-col>
        <v-col v-if="cfg.showServiceType" cols="12" sm="6" md="4" lg="2">
          <v-select
            v-model="model.service_type"
            :items="cfg.options.serviceTypes || []"
            item-title="label"
            item-value="value"
            label="Service type"
            prepend-inner-icon="mdi-briefcase"
            clearable
          ></v-select>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-end gap-2">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">Filter</v-btn>
        </v-col>
        <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-end gap-2">
          <v-btn variant="outlined" color="secondary" prepend-icon="mdi-restore" block @click="resetFilters">Reset</v-btn>
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
        document.querySelectorAll('.apm-memo-filters-mount').forEach((mountEl) => {
            const cfg = readConfig(mountEl);
            if (cfg) bootMemoListFilters(mountEl, cfg);
        });
    }

    window.ApmMemoListFilters = {
        registry: REGISTRY,
        getValues(filterId) {
            return REGISTRY[filterId] ? REGISTRY[filterId].getValues() : null;
        },
        boot: bootMemoListFilters,
        bootAll,
        destroy(filterId) {
            if (REGISTRY[filterId]) {
                REGISTRY[filterId].unmount();
                delete REGISTRY[filterId];
            }
        },
        currentYear,
        currentQuarter,
    };

    document.addEventListener('DOMContentLoaded', bootAll);
    document.addEventListener('livewire:navigated', bootAll);
    document.addEventListener('alpine:navigated', bootAll);
    document.addEventListener('livewire:navigating', () => {
        Object.keys(REGISTRY).forEach((id) => window.ApmMemoListFilters.destroy(id));
    });
})();
