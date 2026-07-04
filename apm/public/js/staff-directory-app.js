/**
 * Staff directory — Vue 3 + Vuetify 3
 * @see https://vuetifyjs.com/en/components/data-tables/
 */
(function () {
    'use strict';

    const MOUNT_ID = 'staff-directory-app';

    function bootStaffDirectory(mountEl, cfg) {
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
                const items = ref([]);
                const totalItems = ref(0);
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const summary = ref({
                    total_staff: 0,
                    active_staff: 0,
                    inactive_staff: 0,
                    filtered_staff: 0,
                });

                const pageSizeOptions = [
                    { title: '10 per page', value: 10 },
                    { title: '25 per page', value: 25 },
                    { title: '50 per page', value: 50 },
                    { title: '100 per page', value: 100 },
                ];

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Name', key: 'name', sortable: false, minWidth: 200 },
                    { title: 'Division', key: 'division', sortable: false, minWidth: 160 },
                    { title: 'Duty station', key: 'duty_station', sortable: false, minWidth: 140 },
                    { title: 'Job title', key: 'job_title', sortable: false, minWidth: 140 },
                    { title: 'Contact', key: 'contact', sortable: false, minWidth: 180 },
                    { title: 'Status', key: 'status', sortable: false, align: 'center', width: 110 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 72 },
                ];

                const showingRange = computed(() => {
                    if (totalItems.value === 0) {
                        return '0–0';
                    }
                    const start = (page.value - 1) * itemsPerPage.value + 1;
                    const end = Math.min(page.value * itemsPerPage.value, totalItems.value);
                    return `${start}–${end}`;
                });

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function displayName(staff) {
                    const full = [staff.fname, staff.lname, staff.oname].filter(Boolean).join(' ');
                    return staff.title ? `${staff.title} ${full}` : full;
                }

                function initials(staff) {
                    const f = (staff.fname || '').charAt(0);
                    const l = (staff.lname || '').charAt(0);
                    return (f + l).toUpperCase() || '?';
                }

                function staffShowUrl(staffId) {
                    return `${cfg.routes.show}/${staffId}`;
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            search: search.value.trim(),
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                        });
                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            throw new Error(data.error || 'Could not load staff.');
                        }

                        const startIndex = (page.value - 1) * itemsPerPage.value;
                        items.value = (data.data || []).map((staff, index) => ({
                            ...staff,
                            row_num: startIndex + index + 1,
                            display_name: displayName(staff),
                            initials: initials(staff),
                            division_label: staff.division?.division_name || 'N/A',
                            duty_station_label: staff.duty_station_name || 'N/A',
                            job_title_label: staff.job_name || 'N/A',
                            phone_label: staff.tel_1 || 'N/A',
                            is_active: Number(staff.active) === 1,
                        }));

                        totalItems.value = Number(data.recordsTotal || 0);
                        summary.value = {
                            total_staff: data.summary?.total_staff ?? 0,
                            active_staff: data.summary?.active_staff ?? 0,
                            inactive_staff: data.summary?.inactive_staff ?? 0,
                            filtered_staff: data.summary?.filtered_staff ?? totalItems.value,
                        };
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify(e.message || 'Could not load staff directory.');
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

                watch(page, () => {
                    loadItems();
                });

                loadItems();

                const summaryKpis = computed(() => [
                    { key: 'total', icon: 'mdi-account-group', accent: '#119a48', value: summary.value.total_staff, label: 'Total staff' },
                    { key: 'active', icon: 'mdi-account-check', accent: '#15803d', value: summary.value.active_staff, label: 'Active' },
                    { key: 'inactive', icon: 'mdi-account-off', accent: '#64748b', value: summary.value.inactive_staff, label: 'Inactive' },
                    { key: 'filtered', icon: 'mdi-magnify', accent: '#0284c7', value: summary.value.filtered_staff, label: 'Matching search' },
                ]);

                return {
                    cfg,
                    search,
                    page,
                    itemsPerPage,
                    items,
                    totalItems,
                    loading,
                    snackbar,
                    summary,
                    summaryKpis,
                    headers,
                    pageSizeOptions,
                    showingRange,
                    staffShowUrl,
                    loadItems,
                };
            },
            template: `
<v-app class="sd-vuetify-app">
  <v-container fluid class="pa-0">
    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4 px-md-6">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-account-group" color="primary" class="me-2"></v-icon>
            Staff directory
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            View and search active staff across the organization
          </div>
        </div>
        <div class="d-flex flex-wrap align-center gap-2">
          <v-text-field
            v-model="search"
            placeholder="Search name, division, job title…"
            prepend-inner-icon="mdi-magnify"
            clearable
            style="min-width: 260px; max-width: 360px;"
            @click:clear="search = ''"
          ></v-text-field>
          <v-select
            v-model="itemsPerPage"
            :items="pageSizeOptions"
            item-title="title"
            item-value="value"
            style="width: 140px;"
          ></v-select>
          <v-menu>
            <template #activator="{ props }">
              <v-btn v-bind="props" variant="outlined" color="primary" prepend-icon="mdi-download">
                Export
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item :href="cfg.routes.exportExcel" prepend-icon="mdi-file-excel" title="Excel"></v-list-item>
              <v-list-item :href="cfg.routes.exportPdf" prepend-icon="mdi-file-pdf-box" title="PDF"></v-list-item>
            </v-list>
          </v-menu>
        </div>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="px-4 px-md-6 pt-4">
        <v-row dense class="mb-4 sd-kpi-row">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="sd-kpi-card" elevation="0" :style="{ '--sd-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div
                  class="sd-kpi-icon-wrap me-3 flex-shrink-0"
                  :style="{ background: kpi.accent + '14', color: kpi.accent }"
                >
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div class="min-w-0">
                  <div class="sd-kpi-value">{{ kpi.value }}</div>
                  <div class="sd-kpi-label">{{ kpi.label }}</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-data-table
          class="sd-staff-table elevation-0 border rounded-lg"
          :headers="headers"
          :items="items"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
          item-value="staff_id"
        >
          <template #item.row_num="{ item }">
            <v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip>
          </template>

          <template #item.name="{ item }">
            <div class="d-flex align-center py-1">
              <v-avatar color="primary" size="36" class="me-3">
                <span class="text-caption font-weight-bold text-white">{{ item.initials }}</span>
              </v-avatar>
              <div>
                <div class="font-weight-medium">{{ item.display_name }}</div>
                <div v-if="item.work_email" class="text-caption text-medium-emphasis">{{ item.work_email }}</div>
              </div>
            </div>
          </template>

          <template #item.division="{ item }">
            <span class="text-body-2">{{ item.division_label }}</span>
          </template>

          <template #item.duty_station="{ item }">
            <span class="text-body-2">{{ item.duty_station_label }}</span>
          </template>

          <template #item.job_title="{ item }">
            <span class="text-body-2">{{ item.job_title_label }}</span>
          </template>

          <template #item.contact="{ item }">
            <div class="text-body-2">
              <div class="d-flex align-center">
                <v-icon icon="mdi-phone" size="small" class="me-1 text-medium-emphasis"></v-icon>
                {{ item.phone_label }}
              </div>
              <div v-if="item.work_email" class="d-flex align-center mt-1">
                <v-icon icon="mdi-email-outline" size="small" class="me-1 text-medium-emphasis"></v-icon>
                {{ item.work_email }}
              </div>
            </div>
          </template>

          <template #item.status="{ item }">
            <v-chip
              :color="item.is_active ? 'success' : 'error'"
              size="small"
              variant="tonal"
              label
            >
              {{ item.is_active ? 'Active' : 'Inactive' }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <v-btn
              :href="staffShowUrl(item.staff_id)"
              icon="mdi-eye-outline"
              variant="text"
              color="primary"
              size="small"
              title="View profile"
            ></v-btn>
          </template>

          <template #no-data>
            <v-alert type="info" variant="tonal" class="ma-4">
              No staff found. Try adjusting your search.
            </v-alert>
          </template>

          <template #bottom>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
              <span class="text-body-2 text-medium-emphasis">
                Showing {{ showingRange }} of {{ totalItems }} staff
              </span>
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

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
            `,
        })
            .use(vuetify);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootStaffDirectory);
    }
})();
