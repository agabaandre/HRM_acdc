/**
 * Staff quarterly travel report — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'staff-quarterly-travel-app';

    function bootStaffQuarterlyTravel(mountEl, cfg) {
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
                            primary: '#119a48',
                            secondary: '#64748b',
                            success: '#119a48',
                            error: '#dc3545',
                            info: '#0ea5e9',
                            warning: '#f59e0b',
                            surface: '#ffffff',
                            background: '#f8fafc',
                        },
                    },
                },
            },
            defaults: {
                VCard: { rounded: 'lg', elevation: 2 },
                VBtn: { rounded: 'lg' },
                VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                VAutocomplete: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const filters = ref({
                    division_id: '',
                    staff_id: '',
                    year: String(cfg.defaults?.year ?? cfg.currentYear ?? ''),
                    quarter: cfg.defaults?.quarter ?? cfg.currentQuarter ?? '',
                });

                const sortBy = ref([{ key: 'division_name', order: 'asc' }]);
                const page = ref(1);
                const itemsPerPage = ref(cfg.perPage || 50);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const summary = ref({
                    total_rows: 0,
                    staff_count: 0,
                    total_travel_days: 0,
                    total_activities: 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });

                const breakdownOpen = ref(false);
                const breakdownLoading = ref(false);
                const breakdownStaffName = ref('');
                const breakdownActivities = ref([]);

                const divisionItems = computed(() => {
                    const list = [{ title: 'All divisions', value: '' }];
                    (cfg.divisions || []).forEach((d) => {
                        list.push({ title: d.division_name, value: String(d.id) });
                    });
                    return list;
                });

                const staffItems = computed(() => {
                    const list = [{ title: 'All staff', value: '' }];
                    (cfg.staffOptions || []).forEach((s) => {
                        list.push({ title: s.label, value: String(s.staff_id) });
                    });
                    return list;
                });

                const yearItems = computed(() => {
                    const list = [{ title: 'All years', value: '' }];
                    (cfg.years || []).forEach((y) => list.push({ title: String(y), value: String(y) }));
                    return list;
                });

                const quarterItems = computed(() => {
                    const list = [{ title: 'All quarters', value: '' }];
                    (cfg.quarters || ['Q1', 'Q2', 'Q3', 'Q4']).forEach((q) => {
                        list.push({ title: q, value: q });
                    });
                    return list;
                });

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Staff name', key: 'staff_name', sortable: true, minWidth: 180 },
                    { title: 'Division', key: 'division_name', sortable: true, minWidth: 140 },
                    { title: 'Year & quarter', key: 'year_quarter', sortable: true, width: 130 },
                    { title: 'QM activities', key: 'activity_count', sortable: true, align: 'center', width: 120 },
                    { title: 'Approved travel days', key: 'approved_travel_days', sortable: true, align: 'center', width: 150 },
                ];

                const showingRange = computed(() => {
                    if (!pagination.value.total) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                const summaryKpis = computed(() => [
                    { key: 'staff', icon: 'mdi-account-group', accent: '#119a48', value: summary.value.staff_count, label: 'Staff in report' },
                    { key: 'rows', icon: 'mdi-table', accent: '#0284c7', value: summary.value.total_rows, label: 'Rows' },
                    { key: 'activities', icon: 'mdi-clipboard-list', accent: '#15803d', value: summary.value.total_activities, label: 'QM activities' },
                    { key: 'days', icon: 'mdi-airplane', accent: '#d97706', value: summary.value.total_travel_days, label: 'Approved travel days' },
                ]);

                const filterLabel = computed(() => {
                    const parts = [];
                    if (filters.value.year && filters.value.quarter) {
                        parts.push(`${filters.value.year} ${filters.value.quarter}`);
                    } else if (filters.value.year) {
                        parts.push(String(filters.value.year));
                    } else if (filters.value.quarter) {
                        parts.push(filters.value.quarter);
                    } else {
                        parts.push('All periods');
                    }
                    return parts.join(' · ');
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function buildQueryParams(includeSort = true) {
                    const params = new URLSearchParams();
                    params.set('page', String(page.value));
                    params.set('per_page', String(itemsPerPage.value));
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.staff_id) params.set('staff_id', filters.value.staff_id);
                    if (filters.value.year) params.set('year', filters.value.year);
                    if (filters.value.quarter) params.set('quarter', filters.value.quarter);
                    if (includeSort && sortBy.value.length) {
                        params.set('sort_column', sortBy.value[0].key);
                        params.set('sort_dir', sortBy.value[0].order);
                    }
                    return params;
                }

                const exportExcelUrl = computed(() => {
                    const qs = buildQueryParams(true).toString();
                    return qs ? `${cfg.routes.exportExcel}?${qs}` : cfg.routes.exportExcel;
                });

                const exportPdfUrl = computed(() => {
                    const qs = buildQueryParams(true).toString();
                    return qs ? `${cfg.routes.exportPdf}?${qs}` : cfg.routes.exportPdf;
                });

                async function loadReport() {
                    loading.value = true;
                    try {
                        const qs = buildQueryParams(true).toString();
                        const url = qs ? `${cfg.routes.data}?${qs}` : cfg.routes.data;
                        const res = await fetch(url, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not load report.');
                        }
                        items.value = (json.data || []).map((row, index) => ({
                            ...row,
                            row_num: (json.pagination?.from || 1) + index,
                        }));
                        pagination.value = {
                            total: json.pagination?.total ?? items.value.length,
                            from: json.pagination?.from ?? 0,
                            to: json.pagination?.to ?? 0,
                            last_page: json.pagination?.last_page ?? 1,
                        };
                        summary.value = {
                            staff_count: json.summary?.staff_count ?? 0,
                            total_travel_days: json.summary?.total_travel_days ?? 0,
                            total_activities: json.summary?.total_activities ?? 0,
                        };
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        summary.value = { total_rows: 0, staff_count: 0, total_travel_days: 0, total_activities: 0 };
                        notify(e.message || 'Could not load report.');
                    } finally {
                        loading.value = false;
                    }
                }

                async function openBreakdown(item) {
                    if (!item?.staff_id) return;
                    breakdownOpen.value = true;
                    breakdownLoading.value = true;
                    breakdownStaffName.value = item.staff_name || `Staff #${item.staff_id}`;
                    breakdownActivities.value = [];
                    try {
                        const params = buildQueryParams(false);
                        const base = cfg.routes.breakdown.replace('__STAFF_ID__', String(item.staff_id));
                        const qs = params.toString();
                        const url = qs ? `${base}?${qs}` : base;
                        const res = await fetch(url, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error('Could not load activity breakdown.');
                        }
                        breakdownStaffName.value = json.staff_name || breakdownStaffName.value;
                        breakdownActivities.value = json.activities || [];
                    } catch (e) {
                        breakdownActivities.value = [];
                        notify(e.message || 'Could not load activity breakdown.');
                    } finally {
                        breakdownLoading.value = false;
                    }
                }

                function downloadBreakdownCsv() {
                    if (!breakdownActivities.value.length) return;
                    const BOM = '\uFEFF';
                    const header = ['#', 'Activity title', 'Year & Quarter', 'Travel days'];
                    const rows = breakdownActivities.value.map((row, idx) => [
                        idx + 1,
                        row.activity_title || '',
                        row.year_quarter || '',
                        row.travel_days || 0,
                    ]);
                    const csv = [header, ...rows]
                        .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
                        .join('\r\n');
                    const blob = new Blob([BOM + csv], { type: 'text/csv;charset=utf-8' });
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(blob);
                    a.download = `activity_breakdown_${(breakdownStaffName.value || 'staff').replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.csv`;
                    a.click();
                    URL.revokeObjectURL(a.href);
                }

                function resetFilters() {
                    filters.value = {
                        division_id: '',
                        staff_id: '',
                        year: String(cfg.defaults?.year ?? cfg.currentYear ?? ''),
                        quarter: cfg.defaults?.quarter ?? cfg.currentQuarter ?? '',
                    };
                    sortBy.value = [{ key: 'division_name', order: 'asc' }];
                    page.value = 1;
                    loadReport();
                }

                watch(page, () => loadReport());
                watch(sortBy, () => {
                    page.value = 1;
                    loadReport();
                }, { deep: true });

                loadReport();

                return {
                    cfg,
                    filters,
                    sortBy,
                    page,
                    itemsPerPage,
                    items,
                    pagination,
                    showingRange,
                    summaryKpis,
                    headers,
                    loading,
                    snackbar,
                    breakdownOpen,
                    breakdownLoading,
                    breakdownStaffName,
                    breakdownActivities,
                    divisionItems,
                    staffItems,
                    yearItems,
                    quarterItems,
                    filterLabel,
                    exportExcelUrl,
                    exportPdfUrl,
                    loadReport,
                    openBreakdown,
                    downloadBreakdownCsv,
                    resetFilters,
                };
            },
            template: `
<v-app class="sqt-vuetify-app">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap gap-2 mb-4">
      <v-btn :href="cfg.routes.reportsIndex" variant="outlined" prepend-icon="mdi-arrow-left" size="small">Reports</v-btn>
    </div>

    <v-alert type="info" variant="tonal" density="comfortable" class="mb-4">
      <div class="text-subtitle-2 font-weight-bold mb-1">What this report includes</div>
      <div class="text-body-2">
        Travel days from approved matrix and single-memo activities whose matrix is approved.
        If an approved change request exists, its participant list is used.
        <strong>Approved travel days</strong> counts only international travel participant days.
      </div>
    </v-alert>

    <v-card class="mb-4">
      <v-card-title class="text-subtitle-1 font-weight-medium py-3">
        <v-icon icon="mdi-filter-outline" class="me-2" color="primary"></v-icon>Filters
        <v-chip size="small" variant="tonal" color="primary" class="ms-2">{{ filterLabel }}</v-chip>
      </v-card-title>
      <v-card-text>
        <v-row dense align="end">
          <v-col cols="12" sm="6" md="3">
            <v-select v-model="filters.division_id" :items="divisionItems" label="Division" clearable></v-select>
          </v-col>
          <v-col cols="12" sm="6" md="3">
            <v-autocomplete
              v-model="filters.staff_id"
              :items="staffItems"
              item-title="title"
              item-value="value"
              label="Staff"
              clearable
              auto-select-first
            ></v-autocomplete>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.year" :items="yearItems" label="Year"></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.quarter" :items="quarterItems" label="Quarter"></v-select>
          </v-col>
          <v-col cols="12" md="2" class="d-flex gap-2">
            <v-btn color="primary" block @click="page = 1; loadReport()">Apply</v-btn>
            <v-btn variant="outlined" block @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2 py-3">
        <span class="text-subtitle-1 font-weight-medium">
          <v-icon icon="mdi-airplane" color="primary" class="me-2"></v-icon>Staff quarterly travel days
        </span>
        <div class="d-flex gap-2">
          <v-btn :href="exportExcelUrl" color="success" variant="tonal" size="small" prepend-icon="mdi-file-excel">Excel</v-btn>
          <v-btn :href="exportPdfUrl" target="_blank" color="error" variant="tonal" size="small" prepend-icon="mdi-file-pdf-box">PDF</v-btn>
        </div>
      </v-card-title>

      <v-card-text class="px-4 pt-0">
        <v-row dense class="mb-4">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="sqt-kpi-card" elevation="0" :style="{ '--sqt-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div class="sqt-kpi-icon-wrap me-3" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div>
                  <div class="sqt-kpi-value">{{ kpi.value }}</div>
                  <div class="sqt-kpi-label">{{ kpi.label }}</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-data-table
          v-model:sort-by="sortBy"
          class="sqt-table elevation-0 border rounded-lg"
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="-1"
          hide-default-footer
          must-sort
        >
          <template #item.row_num="{ item }">
            <span class="sqt-row-num">{{ item.row_num }}</span>
          </template>
          <template #item.staff_name="{ item }">
            <button type="button" class="sqt-staff-link" @click="openBreakdown(item)">{{ item.staff_name }}</button>
          </template>
          <template #item.division_name="{ item }">
            <span class="sqt-cell-text">{{ item.division_name }}</span>
          </template>
          <template #item.year_quarter="{ item }">
            <span class="sqt-cell-text">{{ item.year_quarter }}</span>
          </template>
          <template #item.activity_count="{ item }">
            <span class="sqt-metric">{{ item.activity_count }}</span>
          </template>
          <template #item.approved_travel_days="{ item }">
            <span class="sqt-metric sqt-metric--days">{{ item.approved_travel_days }}</span>
          </template>
          <template #no-data>
            <v-alert type="info" variant="tonal" class="ma-4">No data for the selected filters.</v-alert>
          </template>
          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
              <span class="text-body-2 sqt-footer-text">Showing {{ showingRange }} of {{ pagination.total }} rows</span>
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

    <v-dialog v-model="breakdownOpen" max-width="960" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between gap-2 py-3">
          <span class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-format-list-bulleted" class="me-2"></v-icon>
            Activity breakdown — {{ breakdownStaffName }}
          </span>
          <div class="d-flex gap-2">
            <v-btn
              v-if="breakdownActivities.length"
              size="small"
              color="success"
              variant="tonal"
              prepend-icon="mdi-download"
              @click="downloadBreakdownCsv"
            >Export CSV</v-btn>
            <v-btn icon="mdi-close" variant="text" @click="breakdownOpen = false"></v-btn>
          </div>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text style="min-height: 200px;">
          <div v-if="breakdownLoading" class="text-center py-8">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
            <p class="text-medium-emphasis mt-3 mb-0">Loading activities…</p>
          </div>
          <v-alert v-else-if="!breakdownActivities.length" type="info" variant="tonal">
            No activities found for this staff member with the current filters.
          </v-alert>
          <v-table v-else density="comfortable" class="sqt-breakdown-table">
            <thead>
              <tr>
                <th class="text-center" style="width:3rem">#</th>
                <th>Activity title</th>
                <th>Year & quarter</th>
                <th class="text-center">Travel days</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in breakdownActivities" :key="row.activity_id || idx">
                <td class="text-center">{{ idx + 1 }}</td>
                <td>
                  <a v-if="row.show_url" :href="row.show_url" class="text-primary text-decoration-none">{{ row.activity_title }}</a>
                  <span v-else>{{ row.activity_title }}</span>
                </td>
                <td>{{ row.year_quarter }}</td>
                <td class="text-center sqt-metric">{{ row.travel_days }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootStaffQuarterlyTravel);
    }
})();
