/**
 * Single memos index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'single-memos-index-app';

    const MATRIX_ACCEPTS_STATUSES = ['approved', 'pending', 'returned', 'onhold'];

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);
        const defaults = cfg.defaults || {};

        return {
            tab: pick('tab', defaults.tab || 'mySubmitted'),
            year: pick('year', String(defaults.year ?? cfg.apmCurrentYear ?? '')),
            quarter: pick('quarter', defaults.quarter ?? cfg.apmCurrentQuarter ?? ''),
            division_id: pick('division_id', defaults.division_id ?? ''),
            staff_id: pick('staff_id', defaults.staff_id ?? ''),
            status: pick('status', defaults.status ?? ''),
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
        if (s === 'rejected') return 'error';
        if (s === 'returned') return 'info';
        if (s === 'archived') return 'secondary';
        return 'secondary';
    }

    function syncUrl(filters, tab, page) {
        const current = new URL(window.location.href);
        [
            'year', 'quarter', 'division_id', 'staff_id', 'status',
            'document_number', 'search', 'fund_type_id', 'tab', 'page',
        ].forEach((k) => current.searchParams.delete(k));

        if (filters.year) current.searchParams.set('year', filters.year);
        if (filters.quarter) current.searchParams.set('quarter', filters.quarter);
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

    function navigateTo(url) {
        if (!url) return;
        if (typeof Livewire !== 'undefined' && Livewire.navigate) {
            Livewire.navigate(url);
        } else {
            window.location.href = url;
        }
    }

    function bootSingleMemosIndex(mountEl, cfg) {
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
                    division_id: initial.division_id,
                    staff_id: initial.staff_id,
                    status: initial.status,
                    document_number: initial.document_number,
                    search: initial.search,
                    fund_type_id: initial.fund_type_id,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 10);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    my_submitted: cfg.counts?.my_submitted ?? 0,
                    all_memos: cfg.counts?.all_memos ?? 0,
                    shared_memos: cfg.counts?.shared_memos ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const createDialog = ref(false);

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Title', key: 'activity_title', sortable: false, minWidth: 220 },
                    { title: 'Responsible', key: 'responsible_person_name', sortable: false, minWidth: 140 },
                    { title: 'Division', key: 'division_name', sortable: false, minWidth: 120 },
                    { title: 'Dates', key: 'date_range', sortable: false, width: 130 },
                    { title: 'Fund type', key: 'fund_type_name', sortable: false, minWidth: 140 },
                    { title: 'Status', key: 'overall_status', sortable: false, minWidth: 140 },
                    { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 220 },
                ];

                const showingRange = computed(() => {
                    if (pagination.value.total === 0) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                const tabTitle = computed(() => {
                    if (activeTab.value === 'allMemos') return 'All single memos';
                    if (activeTab.value === 'sharedMemos') return 'Shared single memos';
                    return 'My division single memos';
                });

                const currentQuarterMatrix = computed(() => cfg.currentQuarterMatrix || null);

                const matrixAcceptsMemos = computed(() => {
                    const matrix = currentQuarterMatrix.value;
                    if (!matrix || !matrix.overall_status) return false;
                    return MATRIX_ACCEPTS_STATUSES.includes(String(matrix.overall_status).toLowerCase());
                });

                const matrixStatusNote = computed(() => {
                    const label = cfg.currentQuarterLabel || '';
                    if (matrixAcceptsMemos.value) {
                        return `Your division's ${label} matrix is available and accepts single memos.`;
                    }
                    if (currentQuarterMatrix.value) {
                        return `Your division's ${label} matrix exists but is not yet in a status that accepts single memos (pending, approved, returned, or on hold).`;
                    }
                    return `No matrix was found for your division in ${label}. Contact your focal person or administrator if you need one created.`;
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function showStatusMeta(item) {
                    const status = String(item.overall_status || '').toLowerCase();
                    return status === 'pending' || status === 'returned';
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            tab: activeTab.value,
                            year: filters.value.year,
                            quarter: filters.value.quarter,
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
                            throw new Error(data.error || 'Could not load single memos.');
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
                                all_memos: data.counts.all_memos ?? counts.value.all_memos,
                                shared_memos: data.counts.shared_memos ?? counts.value.shared_memos,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load single memos.');
                    } finally {
                        loading.value = false;
                    }
                }

                function applyFilters() {
                    page.value = 1;
                    loadItems();
                }

                function resetFilters() {
                    const defaults = cfg.defaults || {};
                    filters.value = {
                        year: String(defaults.year ?? cfg.apmCurrentYear ?? ''),
                        quarter: defaults.quarter ?? cfg.apmCurrentQuarter ?? '',
                        division_id: defaults.division_id ?? '',
                        staff_id: defaults.staff_id ?? '',
                        status: defaults.status ?? '',
                        document_number: defaults.document_number ?? '',
                        search: defaults.search ?? '',
                        fund_type_id: defaults.fund_type_id ?? '',
                    };
                    page.value = 1;
                    loadItems();
                }

                function confirmCopy(item) {
                    if (!item.copy_url) return;
                    if (window.confirm('Copy this single memo as a new draft?')) {
                        navigateTo(item.copy_url);
                    }
                }

                function confirmDelete(item) {
                    if (!item.delete_url) return;
                    if (window.confirm('Are you sure you want to delete this single memo? This action cannot be undone.')) {
                        submitHiddenForm(item.delete_url, 'DELETE', cfg.csrf);
                    }
                }

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
                    createDialog,
                    headers,
                    showingRange,
                    tabTitle,
                    currentQuarterMatrix,
                    matrixAcceptsMemos,
                    matrixStatusNote,
                    statusColor,
                    applyFilters,
                    resetFilters,
                    confirmCopy,
                    confirmDelete,
                    showStatusMeta,
                };
            },
            template: `
<v-app class="sm-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4">
        <div class="d-flex align-center gap-2">
          <v-icon icon="mdi-file-document-outline" color="primary" />
          <span class="text-h6 font-weight-bold">Single memo management</span>
        </div>
        <v-btn
          v-if="cfg.showCreateInstructions"
          color="primary"
          variant="flat"
          prepend-icon="mdi-plus"
          @click="createDialog = true"
        >
          Create new
        </v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select
              v-model="filters.year"
              :items="cfg.yearOptions || []"
              item-title="title"
              item-value="value"
              label="Year"
              prepend-inner-icon="mdi-calendar"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select
              v-model="filters.quarter"
              :items="cfg.quarterOptions || []"
              item-title="title"
              item-value="value"
              label="Quarter"
              prepend-inner-icon="mdi-clock-outline"
            />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="3">
            <v-autocomplete
              v-model="filters.division_id"
              :items="cfg.divisionOptions || []"
              item-title="title"
              item-value="value"
              label="Division"
              prepend-inner-icon="mdi-office-building"
              clearable
            />
          </v-col>
          <v-col cols="12" sm="6" md="6" lg="3">
            <v-autocomplete
              v-model="filters.staff_id"
              :items="cfg.staffOptions || []"
              item-title="title"
              item-value="value"
              label="Staff / responsible person"
              prepend-inner-icon="mdi-account"
              clearable
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select
              v-model="filters.status"
              :items="cfg.statusOptions || []"
              item-title="title"
              item-value="value"
              label="Status"
              prepend-inner-icon="mdi-filter-outline"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-text-field
              v-model="filters.document_number"
              label="Document #"
              prepend-inner-icon="mdi-pound"
              clearable
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="3">
            <v-text-field
              v-model="filters.search"
              label="Search single memo title"
              prepend-inner-icon="mdi-magnify"
              placeholder="Enter single memo title to search…"
              clearable
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="3">
            <v-select
              v-model="filters.fund_type_id"
              :items="cfg.fundTypeOptions || []"
              item-title="title"
              item-value="value"
              label="Fund type"
              prepend-inner-icon="mdi-cash"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center gap-2">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">Filter</v-btn>
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center">
            <v-btn variant="outlined" color="secondary" prepend-icon="mdi-filter-off" block @click="resetFilters">Reset</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-tabs v-model="activeTab" color="primary" grow class="apm-vuetify-tabs">
        <v-tab value="mySubmitted">
          <v-icon start icon="mdi-file-document" />
          My division
          <v-chip size="x-small" color="success" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.my_submitted }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllMemos" value="allMemos">
          <v-icon start icon="mdi-grid" />
          All memos
          <v-chip size="x-small" color="primary" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.all_memos }}</v-chip>
        </v-tab>
        <v-tab value="sharedMemos">
          <v-icon start icon="mdi-share-variant" />
          Shared memos
          <v-chip size="x-small" color="info" variant="flat" class="ms-2 apm-tab-count-chip">{{ counts.shared_memos }}</v-chip>
        </v-tab>
      </v-tabs>

      <v-card-text class="pt-4">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-3">
          <div>
            <div class="text-subtitle-1 font-weight-bold">{{ tabTitle }}</div>
            <div class="text-body-2 text-medium-emphasis">
              Showing {{ showingRange }} of {{ pagination.total }}
            </div>
          </div>
        </div>

        <v-data-table
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
          class="sm-list-table apm-list-table"
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

          <template #item.activity_title="{ item }">
            <div v-if="item.document_number" class="text-caption text-medium-emphasis">#{{ item.document_number }}</div>
            <div class="font-weight-medium text-primary">{{ item.activity_title || 'Untitled' }}</div>
            <div v-if="item.background_preview" class="text-caption text-medium-emphasis text-wrap">{{ item.background_preview }}</div>
          </template>

          <template #item.responsible_person_name="{ item }">
            <div class="font-weight-medium">{{ item.responsible_person_name || 'N/A' }}</div>
            <div v-if="item.responsible_person_role" class="text-caption text-medium-emphasis">{{ item.responsible_person_role }}</div>
          </template>

          <template #item.division_name="{ item }">
            <span class="text-wrap">{{ item.division_name || 'N/A' }}</span>
          </template>

          <template #item.date_range="{ item }">
            <span class="text-body-2">{{ item.date_range || '—' }}</span>
          </template>

          <template #item.fund_type_name="{ item }">
            <v-chip size="small" color="warning" variant="flat" class="mb-1">
              <v-icon start icon="mdi-cash" size="x-small" />
              {{ item.fund_type_name || 'N/A' }}
            </v-chip>
            <div v-if="item.fund_code_labels && item.fund_code_labels.length" class="text-caption text-medium-emphasis text-wrap">
              {{ item.fund_code_labels.join(', ') }}
            </div>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="flat" class="text-uppercase mb-1">
              {{ item.overall_status || 'draft' }}
            </v-chip>
            <template v-if="showStatusMeta(item)">
              <div v-if="item.status_level" class="text-caption text-medium-emphasis">Level {{ item.status_level }}</div>
              <div v-if="item.workflow_role" class="text-caption text-medium-emphasis">{{ item.workflow_role }}</div>
              <div v-if="item.current_actor_name" class="text-caption text-medium-emphasis">{{ item.current_actor_name }}</div>
            </template>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex flex-wrap gap-1 justify-end">
              <v-btn size="small" variant="outlined" color="info" prepend-icon="mdi-eye" :href="item.show_url">Open</v-btn>
              <v-btn v-if="item.copy_url" size="small" variant="outlined" color="secondary" prepend-icon="mdi-content-copy" @click="confirmCopy(item)">Copy</v-btn>
              <v-btn v-if="item.delete_url" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="confirmDelete(item)">Delete</v-btn>
              <v-btn v-if="item.print_url" size="small" variant="outlined" color="success" prepend-icon="mdi-printer" :href="item.print_url" target="_blank">Print</v-btn>
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-file-document-outline" size="48" class="mb-2 opacity-50" />
              <div>No single memos found for the selected filters.</div>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-dialog v-model="createDialog" max-width="560" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center gap-2">
          <v-icon icon="mdi-information" color="primary" />
          How to create a single memo
        </v-card-title>
        <v-card-text>
          <p class="mb-3">
            Single memos must be created from the correct <strong>quarterly matrix</strong>, not from this list.
            Creating on the wrong matrix causes approval and budget issues.
          </p>
          <ol class="mb-3 ps-4">
            <li class="mb-2">
              Go to <strong>Quarterly Travel Matrices</strong> and open your division's matrix for the
              <strong>current quarter</strong> (<strong>{{ cfg.currentQuarterLabel }}</strong>).
            </li>
            <li class="mb-2">On that matrix page, click <strong>Add Single Memo</strong>.</li>
            <li class="mb-0">Complete the activity form and submit it for approval.</li>
          </ol>
          <v-alert type="warning" variant="tonal" density="compact" class="mb-0 text-body-2">
            Do not create single memos on matrices from past or future quarters. Only the
            <strong>current quarter</strong> matrix is allowed.
          </v-alert>
          <p class="mt-3 mb-0 text-body-2 text-medium-emphasis">{{ matrixStatusNote }}</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="createDialog = false">Close</v-btn>
          <v-btn variant="outlined" color="primary" :href="cfg.routes.matricesIndex" prepend-icon="mdi-grid">
            Go to matrices
          </v-btn>
          <v-btn
            v-if="matrixAcceptsMemos && currentQuarterMatrix && currentQuarterMatrix.show_url"
            color="primary"
            variant="flat"
            :href="currentQuarterMatrix.show_url"
            prepend-icon="mdi-open-in-new"
          >
            Open current quarter matrix
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootSingleMemosIndex);
    }
})();
