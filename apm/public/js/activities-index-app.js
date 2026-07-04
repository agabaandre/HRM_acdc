/**
 * Activities index — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'activities-index-app';

    const FILTER_KEYS = [
        'year', 'quarter', 'division_id', 'document_number',
        'staff_id', 'status', 'search', 'fund_type_id',
    ];

    const SELECT_FILTER_KEYS = [
        'year', 'quarter', 'division_id', 'staff_id', 'status', 'fund_type_id',
    ];

    const TEXT_FILTER_KEYS = ['search', 'document_number'];

    function readInitialState(cfg) {
        const url = new URLSearchParams(window.location.search);
        const pick = (key, fallback) => (url.has(key) ? (url.get(key) || '') : fallback);

        return {
            tab: pick('tab', cfg.defaults?.tab || 'my-division'),
            year: pick('year', String(cfg.defaults?.year ?? cfg.currentYear ?? '')),
            quarter: pick('quarter', cfg.defaults?.quarter ?? ''),
            division_id: pick('division_id', cfg.defaults?.division_id ?? ''),
            document_number: pick('document_number', cfg.defaults?.document_number ?? ''),
            staff_id: pick('staff_id', cfg.defaults?.staff_id ?? ''),
            status: pick('status', cfg.defaults?.status ?? ''),
            search: pick('search', cfg.defaults?.search ?? ''),
            fund_type_id: pick('fund_type_id', cfg.defaults?.fund_type_id ?? ''),
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
        ['tab', 'page', ...FILTER_KEYS].forEach((k) => current.searchParams.delete(k));
        FILTER_KEYS.forEach((key) => {
            if (filters[key]) current.searchParams.set(key, filters[key]);
        });
        if (tab) current.searchParams.set('tab', tab);
        if (page > 1) current.searchParams.set('page', String(page));
        window.history.replaceState({}, '', current.toString());
    }

    function bootActivitiesIndex(mountEl, cfg) {
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
                    document_number: initial.document_number,
                    staff_id: initial.staff_id,
                    status: initial.status,
                    search: initial.search,
                    fund_type_id: initial.fund_type_id,
                });
                const page = ref(initial.page);
                const itemsPerPage = ref(cfg.perPage || 20);
                const items = ref([]);
                const pagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const counts = ref({
                    all_activities: cfg.counts?.all_activities ?? 0,
                    my_division: cfg.counts?.my_division ?? 0,
                    shared_activities: cfg.counts?.shared_activities ?? 0,
                });
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Title', key: 'activity_title', sortable: false, minWidth: 200 },
                    { title: 'Matrix', key: 'matrix_label', sortable: false, width: 100 },
                    { title: 'Division', key: 'division_name', sortable: false, minWidth: 140 },
                    { title: 'Doc #', key: 'document_number', sortable: false, width: 110 },
                    { title: 'Responsible', key: 'responsible_person_name', sortable: false, minWidth: 140 },
                    { title: 'Dates', key: 'date_range', sortable: false, width: 150 },
                    { title: 'Fund type', key: 'fund_type_name', sortable: false, width: 120 },
                    { title: 'Status', key: 'overall_status', sortable: false, minWidth: 160 },
                    { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 140 },
                ];

                const showingRange = computed(() => {
                    if (pagination.value.total === 0) return '0–0';
                    return `${pagination.value.from}–${pagination.value.to}`;
                });

                const tabTitle = computed(() => {
                    if (activeTab.value === 'all-activities') return 'All activities';
                    if (activeTab.value === 'shared-activities') return 'Shared activities';
                    return 'My division activities';
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
                            throw new Error(data.error || 'Could not load activities.');
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
                                all_activities: data.counts.all_activities ?? counts.value.all_activities,
                                my_division: data.counts.my_division ?? counts.value.my_division,
                                shared_activities: data.counts.shared_activities ?? counts.value.shared_activities,
                            };
                        }

                        syncUrl(filters.value, activeTab.value, page.value);
                    } catch (e) {
                        items.value = [];
                        pagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Could not load activities.');
                    } finally {
                        loading.value = false;
                    }
                }

                let textDebounceTimer = null;

                function applyFilters() {
                    clearTimeout(textDebounceTimer);
                    page.value = 1;
                    loadItems();
                }

                function confirmDelete(event) {
                    if (!window.confirm('Are you sure you want to delete this activity? This action cannot be undone.')) {
                        event.preventDefault();
                    }
                }

                SELECT_FILTER_KEYS.forEach((key) => {
                    watch(() => filters.value[key], () => {
                        page.value = 1;
                        loadItems();
                    });
                });

                TEXT_FILTER_KEYS.forEach((key) => {
                    watch(() => filters.value[key], () => {
                        clearTimeout(textDebounceTimer);
                        textDebounceTimer = setTimeout(() => {
                            page.value = 1;
                            loadItems();
                        }, 400);
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
                    tabTitle,
                    statusColor,
                    applyFilters,
                    confirmDelete,
                };
            },
            template: `
<v-app class="ai-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center gap-2 py-4">
        <v-icon icon="mdi-filter-outline" color="primary" />
        <span class="text-h6 font-weight-bold">Filter activities</span>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6" md="4" lg="3">
            <v-text-field
              v-model="filters.search"
              label="Search title"
              prepend-inner-icon="mdi-magnify"
              clearable
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-text-field
              v-model="filters.document_number"
              label="Document #"
              prepend-inner-icon="mdi-file-document-outline"
              clearable
            />
          </v-col>
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
              label="Responsible staff"
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
              prepend-inner-icon="mdi-flag-outline"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2">
            <v-select
              v-model="filters.fund_type_id"
              :items="cfg.fundTypeOptions || []"
              item-title="title"
              item-value="value"
              label="Fund type"
              prepend-inner-icon="mdi-cash"
            />
          </v-col>
          <v-col cols="12" sm="6" md="4" lg="2" class="d-flex align-center">
            <v-btn color="primary" variant="flat" prepend-icon="mdi-magnify" block @click="applyFilters">
              Filter
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-tabs v-model="activeTab" color="primary" grow>
        <v-tab v-if="cfg.canViewAllActivities" value="all-activities">
          <v-icon start icon="mdi-view-list" />
          All activities
          <v-chip size="x-small" color="primary" class="ms-2">{{ counts.all_activities }}</v-chip>
        </v-tab>
        <v-tab value="my-division">
          <v-icon start icon="mdi-home" />
          My division
          <v-chip size="x-small" color="success" class="ms-2">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab value="shared-activities">
          <v-icon start icon="mdi-share-variant" />
          Shared activities
          <v-chip size="x-small" color="info" class="ms-2">{{ counts.shared_activities }}</v-chip>
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
          class="ai-list-table"
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
            <div class="font-weight-medium">{{ item.activity_title }}</div>
            <v-chip
              v-if="item.is_single_memo"
              size="x-small"
              color="warning"
              variant="flat"
              class="mt-1"
            >
              SM
            </v-chip>
          </template>

          <template #item.matrix_label="{ item }">
            <a
              v-if="item.matrix_url"
              :href="item.matrix_url"
              class="text-primary text-decoration-none font-weight-medium"
            >
              {{ item.matrix_label }}
            </a>
            <span v-else class="text-medium-emphasis">{{ item.matrix_label }}</span>
          </template>

          <template #item.document_number="{ item }">
            <span v-if="item.document_number">{{ item.document_number }}</span>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <template #item.responsible_person_name="{ item }">
            <span v-if="item.responsible_person_name">{{ item.responsible_person_name }}</span>
            <span v-else class="text-medium-emphasis">Not assigned</span>
          </template>

          <template #item.date_range="{ item }">
            <span v-if="item.date_range">{{ item.date_range }}</span>
            <span v-else class="text-medium-emphasis">Dates not set</span>
          </template>

          <template #item.overall_status="{ item }">
            <v-chip :color="statusColor(item.overall_status)" size="small" variant="flat" class="text-uppercase mb-1">
              {{ item.overall_status }}
            </v-chip>
            <div v-if="item.overall_status === 'pending' && item.workflow_role" class="text-caption font-weight-medium">
              {{ item.workflow_role }}
            </div>
            <div v-if="item.overall_status === 'pending' && item.current_actor_name" class="text-caption text-medium-emphasis">
              {{ item.current_actor_name }}
            </div>
          </template>

          <template #item.actions="{ item }">
            <div class="d-flex gap-1 justify-end flex-wrap">
              <v-btn
                icon="mdi-eye"
                size="small"
                variant="text"
                color="info"
                :href="item.show_url"
                title="Open"
              />
              <form
                v-if="item.delete_url"
                :action="item.delete_url"
                method="POST"
                class="d-inline"
                @submit="confirmDelete"
              >
                <input type="hidden" name="_token" :value="cfg.csrf">
                <input type="hidden" name="_method" value="DELETE">
                <v-btn
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  type="submit"
                  title="Delete"
                />
              </form>
              <v-btn
                v-if="item.print_url"
                icon="mdi-printer"
                size="small"
                variant="text"
                color="success"
                :href="item.print_url"
                target="_blank"
                title="Print"
              />
            </div>
          </template>

          <template #no-data>
            <div class="text-center py-8 text-medium-emphasis">
              <v-icon icon="mdi-clipboard-text-off" size="48" class="mb-2 opacity-50" />
              <div>No activities found for the selected filters.</div>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootActivitiesIndex);
    }
})();
