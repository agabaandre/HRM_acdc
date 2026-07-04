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
        <v-btn
          v-if="cfg.isFocalPerson"
          color="primary"
          variant="flat"
          :href="cfg.routes.create"
          prepend-icon="mdi-plus"
        >
          Create new matrix
        </v-btn>
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
      <v-tabs v-model="activeTab" color="primary" grow>
        <v-tab v-if="showMyDivisionTab" value="myDivision">
          <v-icon start icon="mdi-home" />
          My division
          <v-chip size="x-small" color="success" class="ms-2">{{ counts.my_division }}</v-chip>
        </v-tab>
        <v-tab v-if="cfg.canViewAllMatrices" value="allMatrices">
          <v-icon start icon="mdi-grid" />
          All matrices
          <v-chip size="x-small" color="primary" class="ms-2">{{ counts.all }}</v-chip>
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
