/**
 * Divisions directory — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'divisions-app';

    function staffLabel(rel) {
        if (!rel) return { name: 'N/A', subtitle: 'Staff' };
        const name = [rel.fname, rel.lname].filter(Boolean).join(' ').trim() || 'N/A';
        return { name, subtitle: rel.position || 'Staff' };
    }

    function bootDivisions(mountEl, cfg) {
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
                const page = ref(1);
                const itemsPerPage = ref(25);
                const sortBy = ref([{ key: 'division_name', order: 'asc' }]);
                const items = ref([]);
                const totalItems = ref(0);
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const summary = ref({ total_divisions: 0, filtered_divisions: 0 });

                const pageSizeOptions = [
                    { title: '10 per page', value: 10 },
                    { title: '25 per page', value: 25 },
                    { title: '50 per page', value: 50 },
                ];

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Division name', key: 'division_name', sortable: true, minWidth: 160 },
                    { title: 'Short name', key: 'division_short_name', sortable: true, width: 120 },
                    { title: 'Category', key: 'category', sortable: true, width: 120 },
                    { title: 'Division head', key: 'division_head', sortable: false, minWidth: 160 },
                    { title: 'Focal person', key: 'focal_person', sortable: false, minWidth: 160 },
                    { title: 'Admin assistant', key: 'admin_assistant', sortable: false, minWidth: 160 },
                    { title: 'Finance officer', key: 'finance_officer', sortable: false, minWidth: 160 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 72 },
                ];

                const showingRange = computed(() => {
                    if (totalItems.value === 0) return '0–0';
                    const start = (page.value - 1) * itemsPerPage.value + 1;
                    const end = Math.min(page.value * itemsPerPage.value, totalItems.value);
                    return `${start}–${end}`;
                });

                const summaryKpis = computed(() => [
                    { key: 'total', icon: 'mdi-office-building', accent: '#119a48', value: summary.value.total_divisions, label: 'Total divisions' },
                    { key: 'filtered', icon: 'mdi-magnify', accent: '#0284c7', value: summary.value.filtered_divisions, label: 'Matching search' },
                ]);

                const exportUrl = computed(() => {
                    const params = new URLSearchParams();
                    if (search.value.trim()) params.set('search', search.value.trim());
                    const sort = sortBy.value[0];
                    if (sort?.key) {
                        params.set('sort_by', sort.key);
                        params.set('sort_direction', sort.order === 'desc' ? 'desc' : 'asc');
                    }
                    const qs = params.toString();
                    return qs ? `${cfg.routes.exportExcel}?${qs}` : cfg.routes.exportExcel;
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function divisionShowUrl(id) {
                    return `${cfg.routes.show}/${id}`;
                }

                function mapRow(division, index) {
                    const head = staffLabel(division.division_head || division.divisionHead);
                    const focal = staffLabel(division.focal_person || division.focalPerson);
                    const admin = staffLabel(division.admin_assistant || division.adminAssistant);
                    const finance = staffLabel(division.finance_officer || division.financeOfficer);
                    return {
                        ...division,
                        row_num: (page.value - 1) * itemsPerPage.value + index + 1,
                        head_name: head.name,
                        head_subtitle: head.subtitle,
                        focal_name: focal.name,
                        focal_subtitle: focal.subtitle,
                        admin_name: admin.name,
                        admin_subtitle: admin.subtitle,
                        finance_name: finance.name,
                        finance_subtitle: finance.subtitle,
                    };
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                            search: search.value.trim(),
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
                        if (!res.ok) throw new Error(data.error || 'Could not load divisions.');

                        items.value = (data.data || []).map(mapRow);
                        totalItems.value = Number(data.recordsTotal || 0);
                        summary.value = {
                            total_divisions: data.summary?.total_divisions ?? totalItems.value,
                            filtered_divisions: data.summary?.filtered_divisions ?? totalItems.value,
                        };
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify(e.message || 'Could not load divisions.');
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
                    exportUrl,
                    divisionShowUrl,
                };
            },
            template: `
<v-app class="dv-vuetify-app">
  <v-container fluid class="pa-0">
    <v-alert v-if="cfg.flash?.success" type="success" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.success }}</v-alert>
    <v-alert v-if="cfg.flash?.error" type="error" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.error }}</v-alert>

    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4 px-md-6">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-office-building" color="primary" class="me-2"></v-icon>
            Divisions
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            View divisions and key contacts — managed in the main system
          </div>
        </div>
        <div class="d-flex flex-wrap align-center gap-2">
          <v-text-field
            v-model="search"
            placeholder="Search divisions…"
            prepend-inner-icon="mdi-magnify"
            clearable
            style="min-width: 240px; max-width: 320px;"
            @click:clear="search = ''"
          ></v-text-field>
          <v-select v-model="itemsPerPage" :items="pageSizeOptions" item-title="title" item-value="value" style="width: 140px;"></v-select>
          <v-btn :href="exportUrl" variant="outlined" color="success" prepend-icon="mdi-file-excel">Export</v-btn>
        </div>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="px-4 px-md-6 pt-4">
        <v-row dense class="mb-4 dv-kpi-row">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="dv-kpi-card" elevation="0" :style="{ '--dv-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div class="dv-kpi-icon-wrap me-3 flex-shrink-0" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div class="min-w-0">
                  <div class="dv-kpi-value">{{ kpi.value }}</div>
                  <div class="dv-kpi-label">{{ kpi.label }}</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-data-table
          v-model:sort-by="sortBy"
          class="dv-table elevation-0 border rounded-lg"
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
          <template #item.division_name="{ item }">
            <span class="font-weight-medium text-wrap">{{ item.division_name }}</span>
          </template>
          <template #item.division_short_name="{ item }">
            <v-chip v-if="item.division_short_name" size="small" color="primary" variant="tonal">{{ item.division_short_name }}</v-chip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #item.category="{ item }">
            <v-chip v-if="item.category" size="small" color="secondary" variant="tonal">{{ item.category }}</v-chip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>
          <template #item.division_head="{ item }">
            <div>
              <div class="text-body-2">{{ item.head_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.head_subtitle }}</div>
            </div>
          </template>
          <template #item.focal_person="{ item }">
            <div>
              <div class="text-body-2">{{ item.focal_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.focal_subtitle }}</div>
            </div>
          </template>
          <template #item.admin_assistant="{ item }">
            <div>
              <div class="text-body-2">{{ item.admin_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.admin_subtitle }}</div>
            </div>
          </template>
          <template #item.finance_officer="{ item }">
            <div>
              <div class="text-body-2">{{ item.finance_name }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.finance_subtitle }}</div>
            </div>
          </template>
          <template #item.actions="{ item }">
            <v-btn :href="divisionShowUrl(item.id)" icon="mdi-eye-outline" variant="text" color="primary" size="small" title="View division"></v-btn>
          </template>
          <template #no-data>
            <v-alert type="info" variant="tonal" class="ma-4">No divisions found. Try adjusting your search.</v-alert>
          </template>
          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
              <span class="text-body-2 text-medium-emphasis">Showing {{ showingRange }} of {{ totalItems }} divisions</span>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDivisions);
    }
})();
