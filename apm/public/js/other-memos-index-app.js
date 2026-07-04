/**
 * Other memos index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'other-memos-index-app';

    const FILTER_KEYS = [
        'year', 'division_id', 'staff_id', 'status',
        'document_number', 'search',
    ];

    const SELECT_FILTER_KEYS = ['year', 'division_id', 'staff_id', 'status'];
    const TEXT_FILTER_KEYS = ['search', 'document_number'];

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);
        const defaults = cfg.defaults || {};

        return {
            tab: pick('tab', defaults.tab || 'mySubmitted'),
            year: pick('year', String(defaults.year ?? cfg.currentYear ?? '')),
            division_id: pick('division_id', defaults.division_id ?? ''),
            staff_id: pick('staff_id', defaults.staff_id ?? ''),
            status: pick('status', defaults.status ?? ''),
            document_number: pick('document_number', defaults.document_number ?? ''),
            search: pick('search', defaults.search ?? ''),
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
        ['tab', 'page', 'fragment', ...FILTER_KEYS].forEach((k) => current.searchParams.delete(k));

        if (filters.year) current.searchParams.set('year', filters.year);
        if (filters.division_id) current.searchParams.set('division_id', filters.division_id);
        if (filters.staff_id) current.searchParams.set('staff_id', filters.staff_id);
        if (filters.status) current.searchParams.set('status', filters.status);
        if (filters.document_number) current.searchParams.set('document_number', filters.document_number);
        if (filters.search) current.searchParams.set('search', filters.search);
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));

        window.history.replaceState({}, '', current.toString());
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

    function bootOtherMemosIndex(mountEl, cfg) {
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
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 20);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_submitted: cfg.counts?.my_submitted ?? 0,
                    my_division: cfg.counts?.my_division ?? 0,
                    all_memos: cfg.counts?.all_memos ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const csrf = cfg.csrf || '';

                const showCreatorColumn = computed(() => activeTab.value !== 'mySubmitted');

                const headers = computed(() => {
                    const base = [
                        { title: '#', key: 'row_num', sortable: false, width: 56 },
                        { title: 'Title / type', key: 'title', sortable: false, minWidth: 220 },
                    ];
                    if (showCreatorColumn.value) {
                        base.push({ title: 'Creator', key: 'creator_name', sortable: false, minWidth: 140 });
                    }
                    base.push(
                        { title: 'Division', key: 'division_name', sortable: false, minWidth: 130 },
                        { title: 'Created', key: 'created_at', sortable: false, width: 110 },
                        { title: 'Status', key: 'overall_status', sortable: false, minWidth: 140 },
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
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                        });

                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'Could not load other memos.');
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
                                all_memos: data.counts.all_memos ?? counts.value.all_memos,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load other memos.');
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
                    };
                    page.value = 1;
                    loadItems();
                }

                function confirmDelete(item) {
                    if (!item.delete_url) return;
                    if (!window.confirm('Delete this draft? This cannot be undone.')) return;
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
                    statusColor,
                    applyFilters,
                    resetFilters,
                    confirmDelete,
                };
            },
            template: `
<v-app class="om-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex align-center gap-2 py-4">
        <v-icon icon="mdi-file-document-outline" color="primary" />
        <span class="text-h6 font-weight-bold">Other memo management</span>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" lg="3">
            <v-text-field v-model="filters.search" label="Search title" prepend-inner-icon="mdi-magnify" placeholder="Title or type…" clearable />
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
            <v-autocomplete v-model="filters.staff_id" :items="cfg.staffOptions || []" item-title="title" item-value="value" label="Staff / creator" prepend-inner-icon="mdi-account" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select v-model="filters.status" :items="cfg.statusOptions || []" item-title="title" item-value="value" label="Status" prepend-inner-icon="mdi-filter-outline" />
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
        <v-tab v-if="cfg.canViewAllMemos" value="allMemos">
          <v-icon start icon="mdi-grid" />
          All other memos
          <v-chip size="x-small" color="primary" class="ms-2">{{ counts.all_memos }}</v-chip>
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
          class="om-list-table apm-list-table"
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
            <div class="font-weight-medium">{{ item.title }}</div>
            <div class="text-caption text-medium-emphasis">({{ item.memo_type_name }})</div>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="tonal" label class="text-uppercase">
              {{ item.overall_status }}
            </v-chip>
            <div v-if="item.overall_status === 'pending' && item.current_approver_name" class="text-caption text-medium-emphasis mt-1">
              {{ item.current_approver_name }}
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex flex-wrap gap-1 justify-end">
              <v-btn size="small" variant="outlined" color="info" prepend-icon="mdi-eye" :href="item.show_url">Open</v-btn>
              <v-btn v-if="item.edit_url" size="small" variant="outlined" color="warning" prepend-icon="mdi-pencil" :href="item.edit_url">Edit</v-btn>
              <v-btn v-if="item.delete_url" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="confirmDelete(item)">Delete</v-btn>
              <v-btn v-if="item.print_url" size="small" variant="outlined" color="success" prepend-icon="mdi-printer" :href="item.print_url" target="_blank">Print</v-btn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-file-document-outline" size="48" class="mb-2 opacity-50" />
              <div>No other memos found for the selected filters.</div>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootOtherMemosIndex);
    }
})();
