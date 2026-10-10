/**
 * Special memos index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'special-memos-index-app';
    const memoList = window.ApmVuetifyMemoList || {};

    const FILTER_KEYS = [
        'year', 'division_id', 'staff_id', 'status',
        'document_number', 'search', 'fund_type_id', 'request_type_id',
    ];
    const SELECT_FILTER_KEYS = ['year', 'division_id', 'staff_id', 'status', 'fund_type_id', 'request_type_id'];
    const TEXT_FILTER_KEYS = ['search', 'document_number'];

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);
        const defaults = cfg.defaults || {};
        const statusFromUrl = url.has('status')
            ? (url.get('status') || '')
            : (url.has('overall_status') ? (url.get('overall_status') || '') : defaults.status ?? '');

        return {
            tab: pick('tab', defaults.tab || 'mySubmitted'),
            year: pick('year', String(defaults.year ?? cfg.currentYear ?? '')),
            division_id: pick('division_id', defaults.division_id ?? ''),
            staff_id: pick('staff_id', defaults.staff_id ?? ''),
            status: statusFromUrl,
            document_number: pick('document_number', defaults.document_number ?? ''),
            search: pick('search', defaults.search ?? ''),
            fund_type_id: pick('fund_type_id', defaults.fund_type_id ?? ''),
            request_type_id: pick('request_type_id', defaults.request_type_id ?? ''),
            page: Math.max(1, parseInt(pick('page', '1'), 10) || 1),
        };
    }

    function syncUrl(filters, tab, page) {
        const current = new URL(window.location.href);
        ['tab', 'page', 'fragment', 'overall_status', ...FILTER_KEYS].forEach((k) => current.searchParams.delete(k));

        FILTER_KEYS.forEach((key) => {
            if (filters[key]) current.searchParams.set(key, filters[key]);
        });
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));

        window.history.replaceState({}, '', current.toString());
    }

    function bootSpecialMemosIndex(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch, onMounted, onBeforeUnmount } = Vue;
        const vuetify = memoList.createVuetify ? memoList.createVuetify() : Vuetify.createVuetify();
        const statusColor = memoList.statusColor || (() => 'secondary');
        const submitHiddenForm = memoList.submitHiddenForm || (() => {});
        const buildExportUrl = memoList.buildExportUrl || (() => '');

        const initial = readInitialState(cfg);

        const app = createApp({
            setup() {
                const activeTab = ref(initial.tab);
                const filters = ref({
                    year: initial.year,
                    division_id: initial.division_id,
                    staff_id: initial.staff_id,
                    status: initial.status,
                    document_number: initial.document_number,
                    search: initial.search,
                    fund_type_id: initial.fund_type_id,
                    request_type_id: initial.request_type_id,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 20);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_submitted: cfg.counts?.my_submitted ?? 0,
                    my_division: cfg.counts?.my_division ?? 0,
                    shared_memos: cfg.counts?.shared_memos ?? 0,
                    all_memos: cfg.counts?.all_memos ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const csrf = cfg.csrf || '';
                const praPicker = ref({
                    open: false,
                    loading: false,
                    refreshing: false,
                    search: '',
                    quarterFilter: '',
                    year: String(cfg.praFiscalYear || filters.value.year || cfg.currentYear || ''),
                    activities: [],
                    selectedId: null,
                    meta: null,
                    allowExisting: false,
                });

                const showResponsibleColumn = computed(() => activeTab.value !== 'mySubmitted');

                const exportUrl = computed(() => {
                    if (activeTab.value === 'mySubmitted' && cfg.routes?.exportMySubmitted) {
                        return buildExportUrl(cfg.routes.exportMySubmitted, filters.value, ['request_type_id']);
                    }
                    if (activeTab.value === 'allMemos' && cfg.routes?.exportAll) {
                        return buildExportUrl(cfg.routes.exportAll, filters.value, ['request_type_id']);
                    }
                    if (activeTab.value === 'sharedMemos' && cfg.routes?.exportShared) {
                        return buildExportUrl(cfg.routes.exportShared, filters.value, ['request_type_id']);
                    }
                    return null;
                });

                const headers = computed(() => {
                    const base = [
                        { title: '#', key: 'row_num', sortable: false, width: 56 },
                        { title: 'Title', key: 'title', sortable: false, minWidth: 220 },
                        { title: 'Request type', key: 'request_type_name', sortable: false, minWidth: 130 },
                    ];
                    if (showResponsibleColumn.value) {
                        base.push({ title: 'Responsible', key: 'responsible_person_name', sortable: false, minWidth: 140 });
                    }
                    base.push(
                        { title: 'Division', key: 'division_name', sortable: false, minWidth: 130 },
                        { title: 'Fund type', key: 'fund_type_name', sortable: false, minWidth: 130 },
                        { title: 'Dates', key: 'date_range', sortable: false, width: 130 },
                        { title: 'Status', key: 'overall_status', sortable: false, minWidth: 140 },
                        { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 220 }
                    );
                    return base;
                });

                const showingRange = computed(() => {
                    if (pagination.value.total === 0) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
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

                const praFiltered = computed(() => {
                    const q = String(praPicker.value.search || '').trim().toLowerCase();
                    const qf = String(praPicker.value.quarterFilter || '').toUpperCase();
                    let rows = praPicker.value.activities || [];
                    if (qf) {
                        rows = rows.filter((r) => matchesPraQuarterClient(r, Number(praPicker.value.year), qf));
                    }
                    if (q) {
                        rows = rows.filter((r) =>
                            String(r.code || '').toLowerCase().includes(q)
                            || String(r.title || '').toLowerCase().includes(q)
                            || String(r.outcome_area || '').toLowerCase().includes(q)
                        );
                    }
                    return rows;
                });

                const praSelected = computed(() => {
                    const id = Number(praPicker.value.selectedId);
                    return (praPicker.value.activities || []).find((r) => Number(r.pra_activity_id) === id) || null;
                });

                async function openPraPicker() {
                    praPicker.value.open = true;
                    praPicker.value.selectedId = null;
                    praPicker.value.search = '';
                    praPicker.value.quarterFilter = '';
                    praPicker.value.allowExisting = false;
                    praPicker.value.year = String(cfg.praFiscalYear || filters.value.year || cfg.currentYear || '');
                    await loadPraActivities(false);
                }

                async function loadPraActivities(refresh) {
                    if (!cfg.routes?.praActivities) {
                        notify('PRA activities route is not configured.');
                        return;
                    }
                    praPicker.value.loading = true;
                    praPicker.value.refreshing = !!refresh;
                    try {
                        const params = new URLSearchParams({
                            year: String(praPicker.value.year || cfg.currentYear || ''),
                        });
                        if (cfg.userDivisionId) params.set('division_id', String(cfg.userDivisionId));
                        if (refresh) params.set('refresh', '1');
                        const res = await fetch(`${cfg.routes.praActivities}?${params}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) {
                            throw new Error(json.message || 'Could not load PRA activities.');
                        }
                        praPicker.value.activities = json.data?.activities || [];
                        praPicker.value.meta = json.data?.meta || null;
                    } catch (e) {
                        praPicker.value.activities = [];
                        notify(e.message || 'Could not load PRA activities.');
                    } finally {
                        praPicker.value.loading = false;
                        praPicker.value.refreshing = false;
                    }
                }

                function proceedPraPicker() {
                    const id = Number(praPicker.value.selectedId);
                    if (!id) {
                        notify('Select one PRA activity.', 'warning');
                        return;
                    }
                    if (praSelected.value?.already_exists && !praPicker.value.allowExisting) {
                        notify('This PRA activity already exists in APM. Confirm the warning to continue.', 'warning');
                        return;
                    }
                    const base = cfg.routes?.create;
                    if (!base) {
                        notify('Create route missing.');
                        return;
                    }
                    const url = new URL(base, window.location.origin);
                    url.searchParams.set('pra_activity_id', String(id));
                    url.searchParams.set('year', String(praPicker.value.year || ''));
                    if (praPicker.value.quarterFilter) {
                        url.searchParams.set('quarter', praPicker.value.quarterFilter);
                    }
                    window.location.href = url.toString();
                }

                function onHeaderPraClick(e) {
                    const btn = e.target.closest('#sm-create-from-pra-btn');
                    if (!btn) return;
                    e.preventDefault();
                    openPraPicker();
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            tab: activeTab.value,
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                        });
                        FILTER_KEYS.forEach((key) => {
                            if (filters.value[key]) params.set(key, filters.value[key]);
                        });

                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'Could not load special memos.');
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
                                my_submitted: data.counts.my_submitted ?? counts.value.my_submitted,
                                my_division: data.counts.my_division ?? counts.value.my_division,
                                shared_memos: data.counts.shared_memos ?? counts.value.shared_memos,
                                all_memos: data.counts.all_memos ?? counts.value.all_memos,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load special memos.');
                    } finally {
                        loading.value = false;
                    }
                }

                function applyFilters() {
                    page.value = 1;
                    loadItems();
                }

                function resetFilters() {
                    filters.value = {
                        year: String(cfg.currentYear ?? ''),
                        division_id: '',
                        staff_id: '',
                        status: '',
                        document_number: '',
                        search: '',
                        fund_type_id: '',
                        request_type_id: '',
                    };
                    page.value = 1;
                    loadItems();
                }

                function confirmDelete(item) {
                    if (!item.delete_url) return;
                    if (!window.confirm('Delete this special memo? This cannot be undone.')) return;
                    submitHiddenForm(item.delete_url, 'DELETE', csrf);
                }

                let textTimer = null;
                TEXT_FILTER_KEYS.forEach((key) => {
                    watch(() => filters.value[key], () => {
                        clearTimeout(textTimer);
                        textTimer = setTimeout(() => {
                            page.value = 1;
                            loadItems();
                        }, 400);
                    });
                });

                SELECT_FILTER_KEYS.forEach((key) => {
                    watch(() => filters.value[key], () => {
                        page.value = 1;
                        loadItems();
                    });
                });

                watch(activeTab, () => {
                    page.value = 1;
                    loadItems();
                });
                watch(page, () => loadItems());

                onMounted(() => {
                    document.addEventListener('click', onHeaderPraClick);
                    loadItems();
                });
                onBeforeUnmount(() => {
                    document.removeEventListener('click', onHeaderPraClick);
                });

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
                    headers,
                    showingRange,
                    exportUrl,
                    statusColor,
                    applyFilters,
                    resetFilters,
                    confirmDelete,
                    praPicker,
                    praFiltered,
                    praSelected,
                    openPraPicker,
                    loadPraActivities,
                    proceedPraPicker,
                };
            },
            template: `
<v-app class="smemo-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex align-center justify-space-between flex-wrap gap-2 py-4">
        <span class="d-flex align-center gap-2">
          <v-icon icon="mdi-file-star-outline" color="primary" />
          <span class="text-h6 font-weight-bold">Special memo management</span>
        </span>
        <v-btn v-if="cfg.praCreateEnabled !== false" color="success" variant="flat" prepend-icon="mdi-cloud-download" @click="openPraPicker">Create from PRA</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" lg="3">
            <v-text-field v-model="filters.search" label="Search title" prepend-inner-icon="mdi-magnify" placeholder="Enter memo title…" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-text-field v-model="filters.document_number" label="Document #" prepend-inner-icon="mdi-pound" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.year" :items="cfg.yearOptions || []" item-title="title" item-value="value" label="Year" prepend-inner-icon="mdi-calendar" />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="2">
            <v-autocomplete v-model="filters.division_id" :items="cfg.divisionOptions || []" item-title="title" item-value="value" label="Division" prepend-inner-icon="mdi-office-building" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="3">
            <v-autocomplete v-model="filters.staff_id" :items="cfg.staffOptions || []" item-title="title" item-value="value" label="Staff" prepend-inner-icon="mdi-account" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.status" :items="cfg.statusOptions || []" item-title="title" item-value="value" label="Status" prepend-inner-icon="mdi-filter-outline" />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.request_type_id" :items="cfg.requestTypeOptions || []" item-title="title" item-value="value" label="Request type" prepend-inner-icon="mdi-tag" />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.fund_type_id" :items="cfg.fundTypeOptions || []" item-title="title" item-value="value" label="Fund type" prepend-inner-icon="mdi-cash" />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">Filter</v-btn>
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center">
            <v-btn variant="text" prepend-icon="mdi-refresh" block @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-tabs v-model="activeTab" color="primary" grow class="apm-vuetify-tabs">
        <v-tab value="mySubmitted">
          <v-icon start icon="mdi-file-document" />
          My submitted
          <v-chip size="x-small" color="success" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_submitted }}</v-chip>
        </v-tab>
        <v-tab value="myDivision">
          <v-icon start icon="mdi-office-building" />
          My division
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab value="sharedMemos">
          <v-icon start icon="mdi-share-variant" />
          Shared memos
          <v-chip size="x-small" color="info" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.shared_memos }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllMemos" value="allMemos">
          <v-icon start icon="mdi-grid" />
          All memos
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.all_memos }}</v-chip>
        </v-tab>
      </v-tabs>

      <v-card-text class="pt-4">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-3">
          <div class="text-body-2 text-medium-emphasis">
            Showing {{ showingRange }} of {{ pagination.total }}
          </div>
          <v-btn
            v-if="exportUrl"
            :href="exportUrl"
            variant="outlined"
            color="primary"
            size="small"
            prepend-icon="mdi-download"
          >
            Export to Excel
          </v-btn>
        </div>

        <v-data-table
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
          class="apm-list-table"
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

          <template #item.title="{ item }">
            <div v-if="item.document_number" class="text-caption text-medium-emphasis">#{{ item.document_number }}</div>
            <div class="font-weight-medium text-wrap">{{ item.title || 'Untitled' }}</div>
          </template>

          <template #item.fund_type_name="{ item }">
            <v-chip size="small" color="warning" variant="tonal" label class="mb-1">
              {{ item.fund_type_name || 'N/A' }}
            </v-chip>
            <div v-if="item.fund_code_labels && item.fund_code_labels.length" class="text-caption text-medium-emphasis text-wrap">
              {{ item.fund_code_labels.join(', ') }}
            </div>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="tonal" label class="text-uppercase">
              {{ item.overall_status }}
            </v-chip>
            <template v-if="item.overall_status === 'pending' || item.overall_status === 'returned'">
              <div v-if="item.status_level" class="text-caption text-medium-emphasis mt-1">Level {{ item.status_level }}</div>
              <div v-if="item.workflow_role" class="text-caption text-medium-emphasis">{{ item.workflow_role }}</div>
              <div v-if="item.current_actor_name" class="text-caption text-medium-emphasis">{{ item.current_actor_name }}</div>
            </template>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex flex-wrap gap-1 justify-end">
              <v-btn size="small" variant="outlined" color="info" prepend-icon="mdi-eye" :href="item.show_url">Open</v-btn>
              <v-btn v-if="item.copy_url" size="small" variant="outlined" color="secondary" prepend-icon="mdi-content-copy" :href="item.copy_url">Copy</v-btn>
              <v-btn v-if="item.edit_url" size="small" variant="outlined" color="warning" prepend-icon="mdi-pencil" :href="item.edit_url">Edit</v-btn>
              <v-btn v-if="item.delete_url" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="confirmDelete(item)">Delete</v-btn>
              <v-btn v-if="item.print_url" size="small" variant="outlined" color="success" prepend-icon="mdi-printer" :href="item.print_url" target="_blank">Print</v-btn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-file-star-outline" size="48" class="mb-2 opacity-50" />
              <div>No special memos found for the selected filters.</div>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="praPicker.open" max-width="960" content-class="modal-lg" scrollable persistent>
      <v-card>
        <v-card-title class="d-flex align-center gap-2">
          <v-icon icon="mdi-cloud-download" color="primary" />
          Select PRA activity for special memo
        </v-card-title>
        <v-progress-linear v-if="praPicker.refreshing || praPicker.loading" indeterminate color="primary" height="3" />
        <v-divider v-else />
        <v-card-text>
          <div class="d-flex flex-wrap align-center gap-2 mb-3">
            <v-select
              v-model="praPicker.quarterFilter"
              :items="[{ title: 'All quarters', value: '' }, { title: 'Q1', value: 'Q1' }, { title: 'Q2', value: 'Q2' }, { title: 'Q3', value: 'Q3' }, { title: 'Q4', value: 'Q4' }]"
              item-title="title"
              item-value="value"
              label="Filter by quarter"
              density="compact"
              hide-details
              variant="outlined"
              style="min-width: 150px; max-width: 170px;"
            />
            <v-text-field
              v-model="praPicker.search"
              placeholder="Search…"
              prepend-inner-icon="mdi-magnify"
              clearable
              density="compact"
              hide-details
              variant="outlined"
              style="min-width: 200px; max-width: 260px;"
            />
            <v-btn variant="outlined" height="40" :loading="praPicker.refreshing" prepend-icon="mdi-refresh" @click="loadPraActivities(true)">Refresh</v-btn>
          </div>
          <div class="text-caption text-medium-emphasis mb-2">
            {{ praFiltered.length }} shown
            <span v-if="praPicker.meta?.count != null"> / {{ praPicker.meta.count }} loaded</span>
            <span v-if="praPicker.meta?.existing_count"> · {{ praPicker.meta.existing_count }} already in APM</span>
          </div>
          <v-radio-group v-model="praPicker.selectedId" :disabled="praPicker.loading">
            <v-list class="border rounded" lines="three" max-height="420" style="overflow:auto">
              <v-list-item v-for="row in praFiltered" :key="row.pra_activity_id" @click="praPicker.selectedId = row.pra_activity_id">
                <template #prepend>
                  <v-radio :value="row.pra_activity_id" />
                </template>
                <v-list-item-title class="text-wrap">{{ row.title }}</v-list-item-title>
                <v-list-item-subtitle class="text-wrap">
                  {{ row.code }} · {{ row.outcome_area || 'No outcome' }}
                  <template v-if="row.already_exists">
                    · <span class="text-warning">already in APM</span>
                    <template v-for="ex in (row.existing || []).slice(0, 2)" :key="ex.type + '-' + ex.id">
                      · <a :href="ex.url" target="_blank" @click.stop>{{ ex.label }}</a>
                    </template>
                  </template>
                </v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="!praFiltered.length && !praPicker.loading">
                <v-list-item-title class="text-medium-emphasis">No PRA activities match this filter.</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-radio-group>
          <v-alert v-if="praSelected?.already_exists" type="warning" variant="tonal" density="compact" class="mt-3">
            This PRA activity is already linked in APM (may not be approved or implemented yet). You can still continue.
          </v-alert>
          <v-checkbox
            v-if="praSelected?.already_exists"
            v-model="praPicker.allowExisting"
            density="compact"
            hide-details
            class="mt-2"
            label="Continue with already-linked PRA activity"
          />
        </v-card-text>
        <v-divider />
        <v-card-actions>
          <v-btn variant="text" @click="praPicker.open = false">Cancel</v-btn>
          <v-spacer />
          <v-btn color="primary" :disabled="!praPicker.selectedId" prepend-icon="mdi-arrow-right" @click="proceedPraPicker">
            Continue to form
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootSpecialMemosIndex);
    }
})();
