/**
 * Change requests index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'change-requests-index-app';
    const memoList = window.ApmVuetifyMemoList || {};

    const FILTER_KEYS = [
        'year', 'division_id', 'staff_id', 'status',
        'document_number', 'search', 'memo_type', 'fund_type_id',
    ];
    const SELECT_FILTER_KEYS = ['year', 'division_id', 'staff_id', 'status', 'memo_type', 'fund_type_id'];
    const TEXT_FILTER_KEYS = ['search', 'document_number'];

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);
        const defaults = cfg.defaults || {};

        return {
            tab: pick('tab', defaults.tab || 'myChangeRequests'),
            year: pick('year', String(defaults.year ?? cfg.currentYear ?? '')),
            division_id: pick('division_id', defaults.division_id ?? ''),
            staff_id: pick('staff_id', defaults.staff_id ?? ''),
            status: pick('status', defaults.status ?? 'all'),
            document_number: pick('document_number', defaults.document_number ?? ''),
            search: pick('search', defaults.search ?? ''),
            memo_type: pick('memo_type', defaults.memo_type ?? ''),
            fund_type_id: pick('fund_type_id', defaults.fund_type_id ?? ''),
            page: Math.max(1, parseInt(pick('page', '1'), 10) || 1),
        };
    }

    function syncUrl(filters, tab, page) {
        const current = new URL(window.location.href);
        ['tab', 'page', 'fragment', 'overall_status', ...FILTER_KEYS].forEach((k) => current.searchParams.delete(k));

        if (filters.year) current.searchParams.set('year', filters.year);
        if (filters.division_id) current.searchParams.set('division_id', filters.division_id);
        if (filters.staff_id) current.searchParams.set('staff_id', filters.staff_id);
        if (filters.status) current.searchParams.set('status', filters.status);
        if (filters.document_number) current.searchParams.set('document_number', filters.document_number);
        if (filters.search) current.searchParams.set('search', filters.search);
        if (filters.memo_type) current.searchParams.set('memo_type', filters.memo_type);
        if (filters.fund_type_id) current.searchParams.set('fund_type_id', filters.fund_type_id);
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));

        window.history.replaceState({}, '', current.toString());
    }

    function bootChangeRequestsIndex(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch } = Vue;
        const vuetify = memoList.createVuetify ? memoList.createVuetify() : Vuetify.createVuetify();
        const statusColor = memoList.statusColor || (() => 'secondary');

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
                    memo_type: initial.memo_type,
                    fund_type_id: initial.fund_type_id,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 20);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_change_requests: cfg.counts?.my_change_requests ?? 0,
                    my_division: cfg.counts?.my_division ?? 0,
                    shared: cfg.counts?.shared ?? 0,
                    all: cfg.counts?.all ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const csrf = cfg.csrf || '';

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Document#', key: 'document_number', sortable: false, width: 130 },
                    { title: 'Title', key: 'title', sortable: false, minWidth: 200 },
                    { title: 'Parent memo', key: 'parent_memo', sortable: false, minWidth: 140 },
                    { title: 'Date range', key: 'date_range', sortable: false, width: 130 },
                    { title: 'Changes', key: 'change_labels', sortable: false, minWidth: 160 },
                    { title: 'Status', key: 'overall_status', sortable: false, minWidth: 140 },
                    { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 220 },
                ];

                const showingRange = computed(() => {
                    if (pagination.value.total === 0) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
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
                            throw new Error(data.error || 'Could not load change requests.');
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
                                my_change_requests: data.counts.my_change_requests ?? counts.value.my_change_requests,
                                my_division: data.counts.my_division ?? counts.value.my_division,
                                shared: data.counts.shared ?? counts.value.shared,
                                all: data.counts.all ?? counts.value.all,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load change requests.');
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
                        status: 'all',
                        document_number: '',
                        search: '',
                        memo_type: '',
                        fund_type_id: '',
                    };
                    page.value = 1;
                    loadItems();
                }

                async function confirmDelete(item) {
                    if (!item.delete_url) return;
                    if (!window.confirm('Are you sure you want to delete this change request? This action cannot be undone.')) {
                        return;
                    }

                    try {
                        const formData = new FormData();
                        formData.append('_method', 'DELETE');
                        formData.append('_token', csrf);

                        const res = await fetch(item.delete_url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                Accept: 'application/json',
                            },
                            body: formData,
                        });

                        const text = await res.text();
                        let data = {};
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = { success: false, msg: text || `Delete failed (${res.status})` };
                        }
                        if (!res.ok) {
                            data.success = false;
                            data.msg = data.msg || `Delete failed (${res.status})`;
                        }

                        if (data.success) {
                            notify(data.msg || 'Change request deleted.', 'success');
                            loadItems();
                        } else {
                            notify(data.msg || 'Failed to delete change request.');
                        }
                    } catch (e) {
                        notify('An error occurred while deleting the change request.');
                    }
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
                    headers,
                    showingRange,
                    statusColor,
                    applyFilters,
                    resetFilters,
                    confirmDelete,
                };
            },
            template: `
<v-app class="cr-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4">
        <div class="d-flex align-center gap-2">
          <v-icon icon="mdi-swap-horizontal" color="primary" />
          <span class="text-h6 font-weight-bold">Change request management</span>
        </div>
        <v-btn
          v-if="cfg.routes?.pendingApprovals"
          :href="cfg.routes.pendingApprovals"
          variant="outlined"
          color="warning"
          size="small"
          prepend-icon="mdi-clock-outline"
        >
          Pending approvals
          <v-chip v-if="cfg.pendingApprovalCount > 0" size="x-small" color="error" class="ms-2">{{ cfg.pendingApprovalCount }}</v-chip>
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" lg="3">
            <v-text-field v-model="filters.search" label="Search title" prepend-inner-icon="mdi-magnify" placeholder="Enter activity title…" clearable />
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
            <v-select v-model="filters.memo_type" :items="cfg.memoTypeOptions || []" item-title="title" item-value="value" label="Memo type" prepend-inner-icon="mdi-file-tree" />
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
      <v-tabs v-model="activeTab" color="primary" grow>
        <v-tab value="myChangeRequests">
          <v-icon start icon="mdi-file-document" />
          My change requests
          <v-chip size="x-small" color="success" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_change_requests }}</v-chip>
        </v-tab>
        <v-tab value="myDivisionChangeRequests">
          <v-icon start icon="mdi-office-building" />
          My division
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab value="sharedChangeRequests">
          <v-icon start icon="mdi-share-variant" />
          Shared
          <v-chip size="x-small" color="info" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.shared }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllChangeRequests" value="allChangeRequests">
          <v-icon start icon="mdi-grid" />
          All change requests
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.all }}</v-chip>
        </v-tab>
      </v-tabs>

      <v-card-text class="pt-4">
        <div class="text-body-2 text-medium-emphasis mb-3">
          Showing {{ showingRange }} of {{ pagination.total }}
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

          <template #item.document_number="{ item }">
            <v-chip size="small" color="info" variant="tonal" label class="mb-1">
              {{ item.document_number || 'Pending' }}
            </v-chip>
            <div v-if="item.division_name" class="text-caption text-medium-emphasis">{{ item.division_name }}</div>
          </template>

          <template #item.title="{ item }">
            <div class="font-weight-medium text-wrap">{{ item.title || 'Untitled' }}</div>
            <div v-if="item.supporting_reasons_preview" class="text-caption text-medium-emphasis text-wrap">{{ item.supporting_reasons_preview }}</div>
          </template>

          <template #item.parent_memo="{ item }">
            <template v-if="item.parent_memo_url && item.parent_memo_document_number">
              <a :href="item.parent_memo_url" class="text-primary text-decoration-none">
                {{ item.parent_memo_document_number }}
              </a>
              <div v-if="item.parent_memo_model" class="text-caption text-medium-emphasis">{{ item.parent_memo_model }}</div>
            </template>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <template #item.change_labels="{ item }">
            <div v-if="item.change_labels && item.change_labels.length" class="d-flex flex-wrap gap-1">
              <v-chip
                v-for="(label, idx) in item.change_labels"
                :key="idx"
                size="x-small"
                color="secondary"
                variant="tonal"
                label
              >
                {{ label }}
              </v-chip>
            </div>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="tonal" label class="text-uppercase">
              {{ item.overall_status }}
            </v-chip>
            <template v-if="item.overall_status === 'pending' || item.overall_status === 'returned' || item.overall_status === 'submitted'">
              <div v-if="item.status_level" class="text-caption text-medium-emphasis mt-1">Level {{ item.status_level }}</div>
              <div v-if="item.workflow_role" class="text-caption text-medium-emphasis">{{ item.workflow_role }}</div>
              <div v-if="item.current_actor_name" class="text-caption text-medium-emphasis">{{ item.current_actor_name }}</div>
            </template>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex flex-wrap gap-1 justify-end">
              <v-btn size="small" variant="outlined" color="info" prepend-icon="mdi-eye" :href="item.show_url">Open</v-btn>
              <v-btn v-if="item.edit_url" size="small" variant="outlined" color="warning" prepend-icon="mdi-pencil" :href="item.edit_url">Edit</v-btn>
              <v-btn v-if="item.delete_url" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="confirmDelete(item)">Delete</v-btn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-swap-horizontal" size="48" class="mb-2 opacity-50" />
              <div>No change requests found for the selected filters.</div>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootChangeRequestsIndex);
    }
})();
