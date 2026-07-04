/**
 * Budget execution dashboard — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'budget-execution-app';
    let appInstance = null;

    const TYPE_LABELS = {
        matrix_activity: 'Matrix activity',
        single_memo: 'Single memo',
        special_memo: 'Special memo',
        non_travel_memo: 'Non-travel',
    };

    function fmtMoney(n) {
        return '$' + (parseFloat(n) || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function fmtPct(n) {
        return (parseFloat(n) || 0).toFixed(1) + '%';
    }

    function pctColor(n) {
        const v = parseFloat(n) || 0;
        if (v >= 99.9) return 'success';
        if (v >= 50) return 'warning';
        return 'error';
    }

    function typeLabel(t) {
        return TYPE_LABELS[t] || t || '—';
    }

    function bootBudgetExecution(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            appInstance = null;
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        appInstance = null;
        mountEl.innerHTML = '';

        const { createApp, ref, computed, onMounted } = Vue;
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
                            error: '#dc2626',
                            info: '#2563eb',
                            warning: '#d97706',
                            surface: '#ffffff',
                            background: '#f8fafc',
                        },
                    },
                },
            },
            defaults: {
                VCard: { rounded: 'lg', elevation: 2 },
                VBtn: { rounded: 'lg' },
                VSelect: { variant: 'outlined', density: 'comfortable' },
            },
        });

        appInstance = createApp({
            setup() {
                const filters = ref({
                    period_mode: 'quarterly',
                    year: cfg.currentYear,
                    quarter: cfg.currentQuarter,
                    division_id: cfg.canViewAllDivisions
                        ? ''
                        : String(cfg.defaultDivisionId || ''),
                });

                const page = ref(1);
                const loading = ref(false);
                const error = ref('');
                const dashboard = ref(null);
                const snackbar = ref({ show: false, text: '', color: 'success' });

                const periodModeItems = [
                    { title: 'Quarterly', value: 'quarterly' },
                    { title: 'Annual (full year)', value: 'annual' },
                ];

                const yearItems = (cfg.years || []).map((y) => ({ title: String(y), value: y }));
                const quarterItems = (cfg.quarters || []).map((q) => ({ title: q, value: q }));

                const divisionItems = computed(() => {
                    const list = [{ title: 'All divisions', value: '' }];
                    (cfg.divisions || []).forEach((d) => list.push({ title: d.name, value: String(d.id) }));
                    return list;
                });

                const scopeMessage = computed(() => {
                    if (cfg.scopeAccess === 'all') {
                        return 'Viewing all divisions — 10 divisions per page. Use filters to narrow results.';
                    }
                    if (cfg.isDirector) {
                        return 'Viewing divisions under your directorate oversight.';
                    }
                    return 'Viewing your division only.';
                });

                const scopeIcon = computed(() => {
                    if (cfg.scopeAccess === 'all') return 'mdi-office-building';
                    if (cfg.isDirector) return 'mdi-sitemap';
                    return 'mdi-domain';
                });

                const periodLabel = computed(() => {
                    const f = dashboard.value?.filters || {};
                    return f.period_mode === 'annual'
                        ? `Annual ${f.year}`
                        : `${f.quarter || ''} ${f.year}`.trim();
                });

                const summary = computed(() => dashboard.value?.summary || {});
                const divisions = computed(() => dashboard.value?.divisions || []);
                const pagination = computed(() => dashboard.value?.pagination || null);
                const cacheLabel = computed(() => {
                    const ts = dashboard.value?.cached_at;
                    return ts ? `Cached ${new Date(ts).toLocaleString()}` : '';
                });

                const exportQuery = computed(() => {
                    const params = new URLSearchParams();
                    params.set('year', String(filters.value.year));
                    params.set('period_mode', filters.value.period_mode);
                    if (filters.value.period_mode === 'quarterly') {
                        params.set('quarter', filters.value.quarter);
                    }
                    if (filters.value.division_id) {
                        params.set('division_id', filters.value.division_id);
                    }
                    return params.toString();
                });

                const excelHref = computed(() => `${cfg.routes.excel}?${exportQuery.value}`);
                const pdfHref = computed(() => `${cfg.routes.pdf}?${exportQuery.value}`);

                const initiativeHeaders = [
                    { title: 'Type', key: 'source_type', sortable: false, width: '9%' },
                    { title: 'Document', key: 'document_number', sortable: false, width: '11%' },
                    { title: 'Title', key: 'title', sortable: false, width: '22%' },
                    { title: 'Budget', key: 'planned_budget', sortable: false, align: 'end', width: '9%' },
                    { title: 'Executed', key: 'executed_budget', sortable: false, align: 'end', width: '9%' },
                    { title: '%', key: 'execution_pct', sortable: false, align: 'end', width: '6%' },
                    { title: 'Status', key: 'status', sortable: false, width: '10%' },
                    { title: 'Fund codes', key: 'fund_codes', sortable: false, width: '24%' },
                ];

                const fundCodeHeaders = [
                    { title: 'Code', key: 'code', sortable: false },
                    { title: 'Planned', key: 'planned', sortable: false, align: 'end' },
                    { title: 'Executed', key: 'executed', sortable: false, align: 'end' },
                    { title: 'Remaining', key: 'remaining', sortable: false, align: 'end' },
                    { title: 'Working bal.', key: 'working_balance', sortable: false, align: 'end' },
                ];

                function initiativeStatus(row) {
                    if (row.fully_executed) return { label: '100%', color: 'success' };
                    if (row.has_sr_or_arf) return { label: 'Partial', color: 'warning' };
                    return { label: 'Not started', color: 'secondary' };
                }

                function buildParams(forceRefresh) {
                    const params = new URLSearchParams();
                    params.set('year', String(filters.value.year));
                    params.set('period_mode', filters.value.period_mode);
                    if (filters.value.period_mode === 'quarterly') {
                        params.set('quarter', filters.value.quarter);
                    }
                    if (filters.value.division_id) {
                        params.set('division_id', filters.value.division_id);
                    }
                    params.set('page', String(page.value));
                    if (forceRefresh) params.set('nocache', '1');
                    return params;
                }

                async function loadData(forceRefresh = false, resetPage = false) {
                    if (resetPage) page.value = 1;
                    loading.value = true;
                    error.value = '';
                    try {
                        const res = await fetch(`${cfg.routes.data}?${buildParams(forceRefresh).toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok || json?.error) {
                            throw new Error(json?.message || json?.error || 'Could not load data.');
                        }
                        dashboard.value = json;
                    } catch (e) {
                        dashboard.value = null;
                        error.value = e.message || 'Could not load budget execution data.';
                    } finally {
                        loading.value = false;
                    }
                }

                function applyFilters() {
                    loadData(false, true);
                }

                function resetFilters() {
                    filters.value = {
                        period_mode: 'quarterly',
                        year: cfg.currentYear,
                        quarter: cfg.currentQuarter,
                        division_id: cfg.canViewAllDivisions
                            ? ''
                            : String(cfg.defaultDivisionId || ''),
                    };
                    loadData(false, true);
                }

                function onPageChange() {
                    loadData(false, false);
                }

                onMounted(() => loadData(false, false));

                return {
                    cfg,
                    filters,
                    page,
                    loading,
                    error,
                    dashboard,
                    snackbar,
                    periodModeItems,
                    yearItems,
                    quarterItems,
                    divisionItems,
                    scopeMessage,
                    scopeIcon,
                    periodLabel,
                    summary,
                    divisions,
                    pagination,
                    cacheLabel,
                    excelHref,
                    pdfHref,
                    initiativeHeaders,
                    fundCodeHeaders,
                    fmtMoney,
                    fmtPct,
                    pctColor,
                    typeLabel,
                    initiativeStatus,
                    applyFilters,
                    resetFilters,
                    loadData,
                    onPageChange,
                };
            },
            template: `
<v-app class="be-vuetify-app">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
      <v-btn :href="cfg.routes.reports" variant="outlined" size="small" prepend-icon="mdi-arrow-left">
        Reports
      </v-btn>
      <div v-if="dashboard" class="d-flex gap-2">
        <v-btn :href="excelHref" target="_blank" color="success" variant="tonal" size="small" prepend-icon="mdi-file-excel">
          Export Excel
        </v-btn>
        <v-btn :href="pdfHref" target="_blank" color="error" variant="tonal" size="small" prepend-icon="mdi-file-pdf-box">
          Export PDF
        </v-btn>
      </div>
    </div>

    <v-sheet class="be-hero rounded-lg pa-4 mb-4">
      <div class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <div class="text-h6 font-weight-bold">
            <v-icon icon="mdi-chart-donut" class="me-2"></v-icon>Budget execution
          </div>
          <div class="text-body-2 opacity-90">
            Approved APM initiatives vs executed through Service Requests &amp; ARFs
          </div>
        </div>
        <div class="text-body-2 opacity-90">{{ periodLabel || '—' }}</div>
      </div>
    </v-sheet>

    <v-alert type="success" variant="tonal" density="comfortable" class="mb-4" :icon="scopeIcon">
      {{ scopeMessage }}
    </v-alert>

    <v-card class="mb-4">
      <v-card-title class="text-subtitle-1 font-weight-medium">
        <v-icon icon="mdi-filter-outline" class="me-2"></v-icon>Filters
      </v-card-title>
      <v-card-text>
        <v-row dense align="end">
          <v-col cols="6" md="2">
            <v-select v-model="filters.period_mode" :items="periodModeItems" label="Period" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.year" :items="yearItems" label="Year" hide-details></v-select>
          </v-col>
          <v-col v-show="filters.period_mode === 'quarterly'" cols="6" md="2">
            <v-select v-model="filters.quarter" :items="quarterItems" label="Quarter" hide-details></v-select>
          </v-col>
          <v-col v-if="cfg.canPickDivision" cols="12" md="3">
            <v-select
              v-model="filters.division_id"
              :items="divisionItems"
              label="Division"
              hide-details
              :disabled="!cfg.canViewAllDivisions && divisionItems.length <= 2"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3" class="d-flex gap-2">
            <v-btn color="primary" prepend-icon="mdi-magnify" @click="applyFilters">Apply</v-btn>
            <v-btn variant="outlined" @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <div v-if="loading" class="text-center py-10">
      <v-progress-circular indeterminate color="primary" size="48"></v-progress-circular>
      <p class="text-medium-emphasis mt-3 mb-0">Loading budget execution…</p>
    </div>

    <v-alert v-else-if="error" type="error" variant="tonal" class="mb-4">
      <div class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <strong>Could not load budget execution data.</strong>
          <div class="text-body-2 mt-1">{{ error }}</div>
        </div>
        <v-btn variant="outlined" color="error" size="small" @click="loadData(true, false)">Retry</v-btn>
      </div>
    </v-alert>

    <template v-else-if="dashboard">
      <div v-if="cacheLabel" class="text-caption text-medium-emphasis mb-2">{{ cacheLabel }}</div>

      <v-row dense class="mb-4">
        <v-col cols="6" lg="3">
          <v-sheet rounded="lg" class="pa-4 h-100 border" color="surface">
            <v-icon icon="mdi-file-document-multiple" color="success" class="mb-2"></v-icon>
            <div class="text-caption text-medium-emphasis">Initiatives</div>
            <div class="text-h5 font-weight-bold text-success">{{ summary.initiative_count || 0 }}</div>
            <div class="text-caption text-medium-emphasis">{{ summary.division_count || 0 }} divisions</div>
          </v-sheet>
        </v-col>
        <v-col cols="6" lg="3">
          <v-sheet rounded="lg" class="pa-4 h-100 border" color="surface">
            <v-icon icon="mdi-wallet" color="info" class="mb-2"></v-icon>
            <div class="text-caption text-medium-emphasis">Approved budget</div>
            <div class="text-h6 font-weight-bold">{{ fmtMoney(summary.planned_budget) }}</div>
            <div class="text-caption text-medium-emphasis">Remaining {{ fmtMoney(summary.remaining_budget) }}</div>
          </v-sheet>
        </v-col>
        <v-col cols="6" lg="3">
          <v-sheet rounded="lg" class="pa-4 h-100 border" color="surface">
            <v-icon icon="mdi-swap-horizontal" color="warning" class="mb-2"></v-icon>
            <div class="text-caption text-medium-emphasis">Executed (SR/ARF)</div>
            <div class="text-h6 font-weight-bold">{{ fmtMoney(summary.executed_budget) }}</div>
            <div class="text-caption text-medium-emphasis">{{ summary.sr_count || 0 }} SR · {{ summary.arf_count || 0 }} ARF</div>
          </v-sheet>
        </v-col>
        <v-col cols="6" lg="3">
          <v-sheet rounded="lg" class="pa-4 h-100 border" color="surface">
            <v-icon icon="mdi-check-circle" color="success" class="mb-2"></v-icon>
            <div class="text-caption text-medium-emphasis">Execution rate</div>
            <div class="text-h5 font-weight-bold" :class="'text-' + pctColor(summary.execution_pct)">{{ fmtPct(summary.execution_pct) }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ summary.fully_executed_count || 0 }} at 100% · {{ summary.partial_count || 0 }} partial · {{ summary.not_started_count || 0 }} not started
            </div>
          </v-sheet>
        </v-col>
      </v-row>

      <v-alert v-if="!divisions.length" type="info" variant="tonal" class="mb-4">
        No data for this period.
      </v-alert>

      <div v-for="div in divisions" :key="div.division_id || div.division_name" class="mb-6">
        <v-card>
          <v-card-text class="pb-2">
            <div class="d-flex flex-wrap justify-space-between align-start gap-4">
              <div class="flex-grow-1">
                <div class="text-h6 font-weight-bold text-primary mb-2">
                  <v-icon icon="mdi-domain" class="me-1"></v-icon>{{ div.division_name }}
                </div>
                <div class="d-flex flex-wrap gap-2">
                  <v-chip size="small" variant="tonal" prepend-icon="mdi-file-document">{{ div.initiative_count || 0 }} initiatives</v-chip>
                  <v-chip size="small" color="success" variant="tonal">{{ div.fully_executed_count || 0 }} at 100%</v-chip>
                  <v-chip size="small" color="warning" variant="tonal">{{ div.partial_count || 0 }} partial</v-chip>
                  <v-chip size="small" variant="outlined">{{ div.not_started_count || 0 }} not started</v-chip>
                  <v-chip size="small" variant="tonal">{{ div.sr_count || 0 }} SR · {{ div.arf_count || 0 }} ARF</v-chip>
                </div>
              </div>
              <div class="d-flex flex-wrap align-center gap-4">
                <v-progress-circular
                  :model-value="Math.min(100, parseFloat(div.execution_pct) || 0)"
                  :color="pctColor(div.execution_pct)"
                  size="64"
                  width="6"
                >
                  <span class="text-caption font-weight-bold">{{ fmtPct(div.execution_pct) }}</span>
                </v-progress-circular>
                <div class="d-flex flex-column gap-2">
                  <v-sheet color="primary" rounded="lg" class="pa-3 text-white" min-width="160">
                    <div class="text-caption text-uppercase opacity-90">Budget remaining</div>
                    <div class="text-h6 font-weight-bold">{{ fmtMoney(div.remaining_budget) }}</div>
                    <div class="text-caption opacity-75">{{ fmtMoney(div.executed_budget) }} of {{ fmtMoney(div.planned_budget) }} executed</div>
                  </v-sheet>
                  <v-sheet color="info" rounded="lg" class="pa-3 text-white" min-width="160">
                    <div class="text-caption text-uppercase opacity-90">Fund working balance</div>
                    <div class="text-h6 font-weight-bold">{{ fmtMoney(div.total_working_balance) }}</div>
                    <div class="text-caption opacity-75">{{ div.fund_code_count || 0 }} fund codes</div>
                  </v-sheet>
                </div>
              </div>
            </div>
            <v-progress-linear
              class="mt-4 rounded-pill"
              :model-value="Math.min(100, parseFloat(div.execution_pct) || 0)"
              color="primary"
              height="8"
              rounded
            ></v-progress-linear>
          </v-card-text>

          <v-divider v-if="div.fund_codes?.length"></v-divider>
          <div v-if="div.fund_codes?.length" class="pa-4 bg-grey-lighten-5">
            <div class="text-subtitle-2 font-weight-medium text-primary mb-2">
              <v-icon icon="mdi-barcode" size="small" class="me-1"></v-icon>
              Fund codes ({{ div.fund_code_count || 0 }})
            </div>
            <v-data-table
              :headers="fundCodeHeaders"
              :items="div.fund_codes"
              density="compact"
              class="border rounded-lg bg-white"
              hide-default-footer
              :items-per-page="-1"
            >
              <template #item.code="{ item }">
                <span>{{ item.code }}</span>
                <span v-if="item.activity" class="text-caption text-medium-emphasis d-block">{{ item.activity }}</span>
              </template>
              <template #item.planned="{ item }">{{ fmtMoney(item.planned) }}</template>
              <template #item.executed="{ item }">{{ fmtMoney(item.executed) }}</template>
              <template #item.remaining="{ item }">{{ fmtMoney(item.remaining) }}</template>
              <template #item.working_balance="{ item }">{{ fmtMoney(item.working_balance) }}</template>
            </v-data-table>
          </div>

          <v-data-table
            :headers="initiativeHeaders"
            :items="div.initiatives || []"
            density="compact"
            class="be-initiatives-table"
            hide-default-footer
            :items-per-page="-1"
          >
            <template #item.source_type="{ item }">{{ typeLabel(item.source_type) }}</template>
            <template #item.document_number="{ item }">
              <span class="text-body-2">{{ item.document_number || '—' }}</span>
            </template>
            <template #item.title="{ item }">
              <span class="be-initiative-title text-body-2">{{ item.title || '—' }}</span>
            </template>
            <template #item.planned_budget="{ item }">{{ fmtMoney(item.planned_budget) }}</template>
            <template #item.executed_budget="{ item }">{{ fmtMoney(item.executed_budget) }}</template>
            <template #item.execution_pct="{ item }">
              <span :class="'text-' + pctColor(item.execution_pct) + ' font-weight-bold'">{{ fmtPct(item.execution_pct) }}</span>
            </template>
            <template #item.status="{ item }">
              <v-chip :color="initiativeStatus(item).color" size="x-small" variant="tonal" label>
                {{ initiativeStatus(item).label }}
              </v-chip>
            </template>
            <template #item.fund_codes="{ item }">
              <div v-if="item.fund_codes?.length">
                <div v-for="(fc, fi) in item.fund_codes" :key="fi" class="text-caption py-1 border-b">
                  <span class="font-weight-medium">{{ fc.code }}</span>
                  <span v-if="fc.activity" class="text-medium-emphasis"> ({{ fc.activity }})</span>
                  <div class="text-medium-emphasis">
                    Planned {{ fmtMoney(fc.planned) }} · Exec {{ fmtMoney(fc.executed) }} · Rem {{ fmtMoney(fc.remaining) }}
                  </div>
                  <div class="text-medium-emphasis">Working bal. {{ fmtMoney(fc.working_balance) }}</div>
                </div>
              </div>
              <span v-else class="text-medium-emphasis">—</span>
            </template>
            <template #no-data>
              <div class="text-center text-medium-emphasis py-4">No initiatives</div>
            </template>
          </v-data-table>
        </v-card>
      </div>

      <div v-if="pagination && pagination.total > pagination.per_page" class="d-flex flex-wrap align-center justify-space-between gap-3 mt-4 pt-3 border-t">
        <span class="text-body-2 text-medium-emphasis">
          Showing divisions {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
          · Page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <v-pagination
          v-model="page"
          :length="pagination.last_page"
          :total-visible="7"
          density="comfortable"
          rounded="circle"
          active-color="primary"
          @update:model-value="onPageChange"
        ></v-pagination>
      </div>
    </template>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
        appInstance.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootBudgetExecution);
    }
})();
