/**
 * Request ARF index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'request-arf-index-app';

    const FILTER_KEYS = [
        'year', 'division_id', 'staff_id', 'status',
        'document_number', 'search', 'fund_type_id',
    ];

    const SELECT_FILTER_KEYS = ['year', 'division_id', 'staff_id', 'status', 'fund_type_id'];
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
            page: Math.max(1, parseInt(pick('page', '1'), 10) || 1),
        };
    }

    function statusColor(status) {
        const s = String(status || '').toLowerCase();
        if (s === 'approved') return 'success';
        if (s === 'pending') return 'warning';
        if (s === 'cancelled' || s === 'rejected') return 'error';
        if (s === 'returned') return 'info';
        if (s === 'archived') return 'secondary';
        return 'secondary';
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
        if (filters.fund_type_id) current.searchParams.set('fund_type_id', filters.fund_type_id);
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));

        window.history.replaceState({}, '', current.toString());
    }

    function buildExportUrl(baseUrl, filters, tab) {
        const url = new URL(baseUrl, window.location.origin);
        if (filters.year) url.searchParams.set('year', filters.year);
        if (filters.division_id) url.searchParams.set('division_id', filters.division_id);
        if (filters.staff_id) url.searchParams.set('staff_id', filters.staff_id);
        if (filters.status) url.searchParams.set('status', filters.status);
        if (filters.document_number) url.searchParams.set('document_number', filters.document_number);
        if (filters.search) url.searchParams.set('search', filters.search);
        if (filters.fund_type_id) url.searchParams.set('fund_type_id', filters.fund_type_id);
        if (tab) url.searchParams.set('tab', tab);
        return url.toString();
    }

    function submitHiddenForm(action, method, csrf) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);
        if (method && method !== 'POST') {
            const m = document.createElement('input');
            m.type = 'hidden';
            m.name = '_method';
            m.value = method;
            form.appendChild(m);
        }
        document.body.appendChild(form);
        form.submit();
    }

    function bootRequestArfIndex(mountEl, cfg) {
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
                    division_id: initial.division_id,
                    staff_id: initial.staff_id,
                    status: initial.status,
                    document_number: initial.document_number,
                    search: initial.search,
                    fund_type_id: initial.fund_type_id,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 20);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_submitted: cfg.counts?.my_submitted ?? 0,
                    my_division: cfg.counts?.my_division ?? 0,
                    all_arfs: cfg.counts?.all_arfs ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const csrf = cfg.csrf || '';

                const showStaffColumn = computed(() => activeTab.value !== 'mySubmitted');

                const exportUrl = computed(() => {
                    if (activeTab.value === 'mySubmitted' && cfg.routes?.exportMySubmitted) {
                        return buildExportUrl(cfg.routes.exportMySubmitted, filters.value, activeTab.value);
                    }
                    if (activeTab.value === 'allArfs' && cfg.routes?.exportAll) {
                        return buildExportUrl(cfg.routes.exportAll, filters.value, activeTab.value);
                    }
                    return null;
                });

                const headers = computed(() => {
                    const base = [
                        { title: '#', key: 'row_num', sortable: false, width: 56 },
                        { title: 'ARF #', key: 'document_number', sortable: false, width: 120 },
                        { title: 'Title', key: 'title', sortable: false, minWidth: 220 },
                    ];
                    if (showStaffColumn.value) {
                        base.push({ title: 'Staff', key: 'staff_name', sortable: false, minWidth: 140 });
                    }
                    base.push(
                        { title: 'Division', key: 'division_name', sortable: false, minWidth: 130 },
                        { title: 'Status', key: 'overall_status', sortable: false, minWidth: 160 },
                        { title: 'Created', key: 'created_at', sortable: false, width: 110 },
                        { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 220 },
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

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            tab: activeTab.value,
                            year: filters.value.year,
                            division_id: filters.value.division_id,
                            staff_id: filters.value.staff_id,
                            status: filters.value.status,
                            document_number: filters.value.document_number,
                            search: filters.value.search,
                            fund_type_id: filters.value.fund_type_id,
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                        });

                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'Could not load ARF requests.');
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
                                all_arfs: data.counts.all_arfs ?? counts.value.all_arfs,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load ARF requests.');
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
                    };
                    page.value = 1;
                    loadItems();
                }

                function confirmDelete(item) {
                    if (!item.delete_url) return;
                    if (!window.confirm('Delete this ARF request? This cannot be undone.')) return;
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
                    exportUrl,
                    statusColor,
                    applyFilters,
                    resetFilters,
                    confirmDelete,
                };
            },
            template: `
<v-app class="arf-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex align-center gap-2 py-4">
        <v-icon icon="mdi-file-document-outline" color="primary" />
        <span class="text-h6 font-weight-bold">ARF request management</span>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" lg="3">
            <v-text-field v-model="filters.search" label="Search title" prepend-inner-icon="mdi-magnify" placeholder="Enter ARF title…" clearable />
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
        <v-tab value="mySubmitted">
          <v-icon start icon="mdi-file-document" />
          My submitted
          <v-chip size="x-small" color="success" class="ms-2">{{ counts.my_submitted }}</v-chip>
        </v-tab>
        <v-tab value="myDivision">
          <v-icon start icon="mdi-office-building" />
          My division
          <v-chip size="x-small" color="primary" class="ms-2">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllArfs" value="allArfs">
          <v-icon start icon="mdi-grid" />
          All ARF requests
          <v-chip size="x-small" color="primary" class="ms-2">{{ counts.all_arfs }}</v-chip>
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
          class="arf-list-table apm-list-table"
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
            <v-chip size="small" color="info" variant="tonal" label>
              {{ item.document_number || 'N/A' }}
            </v-chip>
          </template>

          <template #item.title="{ item }">
            <div class="font-weight-medium text-wrap">{{ item.title }}</div>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="tonal" label class="text-uppercase">
              {{ item.overall_status }}
            </v-chip>
            <template v-if="item.overall_status === 'pending'">
              <div v-if="item.approval_level" class="text-caption text-medium-emphasis mt-1">
                Level {{ item.approval_level }}
              </div>
              <div v-if="item.workflow_role" class="text-caption text-medium-emphasis">
                {{ item.workflow_role }}
              </div>
              <div v-if="item.current_actor_name" class="text-caption text-medium-emphasis">
                {{ item.current_actor_name }}
              </div>
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
              <v-icon icon="mdi-file-document-outline" size="48" class="mb-2 opacity-50" />
              <div>No ARF requests found for the selected filters.</div>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootRequestArfIndex);
    }
})();
