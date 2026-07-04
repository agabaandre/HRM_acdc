/**
 * Staff profile — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'staff-show-app';

    function statusColor(status) {
        const s = String(status || '').toLowerCase();
        if (s === 'approved' || s === 'completed' || s === 'active') {
            return 'success';
        }
        if (s === 'pending' || s === 'in-progress' || s === 'due' || s === 'under renewal') {
            return 'warning';
        }
        if (s === 'rejected' || s === 'expired' || s === 'separated') {
            return 'error';
        }
        return 'info';
    }

    function formatLabel(value) {
        return value || 'N/A';
    }

    function bootStaffShow(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed } = Vue;
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
            },
        });

        const app = createApp({
            setup() {
                const staff = ref(cfg.staff || {});
                const activities = ref(cfg.activities || []);
                const routes = cfg.routes || {};

                const activityHeaders = [
                    { title: 'ID', key: 'id', width: 80 },
                    { title: 'Title', key: 'title', minWidth: 220 },
                    { title: 'Date', key: 'date', width: 130 },
                    { title: 'Status', key: 'status', width: 120 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 72 },
                ];

                const personalFields = computed(() => [
                    { label: 'Staff ID', value: formatLabel(staff.value.staff_id) },
                    { label: 'Full name', value: formatLabel(staff.value.full_name) },
                    { label: 'Work email', value: formatLabel(staff.value.work_email) },
                    { label: 'Private email', value: formatLabel(staff.value.private_email) },
                    { label: 'Phone', value: formatLabel(staff.value.tel_1) },
                    { label: 'WhatsApp', value: formatLabel(staff.value.whatsapp) },
                    { label: 'Gender', value: formatLabel(staff.value.gender) },
                ]);

                const employmentFields = computed(() => [
                    { label: 'Division', value: formatLabel(staff.value.division) },
                    { label: 'Directorate', value: formatLabel(staff.value.directorate) },
                    { label: 'Duty station', value: formatLabel(staff.value.duty_station) },
                    { label: 'Salutation', value: formatLabel(staff.value.title) },
                    { label: 'Job name', value: formatLabel(staff.value.job_name) },
                    { label: 'Grade', value: formatLabel(staff.value.grade) },
                    { label: 'Contract type', value: formatLabel(staff.value.contract_type) },
                    { label: 'Contracting institution', value: formatLabel(staff.value.contracting_institution) },
                    { label: 'Nationality', value: formatLabel(staff.value.nationality) },
                    { label: 'Employment status', value: formatLabel(staff.value.status) },
                    { label: 'SAP number', value: formatLabel(staff.value.sap_no) },
                    { label: 'Physical location', value: formatLabel(staff.value.physical_location) },
                    { label: 'Supervisor', value: formatLabel(staff.value.supervisor_name) },
                ]);

                return {
                    staff,
                    activities,
                    routes,
                    activityHeaders,
                    personalFields,
                    employmentFields,
                    statusColor,
                };
            },
            template: `
<v-app class="ss-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h4 class="text-h5 font-weight-bold mb-1">{{ staff.display_name }}</h4>
        <div class="text-body-2 text-medium-emphasis">Staff profile and recent activity</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <v-btn color="primary" variant="flat" :href="routes.index" prepend-icon="mdi-arrow-left">
          Back to list
        </v-btn>
      </div>
    </div>

    <v-row>
      <v-col cols="12" md="4">
        <v-card>
          <v-card-text class="text-center pa-6">
            <v-avatar v-if="staff.photo_url" size="150" class="mb-4">
              <v-img :src="staff.photo_url" :alt="staff.display_name" cover />
            </v-avatar>
            <v-avatar v-else color="primary" size="150" class="mb-4">
              <span class="text-h4 text-white font-weight-bold">{{ staff.initials }}</span>
            </v-avatar>

            <div class="text-h6 font-weight-bold mb-1">{{ staff.display_name }}</div>
            <div class="text-body-2 text-medium-emphasis mb-3">{{ staff.job_name || 'Staff member' }}</div>

            <v-chip
              :color="staff.active ? 'success' : 'error'"
              variant="tonal"
              size="small"
              label
              class="mb-4"
            >
              {{ staff.active ? 'Active' : 'Inactive' }}
            </v-chip>

            <div class="d-grid gap-2">
              <v-btn
                v-if="staff.work_email"
                color="primary"
                variant="outlined"
                block
                :href="'mailto:' + staff.work_email"
                prepend-icon="mdi-email"
              >
                Send email
              </v-btn>
              <v-btn
                v-if="staff.tel_1"
                color="primary"
                variant="outlined"
                block
                :href="'tel:' + staff.tel_1"
                prepend-icon="mdi-phone"
              >
                Call
              </v-btn>
              <v-btn
                v-if="staff.supervisor_url"
                color="secondary"
                variant="text"
                block
                :href="staff.supervisor_url"
                prepend-icon="mdi-account-supervisor"
              >
                View supervisor
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="8">
        <v-card class="mb-4 ss-section-card">
          <v-card-title class="ss-section-title d-flex align-center gap-2">
            <v-icon icon="mdi-account" color="primary" />
            Personal information
          </v-card-title>
          <v-card-text class="pa-4">
            <v-row>
              <v-col
                v-for="field in personalFields"
                :key="field.label"
                cols="12"
                sm="6"
                class="py-2"
              >
                <div class="ss-info-label">{{ field.label }}</div>
                <div class="ss-info-value">{{ field.value }}</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card class="mb-4 ss-section-card">
          <v-card-title class="ss-section-title d-flex align-center gap-2">
            <v-icon icon="mdi-briefcase" color="primary" />
            Employment information
          </v-card-title>
          <v-card-text class="pa-4">
            <v-row>
              <v-col
                v-for="field in employmentFields"
                :key="field.label"
                cols="12"
                sm="6"
                class="py-2"
              >
                <div class="ss-info-label">{{ field.label }}</div>
                <div class="ss-info-value">{{ field.value }}</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card class="ss-section-card">
          <v-card-title class="ss-section-title d-flex align-center gap-2">
            <v-icon icon="mdi-cog" color="primary" />
            System information
          </v-card-title>
          <v-card-text class="pa-4">
            <v-row>
              <v-col cols="12" sm="6" class="py-2">
                <div class="ss-info-label">Last updated</div>
                <div class="ss-info-value">{{ staff.updated_at || 'N/A' }}</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card v-if="activities.length" class="mt-4 ss-section-card">
      <v-card-title class="ss-section-title d-flex align-center gap-2">
        <v-icon icon="mdi-clipboard-list" color="primary" />
        Recent activities
      </v-card-title>
      <v-data-table
        :headers="activityHeaders"
        :items="activities"
        class="ss-activities-table"
        density="comfortable"
        :items-per-page="-1"
        hide-default-footer
      >
        <template #item.title="{ item }">
          <span class="text-truncate d-inline-block" style="max-width: 320px;">{{ item.title }}</span>
        </template>
        <template #item.status="{ item }">
          <v-chip :color="statusColor(item.status)" size="small" variant="tonal" label>
            {{ item.status }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <v-btn
            v-if="item.show_url"
            icon="mdi-eye"
            size="small"
            variant="text"
            color="primary"
            :href="item.show_url"
          />
        </template>
      </v-data-table>
    </v-card>
  </v-container>
</v-app>
            `,
        })
            .use(vuetify);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootStaffShow);
    }
})();
