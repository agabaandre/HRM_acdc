/**
 * Budget Execution — Finance SAP API (proxied + Redis-cached by APM)
 */
(function () {
    'use strict';

    const MOUNT_ID = 'budget-execution-app';
    let appInstance = null;

    function fmtMoney(n) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 0,
        }).format(Number(n || 0));
    }

    function compactUsd(n) {
        const v = Number(n || 0);
        const abs = Math.abs(v);
        if (abs >= 1_000_000) return (v / 1_000_000).toFixed(2) + 'M';
        if (abs >= 1_000) return (v / 1_000).toFixed(2) + 'K';
        return v.toFixed(0);
    }

    function fmtPct(n) {
        if (n == null || Number.isNaN(Number(n))) return '—';
        const v = Number(n);
        const ratio = Math.abs(v) > 1 ? v / 100 : v;
        return (ratio * 100).toFixed(1) + '%';
    }

    function execTone(n) {
        if (n == null || Number.isNaN(Number(n))) return 'muted';
        const v = Number(n);
        const pct = Math.abs(v) > 1 ? v : v * 100;
        if (pct >= 90) return 'high';
        if (pct >= 70) return 'mid';
        return 'low';
    }

    function formatDisplayDateTime(value) {
        if (!value) return '';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function boot(mountEl, cfg) {
        if (!mountEl || !cfg || !window.Vue || !window.Vuetify) {
            if (window.ApmVuetifyPage) window.ApmVuetifyPage.destroy(MOUNT_ID);
            appInstance = null;
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        appInstance = null;
        mountEl.innerHTML = '';

        const { createApp, ref, computed, onMounted, watch, nextTick } = window.Vue;
        const { createVuetify } = window.Vuetify;
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
        });
        const financePortalUrl = String(cfg.financePortalUrl || '').trim();

        appInstance = createApp({
            setup() {
                const loading = ref(true);
                const error = ref(null);
                const data = ref(null);
                const financeSyncedAt = ref(null);
                const filterDivisionId = ref('');
                const filterFundCenter = ref('');
                const filterFund = ref('');
                const expanded = ref({});
                const drill = ref({ open: false, title: '', mode: 'centers', rows: [], loading: false });
                const pieEl = ref(null);
                const colEl = ref(null);

                const kpis = computed(() => data.value?.kpis || null);
                const divisions = computed(() => data.value?.divisions || []);
                const budgetCodes = computed(() => data.value?.budget_codes || []);
                const catalog = computed(() => data.value?.filter_catalog || {
                    divisions: data.value?.filter_options || [],
                    fund_centers: [],
                    funds: [],
                });

                const divisionItems = computed(() => {
                    const opts = catalog.value.divisions || [];
                    // No "All …" row — empty/cleared value means all; placeholder stays searchable.
                    return opts.map((o) => ({
                        title: o.short_name ? `${o.short_name} — ${o.name}` : o.name,
                        value: String(o.id),
                    }));
                });

                const fundCenterItems = computed(() => {
                    const divId = filterDivisionId.value === '' || filterDivisionId.value == null
                        ? null
                        : Number(filterDivisionId.value);
                    const centers = (catalog.value.fund_centers || []).filter(
                        (fc) => divId == null || fc.division_id === divId,
                    );
                    return centers.map((fc) => ({ title: fc.code, value: fc.code }));
                });

                const fundItems = computed(() => {
                    const divId = filterDivisionId.value === '' || filterDivisionId.value == null
                        ? null
                        : Number(filterDivisionId.value);
                    const center = String(filterFundCenter.value || '').trim().toUpperCase();
                    const funds = (catalog.value.funds || []).filter((f) => {
                        if (divId != null && !(f.division_ids || []).includes(divId)) return false;
                        if (center && !(f.fund_centers || []).includes(center)) return false;
                        return true;
                    });
                    return funds.map((f) => ({ title: f.code, value: f.code }));
                });

                const fyLabel = computed(() =>
                    data.value?.financial_year?.label || (data.value ? `FY ${data.value.budget_year}` : ''),
                );

                const scopeLabel = computed(() => {
                    if (!data.value) return '';
                    if (data.value.can_view_all) return 'All divisions';
                    if (data.value.scope === 'directorate') return 'Your directorate';
                    if (data.value.scope === 'division') return 'Your division';
                    return 'No division mapped';
                });

                const lastSyncedLabel = computed(() =>
                    formatDisplayDateTime(
                        financeSyncedAt.value
                            || data.value?.finance_synced_at
                            || null,
                    ),
                );

                const showDivisionCharts = computed(
                    () => !!(data.value?.show_org_charts || data.value?.show_directorate_charts),
                );

                const showBudgetCodeCharts = computed(
                    () => !showDivisionCharts.value && !!(data.value?.show_budget_code_charts),
                );

                const showAnyCharts = computed(
                    () => showDivisionCharts.value || showBudgetCodeCharts.value,
                );

                const emptyReason = computed(() => {
                    if (!kpis.value || (kpis.value.division_count || 0) > 0) return null;
                    if (!data.value?.sap_import) {
                        return 'Finance has no latest SAP import for this year yet. Import a SAP dump in the Finance portal (SAP import), then refresh this page.';
                    }
                    if (data.value?.can_view_all) {
                        return 'APM could not load org-wide budget rows from Finance (import exists, but the API returned 0 divisions). Click Refresh, or run: php artisan finance:warm-budget-execution --force. If Finance SPA shows data, the APM→Finance warm token/session was likely empty — redeploy the latest Finance/APM auth fix.';
                    }
                    return 'No SAP fund centers are mapped for your division / directorate scope yet.';
                });

                function syncChildFilters() {
                    filterFundCenter.value = String(filterFundCenter.value || '').trim();
                    filterFund.value = String(filterFund.value || '').trim();
                    const centers = fundCenterItems.value.map((i) => String(i.value)).filter(Boolean);
                    if (filterFundCenter.value && !centers.includes(filterFundCenter.value)) {
                        filterFundCenter.value = '';
                    }
                    const funds = fundItems.value.map((i) => String(i.value)).filter(Boolean);
                    if (filterFund.value && !funds.includes(filterFund.value)) {
                        filterFund.value = '';
                    }
                }

                async function load() {
                    loading.value = true;
                    error.value = null;
                    try {
                        syncChildFilters();
                        const params = new URLSearchParams();
                        if (filterDivisionId.value) params.set('division_id', filterDivisionId.value);
                        if (filterFundCenter.value) params.set('fund_center', filterFundCenter.value);
                        if (filterFund.value) params.set('fund', filterFund.value);
                        const res = await fetch(cfg.routes.data + (params.toString() ? '?' + params : ''), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Failed to load budget execution.');
                        }
                        data.value = json.data || null;
                        financeSyncedAt.value = json.finance_synced_at
                            || json.data?.finance_synced_at
                            || null;
                        if (data.value?.filter_division_id) {
                            filterDivisionId.value = String(data.value.filter_division_id);
                        }
                        if (data.value?.filter_fund_center) {
                            filterFundCenter.value = data.value.filter_fund_center;
                        }
                        if (data.value?.filter_fund) {
                            filterFund.value = data.value.filter_fund;
                        }
                        syncChildFilters();
                    } catch (e) {
                        error.value = e.message || 'Failed to load';
                        data.value = null;
                        financeSyncedAt.value = null;
                    } finally {
                        loading.value = false;
                    }
                    await nextTick();
                    renderCharts();
                }

                function onDivisionChange() {
                    filterFundCenter.value = '';
                    filterFund.value = '';
                    void load();
                }

                function onFundCenterChange() {
                    syncChildFilters();
                    void load();
                }

                function onFundChange() {
                    filterFund.value = String(filterFund.value || '').trim();
                    void load();
                }

                function clearFilters() {
                    filterDivisionId.value = '';
                    filterFundCenter.value = '';
                    filterFund.value = '';
                    void load();
                }

                function toggleExpand(id) {
                    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
                }

                function openDivisionDrill(div) {
                    drill.value = {
                        open: true,
                        title: `${div.division_short_name} — ${div.division_name}`,
                        mode: 'centers',
                        rows: div.fund_centers || [],
                        loading: false,
                    };
                    expanded.value = { ...expanded.value, [div.division_id]: true };
                }

                async function openFundCenterExpenses(fc) {
                    const code = String(fc.fund_center || '').trim();
                    if (!code) return;
                    drill.value = { open: true, title: `Expenses — ${code}`, mode: 'expenses', rows: [], loading: true };
                    try {
                        const url = String(cfg.routes.expenses).replace('__CODE__', encodeURIComponent(code));
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load expenses');
                        const expenses = json.data?.expenses || json.data || [];
                        drill.value = {
                            open: true,
                            title: `Expenses — ${code}`,
                            mode: 'expenses',
                            rows: Array.isArray(expenses) ? expenses : [],
                            loading: false,
                        };
                    } catch (e) {
                        drill.value = {
                            open: true,
                            title: `Expenses — ${code}`,
                            mode: 'expenses',
                            rows: [],
                            loading: false,
                        };
                        error.value = e.message || 'Failed to load expenses';
                    }
                }

                function usedAmount(row) {
                    return Number(((row.commitment || 0) + (row.actual_expenditure || 0)).toFixed(2));
                }

                function renderCharts() {
                    if (!window.Highcharts || !kpis.value || loading.value) return;
                    if (!pieEl.value || !colEl.value) return;

                    if (showDivisionCharts.value) {
                        const pieData = divisions.value
                            .map((d) => ({
                                name: d.division_name,
                                y: usedAmount(d),
                            }))
                            .filter((p) => p.y > 0)
                            .sort((a, b) => b.y - a.y)
                            .slice(0, 12);
                        Highcharts.chart(pieEl.value, {
                            chart: { type: 'pie' },
                            title: { text: data.value?.can_view_all ? 'Proportion of used by division' : 'Proportion of used' },
                            credits: { enabled: false },
                            series: [{ name: 'Used', data: pieData }],
                            colors: ['#c3a366', '#911c39', '#119a48', '#385cad', '#0b3b5c'],
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    dataLabels: { enabled: true, format: '{point.name}' },
                                    point: {
                                        events: {
                                            click: function () {
                                                const match = divisions.value.find((d) => d.division_name === this.name);
                                                if (match) openDivisionDrill(match);
                                            },
                                        },
                                    },
                                },
                            },
                        });

                        const top = divisions.value.slice().sort((a, b) => b.approved_budget - a.approved_budget).slice(0, 10);
                        Highcharts.chart(colEl.value, {
                            chart: { type: 'column' },
                            title: { text: data.value?.can_view_all ? 'Division usage' : 'Directorate division usage' },
                            credits: { enabled: false },
                            xAxis: { categories: top.map((d) => d.division_short_name || d.division_name) },
                            yAxis: { title: { text: 'Amount (USD)' } },
                            series: [
                                { name: 'Approved', data: top.map((d) => Number(d.approved_budget.toFixed(0))), color: '#119a48' },
                                {
                                    name: 'Used',
                                    data: top.map((d) => Number(usedAmount(d).toFixed(0))),
                                    color: '#c3a366',
                                },
                            ],
                        });
                        return;
                    }

                    if (showBudgetCodeCharts.value) {
                        const codes = budgetCodes.value.slice();
                        const pieData = codes
                            .map((c) => ({
                                name: c.budget_code || c.fund_center,
                                y: usedAmount(c),
                            }))
                            .filter((p) => p.y > 0)
                            .sort((a, b) => b.y - a.y)
                            .slice(0, 12);
                        Highcharts.chart(pieEl.value, {
                            chart: { type: 'pie' },
                            title: { text: 'Proportion of used by fund' },
                            credits: { enabled: false },
                            series: [{ name: 'Used', data: pieData }],
                            colors: ['#c3a366', '#911c39', '#119a48', '#385cad', '#0b3b5c'],
                            plotOptions: {
                                pie: {
                                    allowPointSelect: true,
                                    cursor: 'pointer',
                                    dataLabels: { enabled: true, format: '{point.name}' },
                                },
                            },
                        });

                        const top = codes.slice().sort((a, b) => b.approved_budget - a.approved_budget).slice(0, 10);
                        Highcharts.chart(colEl.value, {
                            chart: { type: 'column' },
                            title: { text: 'Fund usage' },
                            credits: { enabled: false },
                            xAxis: { categories: top.map((c) => c.budget_code || c.fund_center) },
                            yAxis: { title: { text: 'Amount (USD)' } },
                            series: [
                                { name: 'Approved', data: top.map((c) => Number(Number(c.approved_budget || 0).toFixed(0))), color: '#119a48' },
                                {
                                    name: 'Used',
                                    data: top.map((c) => Number(usedAmount(c).toFixed(0))),
                                    color: '#c3a366',
                                },
                            ],
                        });
                    }
                }

                onMounted(load);
                watch([showAnyCharts, divisions, budgetCodes, loading], () => {
                    if (!loading.value) nextTick(renderCharts);
                });

                return {
                    loading, error, data, kpis, divisions, budgetCodes,
                    filterDivisionId, filterFundCenter, filterFund,
                    divisionItems, fundCenterItems, fundItems,
                    fyLabel, scopeLabel, lastSyncedLabel,
                    showDivisionCharts, showBudgetCodeCharts, showAnyCharts,
                    financePortalUrl, expanded, drill,
                    pieEl, colEl, emptyReason,
                    fmtMoney, compactUsd, fmtPct, execTone,
                    onDivisionChange, onFundCenterChange, onFundChange, clearFilters, load,
                    toggleExpand, openDivisionDrill, openFundCenterExpenses,
                };
            },
            template: `
              <div class="be-vuetify-app">
                <v-app>
                  <v-main>
                    <v-card class="be-hero mb-4 pa-4" rounded="0" elevation="1">
                      <div class="d-flex flex-wrap justify-space-between align-start ga-3">
                        <div>
                          <h2 class="text-h5 font-weight-bold mb-1">
                            <v-icon icon="mdi-chart-box-outline" color="primary" class="me-1"></v-icon>
                            Budget execution
                          </h2>
                          <div class="text-body-2">
                            Intramural SAP budget execution (Finance API)
                            <v-chip v-if="fyLabel" size="small" class="ml-2" color="primary" variant="tonal">{{ fyLabel }}</v-chip>
                            <v-chip v-if="scopeLabel" size="small" class="ml-1" color="secondary" variant="tonal">{{ scopeLabel }}</v-chip>
                          </div>
                          <p v-if="lastSyncedLabel" class="be-asat">
                            Last synced with Finance <strong>{{ lastSyncedLabel }}</strong>
                          </p>
                        </div>
                        <div class="d-flex flex-wrap align-center ga-2">
                          <v-btn
                            v-if="financePortalUrl"
                            color="primary"
                            variant="outlined"
                            :href="financePortalUrl"
                            target="_blank"
                            rel="noopener"
                          >
                            <v-icon start>mdi-open-in-new</v-icon>
                            Finance portal
                          </v-btn>
                          <v-btn color="primary" variant="flat" :disabled="loading" @click="load">
                            <v-icon start>mdi-refresh</v-icon> Refresh
                          </v-btn>
                        </div>
                      </div>
                    </v-card>

                    <v-row class="mb-3" dense>
                      <v-col cols="12" md="3">
                        <v-autocomplete v-model="filterDivisionId" :items="divisionItems" item-title="title" item-value="value"
                          label="Division" placeholder="Division" hide-details clearable auto-select-first
                          variant="outlined" density="comfortable" @update:model-value="onDivisionChange" />
                      </v-col>
                      <v-col cols="12" md="3">
                        <v-autocomplete v-model="filterFundCenter" :items="fundCenterItems" item-title="title" item-value="value"
                          label="Fund center" placeholder="Fund center" hide-details clearable auto-select-first
                          variant="outlined" density="comfortable" @update:model-value="onFundCenterChange" />
                      </v-col>
                      <v-col cols="12" md="3">
                        <v-autocomplete v-model="filterFund" :items="fundItems" item-title="title" item-value="value"
                          label="Fund" placeholder="Fund" hide-details clearable auto-select-first
                          variant="outlined" density="comfortable" @update:model-value="onFundChange" />
                      </v-col>
                      <v-col cols="12" md="3" class="d-flex align-center ga-2">
                        <v-btn color="success" @click="load">Apply</v-btn>
                        <v-btn v-if="filterDivisionId || filterFundCenter || filterFund" variant="outlined" @click="clearFilters">Clear</v-btn>
                      </v-col>
                    </v-row>

                    <div v-if="loading" class="text-center py-8 text-medium-emphasis">
                      <v-progress-circular indeterminate color="primary" />
                      <p class="mt-2 mb-0">Loading from Finance…</p>
                    </div>
                    <v-alert v-else-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

                    <template v-else-if="kpis">
                      <v-alert v-if="emptyReason" type="warning" variant="tonal" class="mb-4">{{ emptyReason }}</v-alert>
                      <v-row class="mb-4" dense>
                        <v-col cols="12" md="4">
                          <v-card class="be-kpi be-kpi--green pa-4" rounded="0">
                            <div class="be-kpi-body">
                              <span>Approved budget</span>
                              <strong>{{ compactUsd(kpis.approved_budget) }}</strong>
                              <small>{{ fmtMoney(kpis.approved_budget) }}</small>
                            </div>
                            <span class="be-kpi-icon" aria-hidden="true"><i class="fa-solid fa-cart-shopping"></i></span>
                          </v-card>
                        </v-col>
                        <v-col cols="12" md="4">
                          <v-card class="be-kpi be-kpi--gold pa-4" rounded="0">
                            <div class="be-kpi-body">
                              <span>Used budget</span>
                              <strong>{{ compactUsd(kpis.used_budget) }}</strong>
                              <small>{{ fmtMoney(kpis.used_budget) }}</small>
                            </div>
                            <span class="be-kpi-icon" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
                          </v-card>
                        </v-col>
                        <v-col cols="12" md="4">
                          <v-card class="be-kpi be-kpi--maroon pa-4" rounded="0">
                            <div class="be-kpi-body">
                              <span>Execution rate</span>
                              <strong>{{ fmtPct(kpis.execution_rate) }}</strong>
                              <small>
                                {{ kpis.division_count }} division(s) · {{ kpis.fund_center_count }} fund centers
                              </small>
                            </div>
                            <span class="be-kpi-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                          </v-card>
                        </v-col>
                      </v-row>

                      <v-row v-if="showAnyCharts" class="mb-4" dense>
                        <v-col cols="12" md="6"><v-card rounded="0" class="pa-2"><div ref="pieEl" class="be-chart"></div></v-card></v-col>
                        <v-col cols="12" md="6"><v-card rounded="0" class="pa-2"><div ref="colEl" class="be-chart"></div></v-card></v-col>
                      </v-row>

                      <v-card v-for="div in divisions" :key="div.division_id" class="be-div-card mb-3 pa-4" rounded="0">
                        <div class="d-flex justify-space-between align-start ga-3 mb-3">
                          <div>
                            <h3 class="text-subtitle-1 font-weight-bold mb-0">
                              <v-chip size="small" color="primary" variant="flat" class="mr-2">{{ div.division_short_name }}</v-chip>
                              {{ div.division_name }}
                            </h3>
                            <div class="text-caption text-medium-emphasis">{{ div.fund_center_count }} fund center(s)</div>
                          </div>
                          <v-btn size="small" color="primary" variant="outlined" @click="toggleExpand(div.division_id)">
                            <v-icon start size="16">{{ expanded[div.division_id] ? 'mdi-chevron-up' : 'mdi-chevron-down' }}</v-icon>
                            {{ expanded[div.division_id] ? 'Hide funds' : 'Show funds' }}
                          </v-btn>
                        </div>
                        <div class="be-metrics be-row-link" @click="openDivisionDrill(div)">
                          <article class="be-metric be-metric--green">
                            <span class="be-metric-icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                            <div>
                              <span>Released</span>
                              <strong>{{ fmtMoney(div.approved_budget) }}</strong>
                            </div>
                          </article>
                          <article class="be-metric be-metric--gold">
                            <span class="be-metric-icon" aria-hidden="true"><i class="fa-solid fa-file-signature"></i></span>
                            <div>
                              <span>Commitment</span>
                              <strong>{{ fmtMoney(div.commitment) }}</strong>
                            </div>
                          </article>
                          <article class="be-metric be-metric--blue">
                            <span class="be-metric-icon" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
                            <div>
                              <span>Actual</span>
                              <strong>{{ fmtMoney(div.actual_expenditure) }}</strong>
                            </div>
                          </article>
                          <article class="be-metric be-metric--slate">
                            <span class="be-metric-icon" aria-hidden="true"><i class="fa-solid fa-scale-balanced"></i></span>
                            <div>
                              <span>Balance</span>
                              <strong>{{ fmtMoney(div.budget_balance) }}</strong>
                            </div>
                          </article>
                          <article class="be-metric" :class="'be-metric--exec-' + execTone(div.execution_rate)">
                            <span class="be-metric-icon" aria-hidden="true"><i class="fa-solid fa-gauge-high"></i></span>
                            <div>
                              <span>Exec %</span>
                              <strong>{{ fmtPct(div.execution_rate) }}</strong>
                            </div>
                          </article>
                        </div>
                        <v-table v-if="expanded[div.division_id]" density="compact" class="mt-3 be-funds-table">
                          <thead>
                            <tr>
                              <th>Fund center</th><th>Fund</th><th>Released</th><th>Commitment</th><th>Actual</th><th>Balance</th><th>Exec %</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="fc in (div.fund_centers || [])" :key="fc.fund_center" class="be-row-link" @click="openFundCenterExpenses(fc)">
                              <td><code>{{ fc.fund_center }}</code></td>
                              <td><span class="be-fund">{{ fc.budget_code || '—' }}</span></td>
                              <td>{{ fmtMoney(fc.approved_budget) }}</td>
                              <td>{{ fmtMoney(fc.commitment) }}</td>
                              <td>{{ fmtMoney(fc.actual_expenditure) }}</td>
                              <td>{{ fmtMoney(fc.budget_balance) }}</td>
                              <td>
                                <span class="be-exec-pill" :class="'be-exec-pill--' + execTone(fc.execution_rate)">
                                  {{ fmtPct(fc.execution_rate) }}
                                </span>
                              </td>
                            </tr>
                          </tbody>
                        </v-table>
                      </v-card>

                      <p v-if="!divisions.length && !emptyReason" class="text-medium-emphasis">No SAP fund centers mapped for your scope yet.</p>
                    </template>

                    <v-dialog v-model="drill.open" max-width="960" scrollable>
                      <v-card>
                        <v-card-title class="d-flex justify-space-between align-center">
                          <span>{{ drill.title }}</span>
                          <v-btn icon variant="text" @click="drill.open = false"><v-icon>mdi-close</v-icon></v-btn>
                        </v-card-title>
                        <v-card-text>
                          <p v-if="drill.loading" class="text-medium-emphasis">Loading expenses…</p>
                          <p v-else-if="!drill.rows.length" class="text-medium-emphasis">
                            {{ drill.mode === 'expenses' ? 'No expenses for this fund center.' : 'No fund centers.' }}
                          </p>
                          <v-table v-else density="compact">
                            <thead>
                              <tr v-if="drill.mode === 'expenses'">
                                <th>Fund</th><th>GL account</th><th>Description</th><th>Commitment</th><th>Actual</th>
                              </tr>
                              <tr v-else>
                                <th>Fund center</th><th>Fund</th><th>Released</th><th>Commitment</th><th>Actual</th><th>Balance</th><th>Exec %</th>
                              </tr>
                            </thead>
                            <tbody>
                              <template v-if="drill.mode === 'expenses'">
                                <tr v-for="(ex, idx) in drill.rows" :key="idx">
                                  <td><span class="be-fund">{{ ex.budget_code || '—' }}</span></td>
                                  <td><code>{{ ex.gl_account }}</code></td>
                                  <td>{{ ex.gl_description || '—' }}</td>
                                  <td>{{ fmtMoney(ex.commitment) }}</td>
                                  <td>{{ fmtMoney(ex.actual_expenditure) }}</td>
                                </tr>
                              </template>
                              <template v-else>
                                <tr v-for="(fc, idx) in drill.rows" :key="idx" class="be-row-link" @click="openFundCenterExpenses(fc)">
                                  <td><code>{{ fc.fund_center }}</code></td>
                                  <td><span class="be-fund">{{ fc.budget_code || '—' }}</span></td>
                                  <td>{{ fmtMoney(fc.approved_budget) }}</td>
                                  <td>{{ fmtMoney(fc.commitment) }}</td>
                                  <td>{{ fmtMoney(fc.actual_expenditure) }}</td>
                                  <td>{{ fmtMoney(fc.budget_balance) }}</td>
                                  <td>{{ fmtPct(fc.execution_rate) }}</td>
                                </tr>
                              </template>
                            </tbody>
                          </v-table>
                        </v-card-text>
                      </v-card>
                    </v-dialog>
                  </v-main>
                </v-app>
              </div>
            `,
        });

        appInstance.use(vuetify);
        appInstance.mount(mountEl);
        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, boot);
    }
})();
