/**
 * Quarterly travel matrices index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'matrices-index-app';

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);

        return {
            tab: pick('tab', cfg.defaults?.tab || 'myDivision'),
            year: pick('year', String(cfg.defaults?.year ?? cfg.currentYear ?? '')),
            quarter: pick('quarter', cfg.defaults?.quarter ?? ''),
            division: pick('division', cfg.defaults?.division ?? ''),
            focal_person: pick('focal_person', cfg.defaults?.focal_person ?? ''),
            status: pick('status', cfg.defaults?.status ?? 'active'),
            page: Math.max(1, parseInt(pick('page', '1'), 10) || 1),
        };
    }

    function statusColor(status) {
        const s = String(status || '').toLowerCase();
        if (s === 'approved') return 'success';
        if (s === 'pending') return 'warning';
        if (s === 'rejected') return 'error';
        if (s === 'returned') return 'info';
        if (s === 'archived') return 'secondary';
        return 'secondary';
    }

    function formatMoney(value) {
        const n = Number(value) || 0;
        return '$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function syncUrl(filters, tab, page) {
        const current = new URL(window.location.href);
        ['year', 'quarter', 'division', 'focal_person', 'status', 'tab', 'page', 'my_division_page', 'all_matrices_page'].forEach((k) => {
            current.searchParams.delete(k);
        });
        if (filters.year) current.searchParams.set('year', filters.year);
        if (filters.quarter) current.searchParams.set('quarter', filters.quarter);
        if (filters.division) current.searchParams.set('division', filters.division);
        if (filters.focal_person) current.searchParams.set('focal_person', filters.focal_person);
        if (filters.status) current.searchParams.set('status', filters.status);
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));
        window.history.replaceState({}, '', current.toString());
    }

    function bootMatricesIndex(mountEl, cfg) {
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

        const initial = readInitialState(cfg);

        const app = createApp({
            setup() {
                const activeTab = ref(initial.tab);
                const filters = ref({
                    year: initial.year,
                    quarter: initial.quarter,
                    division: initial.division,
                    focal_person: initial.focal_person,
                    status: initial.status,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 24);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_division: cfg.counts?.my_division ?? 0,
                    all: cfg.counts?.all ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });

                const kraDialog = ref({ open: false, title: '', items: [] });
                const activitiesDialog = ref({ open: false, title: '', items: [] });

                // PRA create-matrix wizard
                const praWizard = ref({
                    open: false,
                    step: 1,
                    year: String(cfg.currentYear || new Date().getFullYear()),
                    quarter: cfg.currentQuarter || 'Q1',
                    quarterFilter: cfg.currentQuarter || 'Q1',
                    division_id: cfg.userDivisionId || '',
                    loading: false,
                    refreshing: false,
                    creating: false,
                    search: '',
                    activities: [],
                    selected: [],
                    meta: null,
                    allowExisting: false,
                });

                const praHeaders = [
                    { title: 'Code', key: 'code', width: 110 },
                    { title: 'Activity', key: 'title', minWidth: 200 },
                    { title: 'Outcome area', key: 'outcome_area', minWidth: 140 },
                    { title: 'Budget', key: 'budget', width: 130, align: 'end' },
                    { title: 'In APM', key: 'existing', width: 160 },
                    { title: 'Dates', key: 'dates', width: 130 },
                ];

                const praQuarterFilterOptions = [
                    { title: 'All quarters', value: '' },
                    { title: 'Q1', value: 'Q1' },
                    { title: 'Q2', value: 'Q2' },
                    { title: 'Q3', value: 'Q3' },
                    { title: 'Q4', value: 'Q4' },
                ];

                const praFiltered = computed(() => {
                    const q = String(praWizard.value.search || '').trim().toLowerCase();
                    const qf = String(praWizard.value.quarterFilter || '').toUpperCase();
                    let rows = praWizard.value.activities || [];
                    if (qf) {
                        rows = rows.filter((r) => {
                            if (Object.prototype.hasOwnProperty.call(r, 'matches_quarter')
                                && String(r.filter_quarter || '').toUpperCase() === qf) {
                                return !!r.matches_quarter;
                            }
                            return matchesPraQuarterClient(r, Number(praWizard.value.year), qf);
                        });
                    }
                    if (q) {
                        rows = rows.filter((r) =>
                            String(r.code || '').toLowerCase().includes(q)
                            || String(r.title || '').toLowerCase().includes(q)
                            || String(r.outcome_area || '').toLowerCase().includes(q)
                            || String(r.primary_funding_source || '').toLowerCase().includes(q)
                            || String(r.other_funding_source || '').toLowerCase().includes(q)
                        );
                    }
                    return rows;
                });

                const praSelectedRows = computed(() => {
                    const set = new Set((praWizard.value.selected || []).map(Number));
                    return (praWizard.value.activities || []).filter((r) => set.has(Number(r.pra_activity_id)));
                });

                const praSelectedExistingCount = computed(() =>
                    praSelectedRows.value.filter((r) => r.already_exists).length
                );

                const praSelectedBudget = computed(() => {
                    let quarter = 0;
                    let main = 0;
                    let otherYears = 0;
                    const codes = [];
                    for (const row of praSelectedRows.value) {
                        quarter += praQuarterBudget(row);
                        main += Number(row.budget_main ?? 0) || 0;
                        otherYears += (Number(row.budget_2027 ?? 0) || 0)
                            + (Number(row.budget_2028 ?? 0) || 0);
                        if (row.code) codes.push(String(row.code));
                    }
                    return { quarter, main, otherYears, codes, count: praSelectedRows.value.length };
                });

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Year', key: 'year', sortable: false, width: 80 },
                    { title: 'Quarter', key: 'quarter', sortable: false, width: 90 },
                    { title: 'Division / focal person', key: 'division_name', sortable: false, minWidth: 180 },
                    { title: 'KRAs', key: 'kra_count', sortable: false, width: 110, align: 'center' },
                    { title: 'Activities', key: 'activity_count', sortable: false, width: 120, align: 'center' },
                    { title: 'Level', key: 'approval_level', sortable: false, width: 80, align: 'center' },
                    { title: 'Status', key: 'overall_status', sortable: false, minWidth: 160 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 180 },
                ];

                const activityDialogHeaders = [
                    { title: 'Activity title', key: 'title', minWidth: 260 },
                    { title: 'Participants', key: 'participants', width: 110, align: 'center' },
                    { title: 'Budget', key: 'budget', width: 120, align: 'end' },
                ];

                const showingRange = computed(() => {
                    if (pagination.value.total === 0) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                const showMyDivisionTab = computed(() => counts.value.my_division > 0 || activeTab.value === 'myDivision');
                const exportUrl = computed(() => (
                    activeTab.value === 'allMatrices' ? cfg.routes.exportCsv : cfg.routes.exportDivisionCsv
                ));

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function openKraDialog(item) {
                    kraDialog.value = {
                        open: true,
                        title: `Key result areas — ${item.year} ${item.quarter}`,
                        items: item.kras || [],
                    };
                }

                function openActivitiesDialog(item) {
                    activitiesDialog.value = {
                        open: true,
                        title: `Activities — ${item.year} ${item.quarter}`,
                        items: (item.activities || []).map((a) => ({
                            ...a,
                            budget_display: formatMoney(a.budget),
                        })),
                    };
                }

                function openPraWizard() {
                    const qOpts = cfg.createQuarterOptions || [];
                    const firstQ = qOpts[0]?.value || cfg.currentQuarter || 'Q1';
                    const praYear = cfg.praFiscalYear || cfg.createYearOptions?.[0]?.value || cfg.currentYear;
                    praWizard.value = {
                        open: true,
                        step: 1,
                        year: String(praYear || new Date().getFullYear()),
                        quarter: firstQ,
                        quarterFilter: firstQ,
                        division_id: cfg.userDivisionId || '',
                        loading: false,
                        refreshing: false,
                        creating: false,
                        search: '',
                        activities: [],
                        selected: [],
                        meta: null,
                        allowExisting: false,
                    };
                }

                function matchesPraQuarterClient(row, year, quarter) {
                    const q = String(quarter || '').toUpperCase();
                    if (!q) return true;
                    const startMonth = { Q1: 1, Q2: 4, Q3: 7, Q4: 10 }[q] || 1;
                    const start = new Date(year, startMonth - 1, 1);
                    const end = new Date(year, startMonth + 2, 0, 23, 59, 59);
                    const s = row.start_date ? new Date(row.start_date) : null;
                    const e = row.end_date ? new Date(row.end_date) : null;
                    if (s || e) {
                        const actStart = s || new Date(year, 0, 1);
                        const actEnd = e || new Date(year, 11, 31);
                        return actStart <= end && actEnd >= start;
                    }
                    const budget = Number(row[`${q.toLowerCase()}_budget`] ?? 0) || 0;
                    if (budget > 0) return true;
                    const any = ['q1', 'q2', 'q3', 'q4'].reduce((sum, k) => sum + (Number(row[`${k}_budget`] ?? 0) || 0), 0);
                    return any <= 0;
                }

                function praRemoveSelected(id) {
                    praWizard.value.selected = (praWizard.value.selected || [])
                        .map(Number)
                        .filter((x) => x !== Number(id));
                }

                function praFormatDates(row) {
                    const s = row.start_date ? String(row.start_date).slice(0, 10) : '';
                    const e = row.end_date ? String(row.end_date).slice(0, 10) : '';
                    if (!s && !e) return '—';
                    return `${s || '?'} → ${e || '?'}`;
                }

                function praQuarterBudget(row) {
                    const q = String(praWizard.value.quarter || 'Q1').toLowerCase();
                    return Number(row?.[`${q}_budget`] ?? 0) || 0;
                }

                function praOtherBudget(row) {
                    return (Number(row?.budget_2027 ?? 0) || 0)
                        + (Number(row?.budget_2028 ?? 0) || 0);
                }

                function praFormatBudget(row) {
                    const qAmt = praQuarterBudget(row);
                    const main = Number(row?.budget_main ?? 0) || 0;
                    const other = praOtherBudget(row);
                    const parts = [];
                    if (qAmt > 0) parts.push(formatMoney(qAmt));
                    if (main > 0 && main !== qAmt) parts.push(`total ${formatMoney(main)}`);
                    if (other > 0) parts.push(`+${formatMoney(other)} other yrs`);
                    if (!parts.length && main > 0) return formatMoney(main);
                    return parts.length ? parts.join(' · ') : '—';
                }

                function praFormatFunding(row) {
                    const primary = String(row?.primary_funding_source || '').trim();
                    const other = String(row?.other_funding_source || '').trim();
                    if (primary && other && other.toLowerCase() !== primary.toLowerCase()) {
                        return `${primary} + ${other}`;
                    }
                    return primary || other || '';
                }

                function praFormatSelectedBudget() {
                    const b = praSelectedBudget.value;
                    if (!b.count) return '';
                    const parts = [];
                    if (b.quarter > 0) {
                        parts.push(`${praWizard.value.quarter || 'Q'} ${formatMoney(b.quarter)}`);
                    }
                    if (b.main > 0 && b.main !== b.quarter) {
                        parts.push(`total ${formatMoney(b.main)}`);
                    }
                    if (b.otherYears > 0) {
                        parts.push(`other yrs ${formatMoney(b.otherYears)}`);
                    }
                    if (!parts.length) return 'no budget amounts';
                    return parts.join(' · ');
                }

                async function loadPraActivities(refresh = false) {
                    if (!cfg.routes?.praActivities) {
                        notify('PRA activities route is not configured.');
                        return;
                    }
                    praWizard.value.loading = true;
                    praWizard.value.refreshing = !!refresh;
                    try {
                        const params = new URLSearchParams({
                            year: String(praWizard.value.year),
                            quarter: String(praWizard.value.quarter),
                        });
                        if (praWizard.value.division_id) {
                            params.set('division_id', String(praWizard.value.division_id));
                        }
                        if (refresh) params.set('refresh', '1');
                        // No AbortSignal — PRA full refresh must not be cancelled/timed out by the client.
                        const res = await fetch(`${cfg.routes.praActivities}?${params}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not load PRA activities.');
                        }
                        praWizard.value.activities = json.data?.activities || [];
                        praWizard.value.meta = json.data?.meta || null;
                        // Keep selections that still exist
                        const valid = new Set(praWizard.value.activities.map((a) => Number(a.pra_activity_id)));
                        praWizard.value.selected = praWizard.value.selected
                            .map(Number)
                            .filter((id) => valid.has(id));
                        if (!praWizard.value.activities.length) {
                            notify('No PRA activities found for this division/quarter. Check PRA division mapping.', 'warning');
                        } else if (refresh) {
                            notify('PRA activities refreshed.', 'success');
                        }
                    } catch (e) {
                        praWizard.value.activities = [];
                        notify(e.message || 'Could not load PRA activities.');
                    } finally {
                        praWizard.value.loading = false;
                        praWizard.value.refreshing = false;
                    }
                }

                async function praGoSelectStep() {
                    if (!praWizard.value.year || !praWizard.value.quarter) {
                        notify('Select year and quarter first.');
                        return;
                    }
                    const qOpt = (cfg.createQuarterOptions || []).find((o) => o.value === praWizard.value.quarter);
                    if (qOpt?.year) {
                        praWizard.value.year = String(qOpt.year);
                    }
                    praWizard.value.quarterFilter = String(praWizard.value.quarter || '');
                    praWizard.value.allowExisting = false;
                    praWizard.value.step = 2;
                    await loadPraActivities(false);
                }

                function praGoReviewStep() {
                    if (!praWizard.value.selected.length) {
                        notify('Select at least one PRA activity.');
                        return;
                    }
                    if (praSelectedExistingCount.value > 0 && !praWizard.value.allowExisting) {
                        notify(
                            `${praSelectedExistingCount.value} selected already exist in APM. Review links, then tick “Allow already-linked” to continue.`,
                            'warning'
                        );
                    }
                    praWizard.value.step = 3;
                }

                async function praCreateMatrix() {
                    if (!praWizard.value.selected.length) {
                        notify('Select at least one PRA activity.');
                        return;
                    }
                    if (praSelectedExistingCount.value > 0 && !praWizard.value.allowExisting) {
                        notify('Some selected PRA activities already exist. Confirm the warning checkbox first.', 'warning');
                        return;
                    }
                    praWizard.value.creating = true;
                    try {
                        const res = await fetch(cfg.routes.praCreate, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                year: Number(praWizard.value.year),
                                quarter: praWizard.value.quarter,
                                division_id: praWizard.value.division_id || undefined,
                                pra_activity_ids: praWizard.value.selected.map(Number),
                                allow_existing: !!praWizard.value.allowExisting,
                            }),
                        });
                        const json = await res.json();
                        if (res.status === 409 && json.data?.requires_confirmation) {
                            praWizard.value.allowExisting = true;
                            notify(json.message || 'Confirm to continue with already-linked activities.', 'warning');
                            return;
                        }
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not create matrix.');
                        }
                        notify(json.message || 'Matrix created.', 'success');
                        praWizard.value.open = false;
                        if (json.data?.redirect_url) {
                            window.location.href = json.data.redirect_url;
                            return;
                        }
                        loadItems();
                    } catch (e) {
                        notify(e.message || 'Could not create matrix.');
                    } finally {
                        praWizard.value.creating = false;
                    }
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            tab: activeTab.value,
                            year: filters.value.year,
                            quarter: filters.value.quarter,
                            division: filters.value.division,
                            focal_person: filters.value.focal_person,
                            status: filters.value.status,
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                        });

                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'Could not load matrices.');
                        }

                        items.value = data.data || [];
                        pagination.value = {
                            total: data.pagination?.total ?? 0,
                            from: data.pagination?.from ?? 0,
                            to: data.pagination?.to ?? 0,
                            last_page: data.pagination?.last_page ?? 1,
                        };
                        if (data.counts) {
                            counts.value = {
                                my_division: data.counts.my_division ?? counts.value.my_division,
                                all: data.counts.all ?? counts.value.all,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load matrices.');
                    } finally {
                        loading.value = false;
                    }
                }

                function applyFilters() {
                    page.value = 1;
                    loadItems();
                }

                watch(activeTab, () => {
                    page.value = 1;
                    loadItems();
                });

                watch(page, () => loadItems());
                watch(itemsPerPage, () => {
                    page.value = 1;
                    loadItems();
                });

                loadItems();

                return {
                    cfg,
                    activeTab,
                    filters,
                    page,
                    itemsPerPage,
                    items,
                    pagination,
                    counts,
                    loading,
                    snackbar,
                    kraDialog,
                    activitiesDialog,
                    headers,
                    activityDialogHeaders,
                    showingRange,
                    showMyDivisionTab,
                    exportUrl,
                    statusColor,
                    formatMoney,
                    applyFilters,
                    openKraDialog,
                    openActivitiesDialog,
                    praWizard,
                    praHeaders,
                    praQuarterFilterOptions,
                    praFiltered,
                    praSelectedRows,
                    praSelectedBudget,
                    praSelectedExistingCount,
                    openPraWizard,
                    praRemoveSelected,
                    praFormatDates,
                    praFormatBudget,
                    praFormatFunding,
                    praFormatSelectedBudget,
                    loadPraActivities,
                    praGoSelectStep,
                    praGoReviewStep,
                    praCreateMatrix,
                };
            },
            template: `
<v-app class="mx-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4">
        <div class="d-flex align-center gap-2">
          <v-icon icon="mdi-grid" color="primary" />
          <span class="text-h6 font-weight-bold">Matrix details</span>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <v-btn
            v-if="cfg.isFocalPerson && cfg.praCreateEnabled !== false"
            color="success"
            variant="flat"
            prepend-icon="mdi-cloud-download"
            @click="openPraWizard"
          >
            Create matrix from PRA
          </v-btn>
          <v-btn
            v-if="cfg.isFocalPerson"
            color="success"
            variant="flat"
            :href="cfg.routes.create"
            prepend-icon="mdi-plus"
          >
            Create matrix
          </v-btn>
        </div>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.year" :items="cfg.yearOptions || []" item-title="title" item-value="value" label="Year" prepend-inner-icon="mdi-calendar" />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.quarter" :items="cfg.quarterOptions || []" item-title="title" item-value="value" label="Quarter" prepend-inner-icon="mdi-clock-outline" />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="3">
            <v-autocomplete v-model="filters.division" :items="cfg.divisionOptions || []" item-title="title" item-value="value" label="Division" prepend-inner-icon="mdi-office-building" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="3">
            <v-autocomplete v-model="filters.focal_person" :items="cfg.focalOptions || []" item-title="title" item-value="value" label="Focal person" prepend-inner-icon="mdi-account-tie" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.status" :items="cfg.statusOptions || []" item-title="title" item-value="value" label="Matrix status" prepend-inner-icon="mdi-filter-outline" />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">Filter</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-tabs v-model="activeTab" color="primary" grow class="apm-vuetify-tabs">
        <v-tab v-if="showMyDivisionTab" value="myDivision">
          <v-icon start icon="mdi-home" />
          My division
          <v-chip size="x-small" color="success" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllMatrices" value="allMatrices">
          <v-icon start icon="mdi-grid" />
          All matrices
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.all }}</v-chip>
        </v-tab>
      </v-tabs>

      <v-card-text class="pt-4">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-3">
          <div>
            <div class="text-subtitle-1 font-weight-bold">
              {{ activeTab === 'allMatrices' ? 'All matrices' : 'My division matrices' }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              Showing {{ showingRange }} of {{ pagination.total }}
            </div>
          </div>
          <v-btn variant="outlined" color="primary" size="small" :href="exportUrl" prepend-icon="mdi-download">
            Export to CSV
          </v-btn>
        </div>

        <v-data-table
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
          class="mx-matrix-table apm-list-table"
          density="comfortable"
          hover
        >
          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 pa-3">
              <div class="text-body-2 text-medium-emphasis">
                Page {{ page }} of {{ pagination.last_page || 1 }}
              </div>
              <v-pagination
                v-if="pagination.last_page > 1"
                v-model="page"
                :length="pagination.last_page"
                :total-visible="7"
                density="comfortable"
                rounded="lg"
              />
            </div>
          </template>

          <template #item.division_name="{ item }">
            <div class="font-weight-medium">{{ item.division_name }}</div>
            <div class="text-caption text-medium-emphasis">
              <v-icon icon="mdi-account" size="x-small" class="me-1" />
              {{ item.focal_person_name }}
            </div>
          </template>

          <template #item.kra_count="{ item }">
            <v-btn size="small" variant="outlined" color="info" @click="openKraDialog(item)">
              {{ item.kra_count }} area(s)
            </v-btn>
          </template>

          <template #item.activity_count="{ item }">
            <v-btn size="small" variant="outlined" color="primary" @click="openActivitiesDialog(item)">
              {{ item.activity_count }} activit{{ item.activity_count === 1 ? 'y' : 'ies' }}
            </v-btn>
          </template>

          <template #item.approval_level="{ item }">
            <v-chip size="small" color="info" variant="flat">{{ item.approval_level }}</v-chip>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="flat" class="text-uppercase mb-1">
              {{ item.overall_status }}
            </v-chip>
            <div v-if="item.workflow_role" class="text-caption font-weight-medium">
              {{ item.workflow_role }}
            </div>
            <div v-if="item.current_actor_name" class="text-caption text-medium-emphasis">
              {{ item.current_actor_name }}
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1 justify-end flex-wrap">
              <v-btn
                size="small"
                variant="outlined"
                color="info"
                prepend-icon="mdi-eye"
                :href="item.show_url"
              >
                Open
              </v-btn>
              <v-btn
                v-if="item.edit_url"
                size="small"
                variant="outlined"
                color="warning"
                prepend-icon="mdi-pencil"
                :href="item.edit_url"
              >
                Edit
              </v-btn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-grid-off" size="48" class="mb-2 opacity-50" />
              <div>No matrices found for the selected filters.</div>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="kraDialog.open" max-width="720" scrollable>
      <v-card>
        <v-card-title>{{ kraDialog.title }}</v-card-title>
        <v-card-text>
          <v-list v-if="kraDialog.items.length">
            <v-list-item v-for="(kra, idx) in kraDialog.items" :key="idx" :title="kra" prepend-icon="mdi-check-circle" />
          </v-list>
          <div v-else class="text-medium-emphasis">No key result areas defined.</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="kraDialog.open = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="activitiesDialog.open" max-width="900" scrollable>
      <v-card>
        <v-card-title>{{ activitiesDialog.title }}</v-card-title>
        <v-card-text>
          <v-data-table
            v-if="activitiesDialog.items.length"
            :headers="activityDialogHeaders"
            :items="activitiesDialog.items"
            density="compact"
            :items-per-page="-1"
            hide-default-footer
          >
            <template #item.budget="{ item }">{{ formatMoney(item.budget) }}</template>
          </v-data-table>
          <div v-else class="text-medium-emphasis">No activities defined.</div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="activitiesDialog.open = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="praWizard.open" max-width="1400" width="92vw" content-class="modal-xl pra-wizard-dialog" scrollable persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between flex-wrap gap-2">
          <span class="d-flex align-center gap-2">
            <v-icon icon="mdi-cloud-download" color="primary" />
            Create matrix from PRA
          </span>
          <v-chip size="small" variant="tonal" color="primary">Step {{ praWizard.step }} of 3</v-chip>
        </v-card-title>
        <v-progress-linear
          v-if="praWizard.refreshing || praWizard.loading"
          indeterminate
          color="primary"
          height="3"
        />
        <v-divider v-else />
        <v-card-text>
          <div v-if="praWizard.step === 1">
            <v-row dense>
              <v-col cols="12" md="4">
                <v-select
                  v-model="praWizard.year"
                  :items="cfg.createYearOptions || [{ title: String(cfg.currentYear), value: String(cfg.currentYear) }]"
                  item-title="title"
                  item-value="value"
                  label="Year"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="praWizard.quarter"
                  :items="cfg.createQuarterOptions || cfg.quarterOptions || []"
                  item-title="title"
                  item-value="value"
                  label="Quarter"
                />
              </v-col>
              <v-col v-if="cfg.isAdmin" cols="12" md="4">
                <v-autocomplete
                  v-model="praWizard.division_id"
                  :items="(cfg.divisionOptions || []).filter((d) => d.value)"
                  item-title="title"
                  item-value="value"
                  label="Division"
                />
              </v-col>
            </v-row>
          </div>

          <div v-else-if="praWizard.step === 2">
            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
              <div class="text-body-2 text-medium-emphasis">
                {{ praFiltered.length }} shown
                <span v-if="praWizard.meta?.count != null"> / {{ praWizard.meta.count }} loaded</span>
                <span v-if="praWizard.meta?.existing_count"> · {{ praWizard.meta.existing_count }} already in APM</span>
                <span v-if="praWizard.meta?.pra_codes?.length"> · PRA codes: {{ praWizard.meta.pra_codes.join(', ') }}</span>
              </div>
              <div class="d-flex align-center flex-wrap gap-2">
                <v-select
                  v-model="praWizard.quarterFilter"
                  :items="praQuarterFilterOptions"
                  item-title="title"
                  item-value="value"
                  label="Filter by quarter"
                  density="compact"
                  hide-details
                  variant="outlined"
                  style="min-width: 150px; max-width: 170px; height: 40px;"
                />
                <v-text-field
                  v-model="praWizard.search"
                  placeholder="Search…"
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  density="compact"
                  hide-details
                  single-line
                  variant="outlined"
                  style="min-width: 200px; max-width: 260px; height: 40px;"
                  class="pra-wizard-search"
                />
                <v-btn
                  variant="outlined"
                  density="comfortable"
                  height="40"
                  min-height="40"
                  :loading="praWizard.refreshing"
                  :disabled="praWizard.loading && !praWizard.refreshing"
                  prepend-icon="mdi-refresh"
                  @click="loadPraActivities(true)"
                >Refresh</v-btn>
              </div>
            </div>
            <v-alert
              v-if="praWizard.refreshing"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              Refreshing PRA activities — this can take a few minutes. Please wait…
            </v-alert>
            <v-data-table
              v-model="praWizard.selected"
              :headers="praHeaders"
              :items="praFiltered"
              :loading="praWizard.loading && !praWizard.refreshing"
              :items-per-page="25"
              show-select
              item-value="pra_activity_id"
              density="compact"
              class="border rounded"
            >
              <template #item.code="{ item }">
                <v-chip size="x-small" color="primary" variant="tonal">{{ item.code || item.pra_activity_id }}</v-chip>
              </template>
              <template #item.title="{ item }">
                <div class="text-wrap">{{ item.title }}</div>
                <div v-if="praFormatFunding(item)" class="text-caption text-medium-emphasis text-wrap">
                  {{ praFormatFunding(item) }}
                </div>
              </template>
              <template #item.outcome_area="{ item }">
                <div class="text-caption text-wrap">{{ item.outcome_area || '—' }}</div>
              </template>
              <template #item.budget="{ item }">
                <span class="text-caption text-no-wrap">{{ praFormatBudget(item) }}</span>
              </template>
              <template #item.existing="{ item }">
                <div v-if="item.already_exists" class="d-flex flex-column gap-1">
                  <v-chip size="x-small" color="warning" variant="tonal">Already exists</v-chip>
                  <a
                    v-for="ex in (item.existing || []).slice(0, 3)"
                    :key="ex.type + '-' + ex.id"
                    :href="ex.url"
                    target="_blank"
                    class="text-caption text-primary text-decoration-none"
                  >{{ ex.label }} ({{ ex.status }})</a>
                </div>
                <span v-else class="text-caption text-medium-emphasis">—</span>
              </template>
              <template #item.dates="{ item }">
                <span class="text-caption">{{ praFormatDates(item) }}</span>
              </template>
              <template #no-data>
                <v-alert type="warning" variant="tonal" class="ma-4">
                  No PRA activities match this filter. Try “All quarters” or refresh after mapping your division.
                </v-alert>
              </template>
            </v-data-table>
            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mt-2">
              <div class="text-body-2">
                <strong>{{ praSelectedBudget.count }}</strong> selected
                <span v-if="praSelectedBudget.count" class="text-medium-emphasis">
                  · combined {{ praFormatSelectedBudget() }}
                </span>
                <span v-if="praSelectedExistingCount" class="text-warning">
                  · {{ praSelectedExistingCount }} already in APM
                </span>
              </div>
              <v-btn
                v-if="praSelectedBudget.count"
                variant="text"
                size="small"
                color="error"
                @click="praWizard.selected = []"
              >Clear selection</v-btn>
            </div>
            <div
              v-if="praSelectedBudget.count && praSelectedBudget.codes.length"
              class="text-caption text-medium-emphasis mt-1 text-wrap"
            >
              Codes: {{ praSelectedBudget.codes.join(', ') }}
            </div>
          </div>

          <div v-else>
            <p class="mb-3">
              Review selected activities.
              <strong v-if="praSelectedBudget.count">
                Combined budget: {{ praFormatSelectedBudget() }}
              </strong>
            </p>
            <v-alert
              v-if="praSelectedExistingCount"
              type="warning"
              variant="tonal"
              density="compact"
              class="mb-3"
            >
              {{ praSelectedExistingCount }} selected PRA activit{{ praSelectedExistingCount === 1 ? 'y is' : 'ies are' }}
              already linked in APM (may be draft, pending, or not yet implemented). You can still import —
              confirm below.
            </v-alert>
            <v-checkbox
              v-if="praSelectedExistingCount"
              v-model="praWizard.allowExisting"
              density="compact"
              hide-details
              class="mb-3"
              label="Allow already-linked PRA activities (I understand they may duplicate work)"
            />
            <v-list lines="two" class="border rounded">
              <v-list-item v-for="row in praSelectedRows" :key="row.pra_activity_id">
                <v-list-item-title class="text-wrap">{{ row.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-wrap">
                  {{ row.code }} · {{ row.outcome_area || 'No outcome' }}
                  <span v-if="praFormatBudget(row) !== '—'"> · {{ praFormatBudget(row) }}</span>
                  <span v-if="praFormatFunding(row)"> · {{ praFormatFunding(row) }}</span>
                  <span v-if="row.already_exists" class="text-warning"> · already in APM</span>
                </v-list-item-subtitle>
                <template #append>
                  <v-btn icon="mdi-delete-outline" variant="text" color="error" size="small" @click="praRemoveSelected(row.pra_activity_id)" />
                </template>
              </v-list-item>
              <v-list-item v-if="!praSelectedRows.length">
                <v-list-item-title class="text-medium-emphasis">No activities selected.</v-list-item-title>
              </v-list-item>
            </v-list>
          </div>
        </v-card-text>
        <v-divider />
        <v-card-actions class="px-4 py-3">
          <v-btn variant="text" :disabled="praWizard.creating" @click="praWizard.open = false">Cancel</v-btn>
          <v-spacer />
          <v-btn v-if="praWizard.step > 1" variant="outlined" :disabled="praWizard.creating" @click="praWizard.step -= 1">Back</v-btn>
          <v-btn v-if="praWizard.step === 1" color="primary" @click="praGoSelectStep">Next: select activities</v-btn>
          <v-btn v-if="praWizard.step === 2" color="primary" :disabled="!praWizard.selected.length" @click="praGoReviewStep">Next: review</v-btn>
          <v-btn v-if="praWizard.step === 3" color="success" :loading="praWizard.creating" :disabled="!praWizard.selected.length" prepend-icon="mdi-check" @click="praCreateMatrix">
            Create matrix
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootMatricesIndex);
    }
})();
