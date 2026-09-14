/**
 * Directorates directory — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'directorates-app';

    function bootDirectorates(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, watch, computed } = Vue;
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
            },
        });

        const app = createApp({
            setup() {
                const search = ref('');
                const status = ref('');
                const page = ref(1);
                const itemsPerPage = ref(25);
                const sortBy = ref([{ key: 'created_at', order: 'desc' }]);
                const items = ref([]);
                const totalItems = ref(0);
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const summary = ref({
                    total_directorates: 0,
                    active_directorates: 0,
                    inactive_directorates: 0,
                    filtered_directorates: 0,
                });

                const statusItems = [
                    { title: 'All status', value: '' },
                    { title: 'Active', value: 'active' },
                    { title: 'Inactive', value: 'inactive' },
                ];

                const pageSizeOptions = [
                    { title: '10 per page', value: 10 },
                    { title: '25 per page', value: 25 },
                    { title: '50 per page', value: 50 },
                ];

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Name', key: 'name', sortable: true, minWidth: 180 },
                    { title: 'Code', key: 'code', sortable: true, width: 100 },
                    { title: 'Director', key: 'director_name', sortable: false, minWidth: 160 },
                    { title: 'Status', key: 'is_active', sortable: true, align: 'center', width: 110 },
                    { title: 'Created', key: 'created_at', sortable: true, width: 150 },
                    { title: 'Updated', key: 'updated_at', sortable: true, width: 150 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 72 },
                ];

                const showingRange = computed(() => {
                    if (totalItems.value === 0) return '0–0';
                    const start = (page.value - 1) * itemsPerPage.value + 1;
                    const end = Math.min(page.value * itemsPerPage.value, totalItems.value);
                    return `${start}–${end}`;
                });

                const summaryKpis = computed(() => [
                    { key: 'total', icon: 'mdi-domain', accent: '#119a48', value: summary.value.total_directorates, label: 'Total directorates' },
                    { key: 'active', icon: 'mdi-check-circle', accent: '#15803d', value: summary.value.active_directorates, label: 'Active' },
                    { key: 'inactive', icon: 'mdi-close-circle', accent: '#64748b', value: summary.value.inactive_directorates, label: 'Inactive' },
                    { key: 'filtered', icon: 'mdi-magnify', accent: '#0284c7', value: summary.value.filtered_directorates, label: 'Matching filters' },
                ]);

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function directorateShowUrl(id) {
                    return `${cfg.routes.show}/${id}`;
                }

                function formatDate(value) {
                    if (!value) return '—';
                    const d = new Date(value);
                    if (Number.isNaN(d.getTime())) return String(value);
                    return d.toLocaleString(undefined, {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: '2-digit', minute: '2-digit',
                    });
                }

                function mapRow(row, index) {
                    const director = row.director;
                    const directorName = director
                        ? [director.lname, director.fname].filter(Boolean).join(' ').trim()
                        : '';
                    return {
                        ...row,
                        row_num: (page.value - 1) * itemsPerPage.value + index + 1,
                        director_name: directorName || '—',
                        created_label: formatDate(row.created_at),
                        updated_label: formatDate(row.updated_at),
                        is_active_bool: Number(row.is_active) === 1,
                    };
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                            search: search.value.trim(),
                            status: status.value,
                        });
                        const sort = sortBy.value[0];
                        if (sort?.key) {
                            params.set('sort_by', sort.key);
                            params.set('sort_direction', sort.order === 'desc' ? 'desc' : 'asc');
                        }
                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Could not load directorates.');

                        items.value = (data.data || []).map(mapRow);
                        totalItems.value = Number(data.recordsTotal || 0);
                        summary.value = {
                            total_directorates: data.summary?.total_directorates ?? totalItems.value,
                            active_directorates: data.summary?.active_directorates ?? 0,
                            inactive_directorates: data.summary?.inactive_directorates ?? 0,
                            filtered_directorates: data.summary?.filtered_directorates ?? totalItems.value,
                        };
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify(e.message || 'Could not load directorates.');
                    } finally {
                        loading.value = false;
                    }
                }

                let searchTimer = null;
                watch(search, () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        page.value = 1;
                        loadItems();
                    }, 400);
                });

                watch(status, () => {
                    page.value = 1;
                    loadItems();
                });

                watch(itemsPerPage, () => {
                    page.value = 1;
                    loadItems();
                });

                watch(page, () => loadItems());

                watch(sortBy, () => {
                    page.value = 1;
                    loadItems();
                }, { deep: true });

                loadItems();

                return {
                    cfg,
                    search,
                    status,
                    statusItems,
                    page,
                    itemsPerPage,
                    sortBy,
                    items,
                    totalItems,
                    loading,
                    snackbar,
                    summaryKpis,
                    headers,
                    pageSizeOptions,
                    showingRange,
                    directorateShowUrl,
                };
            },
            template: `
<v-app class="dr-vuetify-app">
  <v-container fluid class="pa-0">
    <v-alert v-if="cfg.flash?.success" type="success" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.success }}</v-alert>
    <v-alert v-if="cfg.flash?.error" type="error" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.error }}</v-alert>

    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4 px-md-6">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-domain" color="primary" class="me-2"></v-icon>
            Directorates
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            Manage directorates and assigned directors
          </div>
        </div>
        <div class="d-flex flex-wrap align-center gap-2">
          <v-text-field
            v-model="search"
            placeholder="Search name or code…"
            prepend-inner-icon="mdi-magnify"
            clearable
            style="min-width: 220px; max-width: 300px;"
            @click:clear="search = ''"
          ></v-text-field>
          <v-select v-model="status" :items="statusItems" item-title="title" item-value="value" style="width: 150px;"></v-select>
          <v-select v-model="itemsPerPage" :items="pageSizeOptions" item-title="title" item-value="value" style="width: 140px;"></v-select>
          <v-btn :href="cfg.routes.create" color="primary" prepend-icon="mdi-plus">Add directorate</v-btn>
        </div>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="px-4 px-md-6 pt-4">
        <v-row dense class="mb-4 dr-kpi-row">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="dr-kpi-card" elevation="0" :style="{ '--dr-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div class="dr-kpi-icon-wrap me-3 flex-shrink-0" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div class="min-w-0">
                  <div class="dr-kpi-value">{{ kpi.value }}</div>
                  <div class="dr-kpi-label">{{ kpi.label }}</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-data-table
          v-model:sort-by="sortBy"
          class="dr-table elevation-0 border rounded-lg"
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
          must-sort
        >
          <template #item.row_num="{ item }">
            <v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip>
          </template>
          <template #item.name="{ item }">
            <span class="font-weight-medium">{{ item.name }}</span>
          </template>
          <template #item.code="{ item }">
            <v-chip size="small" variant="tonal" color="info">{{ item.code }}</v-chip>
          </template>
          <template #item.director_name="{ item }">
            <span class="text-body-2 text-nowrap">{{ item.director_name }}</span>
          </template>
          <template #item.is_active="{ item }">
            <v-chip :color="item.is_active_bool ? 'success' : 'error'" size="small" variant="tonal" label>
              {{ item.is_active_bool ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>
          <template #item.created_at="{ item }">
            <span class="text-body-2 text-nowrap">{{ item.created_label }}</span>
          </template>
          <template #item.updated_at="{ item }">
            <span class="text-body-2 text-nowrap">{{ item.updated_label }}</span>
          </template>
          <template #item.actions="{ item }">
            <v-btn :href="directorateShowUrl(item.id)" icon="mdi-eye-outline" variant="text" color="primary" size="small" title="View directorate"></v-btn>
          </template>
          <template #no-data>
            <v-alert type="info" variant="tonal" class="ma-4">No directorates found. Try adjusting your filters.</v-alert>
          </template>
          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
              <span class="text-body-2 text-medium-emphasis">Showing {{ showingRange }} of {{ totalItems }} directorates</span>
              <v-pagination
                v-model="page"
                :length="Math.max(1, Math.ceil(totalItems / itemsPerPage))"
                :total-visible="7"
                density="comfortable"
                rounded="circle"
                active-color="primary"
              ></v-pagination>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDirectorates);
    }
})();
