/**
 * Division memo counts report — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'division-counts-report-app';

    function bootDivisionCountsReport(mountEl, cfg) {
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
                const filters = ref({
                    division: '',
                    year: String(cfg.defaults?.year ?? cfg.currentYear ?? ''),
                    quarter: cfg.defaults?.quarter ?? cfg.currentQuarter ?? '',
                    memo_type: '',
                });
                const sortBy = ref([{ key: 'division_name', order: 'asc' }]);
                const items = ref([]);
                const summary = ref({
                    total_divisions: 0, total_approved: 0, total_pending: 0,
                    total_returned: 0, total_draft: 0, total_count: 0,
                });
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
                    { title: 'Division', key: 'division_name', sortable: true, minWidth: 180 },
                    { title: 'Approved', key: 'approved_count', sortable: true, align: 'center' },
                    { title: 'Pending', key: 'pending_count', sortable: true, align: 'center' },
                    { title: 'Returned', key: 'returned_count', sortable: true, align: 'center' },
                    { title: 'Draft', key: 'draft_count', sortable: true, align: 'center' },
                    { title: 'Total', key: 'total_count', sortable: true, align: 'center' },
                ];

                const summaryKpis = computed(() => [
                    { key: 'div', icon: 'mdi-office-building', accent: '#119a48', value: summary.value.total_divisions, label: 'Divisions' },
                    { key: 'app', icon: 'mdi-check-circle', accent: '#15803d', value: summary.value.total_approved, label: 'Approved' },
                    { key: 'pend', icon: 'mdi-clock-outline', accent: '#d97706', value: summary.value.total_pending, label: 'Pending' },
                    { key: 'tot', icon: 'mdi-sigma', accent: '#0284c7', value: summary.value.total_count, label: 'Total memos' },
                ]);

                const filterLabel = computed(() => {
                    const y = filters.value.year === 'all' ? 'All years' : (filters.value.year || 'All years');
                    const q = filters.value.quarter || 'All quarters';
                    return `${y} · ${q}`;
                });

                const exportUrl = computed(() => {
                    const qs = buildQueryParams().toString();
                    return qs ? `${cfg.routes.exportExcel}?${qs}` : cfg.routes.exportExcel;
                });

                function sortApiKey(key) {
                    return key === 'division_name' ? 'division' : key;
                }

                function buildQueryParams() {
                    const params = new URLSearchParams();
                    if (filters.value.division) params.set('division', filters.value.division);
                    if (filters.value.year) params.set('year', filters.value.year);
                    if (filters.value.quarter) params.set('quarter', filters.value.quarter);
                    if (filters.value.memo_type) params.set('memo_type', filters.value.memo_type);
                    if (sortBy.value.length) {
                        params.set('sort_column', sortApiKey(sortBy.value[0].key));
                        params.set('sort_dir', sortBy.value[0].order);
                    }
                    return params;
                }

                async function loadReport() {
                    loading.value = true;
                    try {
                        const qs = buildQueryParams().toString();
                        const url = qs ? `${cfg.routes.data}?${qs}` : cfg.routes.data;
                        const res = await fetch(url, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error('Could not load division counts.');
                        items.value = (json.data || []).map((row, i) => ({ ...row, row_num: i + 1 }));
                        summary.value = json.summary || summary.value;
                    } catch (e) {
                        items.value = [];
                        notify(e.message || 'Could not load division counts.');
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
                    };
                    sortBy.value = [{ key: 'division_name', order: 'asc' }];
                    loadReport();
                }

                function printPage() {
                    window.print();
                }

                watch(sortBy, () => loadReport(), { deep: true });
                loadReport();

                return {
                    cfg, filters, sortBy, items, summaryKpis, headers, loading, snackbar,
                    divisionItems, yearItems, quarterItems, memoTypeItems, filterLabel, exportUrl,
                    loadReport, resetFilters, printPage,
                };
            },
            template: `
<v-app class="dcr-vuetify-app">
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
          <v-col cols="12" sm="6" md="3"><v-select v-model="filters.division" :items="divisionItems" label="Division" clearable></v-select></v-col>
          <v-col cols="6" md="2"><v-select v-model="filters.year" :items="yearItems" label="Year"></v-select></v-col>
          <v-col cols="6" md="2"><v-select v-model="filters.quarter" :items="quarterItems" label="Quarter"></v-select></v-col>
          <v-col cols="12" sm="6" md="3"><v-select v-model="filters.memo_type" :items="memoTypeItems" label="Memo type" clearable></v-select></v-col>
          <v-col cols="12" md="2" class="d-flex gap-2">
            <v-btn color="primary" block @click="loadReport">Apply</v-btn>
            <v-btn variant="outlined" block @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card id="dcr-print-area">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2 py-3 no-print">
        <div>
          <span class="text-subtitle-1 font-weight-medium"><v-icon icon="mdi-chart-bar" color="primary" class="me-2"></v-icon>Division memo counts</span>
          <div class="text-caption text-medium-emphasis">Click division or total to open memo list</div>
        </div>
        <div class="d-flex gap-2">
          <v-btn :href="exportUrl" color="success" variant="tonal" size="small" prepend-icon="mdi-file-excel">Excel</v-btn>
          <v-btn variant="outlined" size="small" prepend-icon="mdi-printer" @click="printPage">Print</v-btn>
        </div>
      </v-card-title>
      <v-card-text class="px-4 pt-0">
        <v-row dense class="mb-4 no-print">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="dcr-kpi-card" elevation="0" :style="{ '--dcr-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div class="dcr-kpi-icon-wrap me-3" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div><div class="dcr-kpi-value">{{ kpi.value }}</div><div class="dcr-kpi-label">{{ kpi.label }}</div></div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-data-table
          v-model:sort-by="sortBy"
          class="dcr-table elevation-0 border rounded-lg"
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="-1"
          hide-default-footer
          must-sort
        >
          <template #item.row_num="{ item }"><v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip></template>
          <template #item.division_name="{ item }">
            <a :href="item.memo_list_url" class="text-primary font-weight-medium text-decoration-none">{{ item.division_name }}</a>
          </template>
          <template #item.approved_count="{ item }"><v-chip size="small" variant="tonal" color="success">{{ item.approved_count }}</v-chip></template>
          <template #item.pending_count="{ item }"><v-chip size="small" variant="tonal" color="warning">{{ item.pending_count }}</v-chip></template>
          <template #item.returned_count="{ item }"><v-chip size="small" variant="tonal" color="error">{{ item.returned_count }}</v-chip></template>
          <template #item.draft_count="{ item }"><v-chip size="small" variant="tonal" color="secondary">{{ item.draft_count }}</v-chip></template>
          <template #item.total_count="{ item }">
            <a :href="item.memo_list_url" class="text-decoration-none"><v-chip size="small" variant="flat" color="primary">{{ item.total_count }}</v-chip></a>
          </template>
          <template #no-data><v-alert type="info" variant="tonal" class="ma-4">No data for the selected filters.</v-alert></template>
          <template #bottom>
            <div class="px-4 py-3 text-body-2 text-medium-emphasis">Showing {{ items.length }} division{{ items.length === 1 ? '' : 's' }}</div>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDivisionCountsReport);
    }
})();
