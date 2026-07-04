/**
 * Memo list (details) report — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'memo-list-report-app';

    function readInitialFilters(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);

        return {
            division: pick('division', ''),
            year: pick('year', String(cfg.defaults?.year ?? cfg.currentYear ?? '')),
            quarter: pick('quarter', cfg.defaults?.quarter ?? cfg.currentQuarter ?? ''),
            memo_type: pick('memo_type', ''),
            status: pick('status', ''),
        };
    }

    function bootMemoListReport(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch } = Vue;
        const { createVuetify } = Vuetify;

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48', secondary: '#64748b', success: '#119a48',
                            error: '#dc3545', info: '#0ea5e9', warning: '#f59e0b',
                            surface: '#ffffff', background: '#f8fafc',
                        },
                    },
                },
            },
            defaults: {
                VCard: { rounded: 'lg', elevation: 2 },
                VBtn: { rounded: 'lg' },
                VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const filters = ref(readInitialFilters(cfg));
                const page = ref(1);
                const sortBy = ref([{ key: 'year_quarter', order: 'desc' }]);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });

                const divisionItems = computed(() => {
                    const list = [{ title: 'All divisions', value: '' }];
                    (cfg.divisions || []).forEach((d) => list.push({ title: d.division_name, value: String(d.id) }));
                    return list;
                });

                const yearItems = computed(() => {
                    const cy = String(cfg.currentYear ?? '');
                    const list = [{ title: cy + ' (current)', value: cy }, { title: 'All years', value: 'all' }];
                    (cfg.years || []).forEach((y) => {
                        const ys = String(y);
                        if (ys !== cy) list.push({ title: ys, value: ys });
                    });
                    return list;
                });

                const quarterItems = computed(() => {
                    const list = [{ title: 'All quarters', value: '' }];
                    (cfg.quarters || ['Q1', 'Q2', 'Q3', 'Q4']).forEach((q) => list.push({ title: q, value: q }));
                    return list;
                });

                const memoTypeItems = computed(() => {
                    const list = [{ title: 'All types', value: '' }];
                    (cfg.memoTypeOptions || []).forEach((m) => list.push({ title: m.label, value: m.code }));
                    return list;
                });

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Document #', key: 'document_number', sortable: true, width: 120 },
                    { title: 'Title', key: 'title', sortable: true, minWidth: 200 },
                    { title: 'Division', key: 'division_name', sortable: true, minWidth: 140 },
                    { title: 'Type', key: 'type_label', sortable: true, width: 130 },
                    { title: 'Year / quarter', key: 'year_quarter', sortable: true, width: 120 },
                    { title: 'Status', key: 'overall_status', sortable: true, align: 'center', width: 130 },
                    { title: 'Date range', key: 'date_range', sortable: true, minWidth: 160 },
                    { title: 'Responsible', key: 'responsible_person_name', sortable: true, minWidth: 140 },
                ];

                const showingRange = computed(() => {
                    if (!pagination.value.total) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                const filterLabel = computed(() => {
                    const y = filters.value.year === 'all' ? 'All years' : (filters.value.year || 'All years');
                    const q = filters.value.quarter || 'All quarters';
                    return `${y} · ${q}`;
                });

                const exportUrl = computed(() => {
                    const qs = buildQueryParams(false).toString();
                    return qs ? `${cfg.routes.exportExcel}?${qs}` : cfg.routes.exportExcel;
                });

                function sortApiKey(key) {
                    if (key === 'division_name') return 'division_id';
                    if (key === 'type_label') return 'document_type';
                    if (key === 'date_range') return 'date_from';
                    return key;
                }

                function buildQueryParams(includePage = true) {
                    const params = new URLSearchParams();
                    if (includePage) params.set('page', String(page.value));
                    if (filters.value.division) params.set('division', filters.value.division);
                    if (filters.value.year) params.set('year', filters.value.year);
                    if (filters.value.quarter) params.set('quarter', filters.value.quarter);
                    if (filters.value.memo_type) params.set('memo_type', filters.value.memo_type);
                    if (filters.value.status) params.set('status', filters.value.status);
                    if (sortBy.value.length) {
                        params.set('sort_column', sortApiKey(sortBy.value[0].key));
                        params.set('sort_dir', sortBy.value[0].order);
                    }
                    return params;
                }

                async function loadReport() {
                    loading.value = true;
                    try {
                        const qs = buildQueryParams(true).toString();
                        const res = await fetch(`${cfg.routes.data}?${qs}`, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error('Could not load memo list.');
                        const pg = json.pagination || {};
                        items.value = (json.data || []).map((row, i) => ({
                            ...row,
                            row_num: (pg.from || 1) + i,
                        }));
                        pagination.value = {
                            total: pg.total || 0,
                            from: pg.from || 0,
                            to: pg.to || 0,
                            last_page: pg.last_page || 1,
                        };
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load memo list.');
                    } finally {
                        loading.value = false;
                    }
                }

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function resetFilters() {
                    filters.value = {
                        division: '',
                        year: String(cfg.defaults?.year ?? cfg.currentYear ?? ''),
                        quarter: cfg.defaults?.quarter ?? cfg.currentQuarter ?? '',
                        memo_type: '',
                        status: '',
                    };
                    page.value = 1;
                    sortBy.value = [{ key: 'year_quarter', order: 'desc' }];
                    loadReport();
                }

                function printPage() {
                    window.print();
                }

                watch(page, () => loadReport());
                watch(sortBy, () => { page.value = 1; loadReport(); }, { deep: true });
                loadReport();

                return {
                    cfg, filters, page, sortBy, items, pagination, headers, loading, snackbar,
                    divisionItems, yearItems, quarterItems, memoTypeItems, statusOptions: cfg.statusOptions || [],
                    showingRange, filterLabel, exportUrl, loadReport, resetFilters, printPage,
                };
            },
            template: `
<v-app class="mlr-vuetify-app">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap gap-2 mb-4 no-print">
      <v-btn :href="cfg.routes.reportsIndex" variant="outlined" prepend-icon="mdi-arrow-left" size="small">Reports</v-btn>
    </div>

    <v-card class="mb-4 no-print">
      <v-card-title class="text-subtitle-1 font-weight-medium py-3">
        <v-icon icon="mdi-filter-outline" class="me-2" color="primary"></v-icon>Filters
        <v-chip size="small" variant="tonal" color="primary" class="ms-2">{{ filterLabel }}</v-chip>
      </v-card-title>
      <v-card-text>
        <v-row dense align="end">
          <v-col cols="12" sm="6" md="2"><v-select v-model="filters.division" :items="divisionItems" label="Division" clearable></v-select></v-col>
          <v-col cols="6" md="2"><v-select v-model="filters.year" :items="yearItems" label="Year"></v-select></v-col>
          <v-col cols="6" md="2"><v-select v-model="filters.quarter" :items="quarterItems" label="Quarter"></v-select></v-col>
          <v-col cols="12" sm="6" md="2"><v-select v-model="filters.memo_type" :items="memoTypeItems" label="Memo type" clearable></v-select></v-col>
          <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" item-title="label" item-value="value" label="Status"></v-select></v-col>
          <v-col cols="12" md="2" class="d-flex gap-2">
            <v-btn color="primary" block @click="page = 1; loadReport()">Apply</v-btn>
            <v-btn variant="outlined" block @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card id="mlr-print-area">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2 py-3 no-print">
        <span class="text-subtitle-1 font-weight-medium"><v-icon icon="mdi-format-list-bulleted" color="primary" class="me-2"></v-icon>Memo list</span>
        <div class="d-flex gap-2">
          <v-btn :href="exportUrl" color="success" variant="tonal" size="small" prepend-icon="mdi-file-excel">Excel</v-btn>
          <v-btn variant="outlined" size="small" prepend-icon="mdi-printer" @click="printPage">Print</v-btn>
        </div>
      </v-card-title>
      <v-card-text class="px-4 pt-0">
        <v-data-table
          v-model:sort-by="sortBy"
          class="mlr-table elevation-0 border rounded-lg"
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="-1"
          hide-default-footer
          must-sort
        >
          <template #item.row_num="{ item }"><v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip></template>
          <template #item.document_number="{ item }">
            <a v-if="item.show_url && item.show_url !== '#'" :href="item.show_url" class="text-primary text-decoration-none"><code>{{ item.document_number }}</code></a>
            <code v-else>{{ item.document_number }}</code>
          </template>
          <template #item.title="{ item }">
            <a v-if="item.show_url && item.show_url !== '#'" :href="item.show_url" class="text-primary text-decoration-none text-wrap">{{ item.title }}</a>
            <span v-else class="text-wrap">{{ item.title }}</span>
          </template>
          <template #item.type_label="{ item }"><v-chip size="x-small" variant="tonal" color="info">{{ item.type_label }}</v-chip></template>
          <template #item.overall_status="{ item }">
            <v-chip size="small" variant="tonal" :color="item.status_color" label>{{ item.status_label }}</v-chip>
          </template>
          <template #no-data><v-alert type="info" variant="tonal" class="ma-4">No memos for the selected filters.</v-alert></template>
          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
              <span class="text-body-2 text-medium-emphasis">Showing {{ showingRange }} of {{ pagination.total }} memos</span>
              <v-pagination
                v-model="page"
                :length="Math.max(1, pagination.last_page)"
                :total-visible="7"
                density="comfortable"
                rounded="circle"
                active-color="primary"
              ></v-pagination>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootMemoListReport);
    }
})();
