/**
 * Average time per document report — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'approver-document-timing-app';
    let appInstance = null;

    function bootApproverDocumentTiming(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            appInstance = null;
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        appInstance = null;
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch, onMounted, nextTick } = Vue;
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
                VTextField: { variant: 'outlined', density: 'comfortable' },
                VSelect: { variant: 'outlined', density: 'comfortable' },
            },
        });

        appInstance = createApp({
            setup() {
                const filters = ref({
                    staff_id: cfg.filters?.staff_id != null ? String(cfg.filters.staff_id) : '',
                    division_id: cfg.filters?.division_id ? String(cfg.filters.division_id) : '',
                    document_type: cfg.filters?.document_type || '',
                    year: cfg.filters?.year ? String(cfg.filters.year) : '',
                    month: cfg.filters?.month ? String(cfg.filters.month) : '',
                    q: cfg.filters?.q || '',
                });

                const trendGranularity = ref('monthly');
                const trendPoints = ref([]);
                const trendLoading = ref(false);
                const trendChartRef = ref(null);
                let trendChartInstance = null;

                const staffItems = computed(() => {
                    const list = [];
                    if (cfg.reportFullAccess) {
                        list.push({ title: 'All approvers with records', value: '' });
                    }
                    (cfg.staffOptions || []).forEach((s) => {
                        list.push({ title: s.label, value: String(s.staff_id) });
                    });
                    if (!cfg.reportFullAccess && cfg.sessionStaffId) {
                        const own = (cfg.staffOptions || []).find((s) => Number(s.staff_id) === Number(cfg.sessionStaffId));
                        if (own) {
                            return [{ title: own.label, value: String(own.staff_id) }];
                        }
                    }
                    return list;
                });

                const divisionItems = computed(() => {
                    const list = [{ title: 'All divisions', value: '' }];
                    (cfg.divisions || []).forEach((d) => list.push({ title: d.name, value: String(d.id) }));
                    return list;
                });

                const documentTypeItems = computed(() => {
                    const list = [{ title: 'All types', value: '' }];
                    (cfg.documentTypes || []).forEach((dt) => list.push({ title: dt, value: dt }));
                    return list;
                });

                const yearItems = computed(() => {
                    const list = [{ title: 'Any year', value: '' }];
                    (cfg.years || []).forEach((y) => list.push({ title: String(y), value: String(y) }));
                    return list;
                });

                const summaryKpis = computed(() => [
                    {
                        key: 'actions',
                        accent: '#119a48',
                        value: Number(cfg.summary?.total_rows || 0).toLocaleString(),
                        label: 'Actions in scope',
                    },
                    {
                        key: 'avg',
                        accent: '#0284c7',
                        value: cfg.summary?.avg_display || '—',
                        sub: cfg.summary?.avg_hours != null ? `${cfg.summary.avg_hours} hours (numeric)` : '',
                        label: 'Average time',
                    },
                    {
                        key: 'total',
                        accent: '#15803d',
                        value: Number(cfg.summary?.total_hours || 0).toLocaleString(undefined, { maximumFractionDigits: 1 }),
                        sub: 'Sum of hours elapsed at selected filters',
                        label: 'Total person-hours',
                    },
                ]);

                const tableHeaders = [
                    { title: 'Approver', key: 'staff_name', sortable: false, minWidth: 140 },
                    { title: 'Type', key: 'document_type_label', sortable: false, width: 110 },
                    { title: 'Document', key: 'document_title', sortable: false, minWidth: 220 },
                    { title: 'Division', key: 'division_name', sortable: false, minWidth: 120 },
                    { title: 'Workflow / role', key: 'workflow_name', sortable: false, minWidth: 160 },
                    { title: 'Received', key: 'received_at', sortable: false, width: 130 },
                    { title: 'Acted', key: 'acted_at', sortable: false, width: 130 },
                    { title: 'Elapsed', key: 'elapsed_hours', sortable: false, width: 100, align: 'end' },
                    { title: '', key: 'actions', sortable: false, width: 72, align: 'end' },
                ];

                function buildFilterParams(extra) {
                    const params = new URLSearchParams();
                    if (cfg.reportFullAccess && filters.value.staff_id) {
                        params.set('staff_id', filters.value.staff_id);
                    } else if (!cfg.reportFullAccess && cfg.sessionStaffId) {
                        params.set('staff_id', String(cfg.sessionStaffId));
                    }
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.document_type) params.set('document_type', filters.value.document_type);
                    if (filters.value.year) params.set('year', filters.value.year);
                    if (filters.value.month) params.set('month', filters.value.month);
                    if (filters.value.q.trim()) params.set('q', filters.value.q.trim());
                    if (extra) {
                        Object.keys(extra).forEach((k) => {
                            if (extra[k] != null && extra[k] !== '') params.set(k, String(extra[k]));
                        });
                    }
                    return params;
                }

                function applyFilters() {
                    const params = buildFilterParams();
                    window.location.href = `${cfg.routes.index}?${params.toString()}`;
                }

                function clearFilters() {
                    const params = new URLSearchParams();
                    if (!cfg.reportFullAccess && cfg.sessionStaffId) {
                        params.set('staff_id', String(cfg.sessionStaffId));
                    }
                    window.location.href = `${cfg.routes.index}?${params.toString()}`;
                }

                function exportUrl() {
                    return `${cfg.routes.export}?${buildFilterParams().toString()}`;
                }

                function paginationUrl(page) {
                    return `${cfg.routes.index}?${buildFilterParams({ page }).toString()}`;
                }

                function destroyTrendChart() {
                    if (trendChartInstance) {
                        try { trendChartInstance.destroy(); } catch (e) {}
                        trendChartInstance = null;
                    }
                }

                function drawTrendChart(attempt = 0) {
                    const el = trendChartRef.value;
                    if (!el || typeof Highcharts === 'undefined') {
                        if (attempt < 25) setTimeout(() => drawTrendChart(attempt + 1), 100);
                        return;
                    }
                    destroyTrendChart();
                    const points = trendPoints.value || [];
                    if (!points.length) {
                        el.innerHTML = '<p class="text-medium-emphasis text-center py-8 mb-0">No timing trend data for the current filters.</p>';
                        return;
                    }
                    el.innerHTML = '';
                    const categories = points.map((p) => p.label);
                    const seriesData = points.map((p) => ({
                        y: Number(p.avg_hours) || 0,
                        count: Number(p.count) || 0,
                    }));
                    const maxVal = seriesData.length ? Math.max(...seriesData.map((d) => d.y)) : 0;
                    const yMax = maxVal > 0 ? Math.ceil(maxVal * 1.15 * 10) / 10 : 10;
                    const granLabel = trendGranularity.value === 'weekly' ? 'Weekly' : 'Monthly';
                    try {
                        trendChartInstance = Highcharts.chart(el, {
                            chart: { type: 'line', height: 320 },
                            title: { text: `${granLabel} average hours trend` },
                            subtitle: { text: 'Average hours elapsed per approval action in each period.' },
                            xAxis: { categories, crosshair: true, labels: { rotation: -35, style: { fontSize: '11px' } } },
                            yAxis: { min: 0, max: yMax, title: { text: 'Average hours' } },
                            tooltip: {
                                pointFormat: 'Avg: <b>{point.y}</b> hrs<br/>Actions: <b>{point.count}</b>',
                            },
                            plotOptions: {
                                line: {
                                    color: '#119a48',
                                    lineWidth: 2,
                                    marker: { radius: 4, fillColor: '#119a48' },
                                    dataLabels: { enabled: false },
                                },
                            },
                            series: [{
                                name: 'Avg. hours',
                                data: seriesData,
                            }],
                            credits: { enabled: false },
                        });
                    } catch (err) {
                        console.warn('Timing trend chart render failed:', err);
                    }
                }

                async function loadTrend() {
                    trendLoading.value = true;
                    try {
                        const params = buildFilterParams({ granularity: trendGranularity.value });
                        const res = await fetch(`${cfg.routes.trend}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        trendPoints.value = json?.success && Array.isArray(json.data) ? json.data : [];
                    } catch (e) {
                        trendPoints.value = [];
                    } finally {
                        trendLoading.value = false;
                        nextTick(() => drawTrendChart());
                    }
                }

                watch(trendGranularity, () => loadTrend());

                onMounted(() => loadTrend());

                return {
                    cfg,
                    filters,
                    staffItems,
                    divisionItems,
                    documentTypeItems,
                    yearItems,
                    months: cfg.months || [],
                    summaryKpis,
                    tableHeaders,
                    records: cfg.records || [],
                    pagination: cfg.pagination || {},
                    trendGranularity,
                    trendPoints,
                    trendLoading,
                    trendChartRef,
                    applyFilters,
                    clearFilters,
                    exportUrl,
                    paginationUrl,
                };
            },
            template: `
<v-app class="adt-vuetify-app">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center gap-2 mb-4">
      <v-btn :href="cfg.routes.reportsIndex" variant="outlined" prepend-icon="mdi-arrow-left" size="small">Reports</v-btn>
      <span class="text-caption text-medium-emphasis ms-auto">Receipt rules match the Approver Dashboard average-time calculation.</span>
    </div>

    <v-alert v-if="!cfg.reportFullAccess" type="info" variant="tonal" density="compact" class="mb-4">
      You are viewing <strong>your own</strong> approval timing only. Administrators (role 10 or permissions 87 / 88) can filter by any approver.
    </v-alert>

    <v-row dense class="mb-4">
      <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="12" md="4">
        <v-card class="adt-kpi-card" elevation="1" :style="{ '--adt-kpi-accent': kpi.accent }">
          <v-card-text class="py-4">
            <div class="text-caption text-medium-emphasis text-uppercase font-weight-medium">{{ kpi.label }}</div>
            <div class="text-h5 font-weight-bold mt-1">{{ kpi.value }}</div>
            <div v-if="kpi.sub" class="text-caption text-medium-emphasis mt-1">{{ kpi.sub }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <div class="text-subtitle-1 font-weight-medium">Average hours trend</div>
          <div class="text-caption text-medium-emphasis">Per approval action at current filters</div>
        </div>
        <v-btn-toggle v-model="trendGranularity" mandatory density="compact" color="primary" rounded="lg" :disabled="trendLoading">
          <v-btn value="monthly" size="small">Monthly</v-btn>
          <v-btn value="weekly" size="small">Weekly</v-btn>
        </v-btn-toggle>
      </v-card-title>
      <v-card-text>
        <v-progress-linear v-if="trendLoading" indeterminate color="primary" class="mb-2"></v-progress-linear>
        <div ref="trendChartRef" style="min-height:320px;"></div>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center gap-2">
        <v-icon icon="mdi-filter-outline" class="me-1"></v-icon>
        <span class="text-subtitle-1 font-weight-medium">Filters</span>
        <v-spacer></v-spacer>
        <v-btn :href="exportUrl()" color="success" variant="tonal" size="small" prepend-icon="mdi-download">Export CSV</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.staff_id"
              :items="staffItems"
              label="Approver"
              hide-details
              :disabled="!cfg.reportFullAccess"
            ></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.division_id" :items="divisionItems" label="Division" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.document_type" :items="documentTypeItems" label="Document type" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.year" :items="yearItems" label="Year" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="1">
            <v-select v-model="filters.month" :items="months" item-title="title" item-value="value" label="Month" hide-details></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-text-field v-model="filters.q" label="Search" prepend-inner-icon="mdi-magnify" hide-details clearable placeholder="Title, document #, approver…"></v-text-field>
          </v-col>
          <v-col cols="12" class="d-flex gap-2">
            <v-btn color="primary" prepend-icon="mdi-magnify" @click="applyFilters">Apply</v-btn>
            <v-btn variant="outlined" @click="clearFilters">Clear</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="text-subtitle-1 font-weight-medium">
        <v-icon icon="mdi-table" class="me-2"></v-icon>Document timing trail
      </v-card-title>
      <v-data-table
        :headers="tableHeaders"
        :items="records"
        density="comfortable"
        class="border-b"
        hide-default-footer
        :items-per-page="-1"
      >
        <template #item.staff_name="{ item }">
          <div class="font-weight-medium">{{ item.staff_name }}</div>
          <div class="text-caption text-medium-emphasis">ID {{ item.staff_id }}</div>
        </template>
        <template #item.document_type_label="{ item }">
          <v-chip size="small" variant="tonal">{{ item.document_type_label }}</v-chip>
        </template>
        <template #item.document_title="{ item }">
          <div class="font-weight-medium adt-doc-title">{{ item.document_title }}</div>
          <div v-if="item.document_number" class="text-caption text-medium-emphasis adt-doc-title mt-1">{{ item.document_number }}</div>
        </template>
        <template #item.workflow_name="{ item }">
          <div class="text-body-2">{{ item.workflow_name }}</div>
          <div class="text-caption text-medium-emphasis">{{ item.workflow_role }}</div>
        </template>
        <template #item.elapsed_hours="{ item }">
          <div class="font-weight-semibold">{{ item.elapsed_hours }} h</div>
          <div class="text-caption text-medium-emphasis">{{ item.elapsed_days }} d</div>
        </template>
        <template #item.actions="{ item }">
          <v-btn v-if="item.doc_url" :href="item.doc_url" size="small" variant="outlined" color="success">Open</v-btn>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #no-data>
          <v-alert type="info" variant="tonal" class="ma-4">
            No timing rows yet. Run the backfill job from Jobs management, then approve documents to capture new actions automatically.
          </v-alert>
        </template>
        <template #bottom>
          <v-divider></v-divider>
          <div v-if="records.length" class="d-flex justify-space-between align-center flex-wrap gap-2 pa-3 text-body-2">
            <span class="text-medium-emphasis">
              Total elapsed (all rows matching filters):
              <strong>{{ cfg.summary.total_elapsed_hours }} h</strong>
              · <strong>{{ cfg.summary.total_elapsed_days }} d</strong>
            </span>
            <div v-if="pagination.last_page > 1" class="d-flex align-center gap-2">
              <span class="text-caption text-medium-emphasis">
                {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
              </span>
              <v-pagination
                :model-value="pagination.current_page"
                :length="pagination.last_page"
                density="compact"
                total-visible="7"
                @update:model-value="(p) => { window.location.href = paginationUrl(p); }"
              ></v-pagination>
            </div>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <p class="text-caption text-medium-emphasis mt-3 mb-0">
      Rows store the approver’s staff ID and name at action time. “Received” is the prior submission or prior approval timestamp at that workflow step, consistent with the dashboard metric.
    </p>
  </v-container>
</v-app>
            `,
        });

        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
        appInstance.use(vuetify);
        appInstance.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootApproverDocumentTiming);
    }
})();
