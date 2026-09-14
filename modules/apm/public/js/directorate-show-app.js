/**
 * Directorate detail — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'directorate-show-app';

    function bootDirectorateShow(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, onMounted } = Vue;
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
                const directorate = ref(cfg.directorate || {});
                const divisions = ref(cfg.divisions || []);
                const routes = cfg.routes || {};
                const snackbar = ref({ show: false, text: '', color: 'success' });

                const divisionHeaders = [
                    { title: 'Division name', key: 'division_name', minWidth: 200 },
                    { title: 'Code', key: 'code', width: 120 },
                    { title: 'Status', key: 'is_active', width: 110 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 90 },
                ];

                const statusChip = computed(() => directorate.value.is_active
                    ? { text: 'Active', color: 'success' }
                    : { text: 'Inactive', color: 'error' });

                function divisionShowUrl(id) {
                    return `${routes.divisionsShow}/${id}`;
                }

                onMounted(() => {
                    if (cfg.flash?.success) {
                        snackbar.value = { show: true, text: cfg.flash.success, color: 'success' };
                    }
                });

                return {
                    directorate,
                    divisions,
                    routes,
                    snackbar,
                    divisionHeaders,
                    statusChip,
                    divisionShowUrl,
                };
            },
            template: `
<v-app class="drs-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h4 class="text-h5 font-weight-bold mb-1">{{ directorate.name }}</h4>
        <div class="text-body-2 text-medium-emphasis">Directorate details and related divisions</div>
      </div>
      <v-btn color="primary" variant="flat" :href="routes.index" prepend-icon="mdi-arrow-left">
        Back to directorates
      </v-btn>
    </div>

    <v-row>
      <v-col cols="12" md="8">
        <v-card class="mb-4">
          <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
            <span class="text-subtitle-1 font-weight-medium">
              <v-icon icon="mdi-information-outline" class="me-2" color="info"></v-icon>Directorate information
            </span>
            <v-chip :color="statusChip.color" variant="tonal" size="small">{{ statusChip.text }}</v-chip>
          </v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis text-uppercase">ID</div>
                <div class="text-h6 font-weight-bold">{{ directorate.id }}</div>
              </v-col>
              <v-col cols="12" sm="6">
                <div class="text-caption text-medium-emphasis text-uppercase">Code</div>
                <div class="font-weight-medium">{{ directorate.code || '—' }}</div>
              </v-col>
              <v-col cols="12">
                <div class="text-caption text-medium-emphasis text-uppercase">Directorate name</div>
                <div class="text-h6">{{ directorate.name }}</div>
              </v-col>
              <v-col cols="12" v-if="directorate.description">
                <div class="text-caption text-medium-emphasis text-uppercase">Description</div>
                <div class="text-body-2">{{ directorate.description }}</div>
              </v-col>
              <v-col cols="12">
                <div class="text-caption text-medium-emphasis text-uppercase">Director</div>
                <template v-if="directorate.director">
                  <div class="text-h6 mb-0">{{ directorate.director.name }}</div>
                  <div class="text-caption text-medium-emphasis">
                    {{ directorate.director.title }} · Staff ID {{ directorate.director.staff_id }}
                  </div>
                </template>
                <span v-else class="text-medium-emphasis">Not assigned</span>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card v-if="divisions.length">
          <v-card-title class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-office-building" class="me-2" color="primary"></v-icon>Related divisions
            <v-chip size="x-small" class="ms-2" variant="tonal">{{ divisions.length }}</v-chip>
          </v-card-title>
          <v-data-table
            :headers="divisionHeaders"
            :items="divisions"
            density="comfortable"
            class="border-0"
            hide-default-footer
            :items-per-page="-1"
          >
            <template #item.is_active="{ item }">
              <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'" variant="tonal">
                {{ item.is_active ? 'Active' : 'Inactive' }}
              </v-chip>
            </template>
            <template #item.actions="{ item }">
              <v-btn
                :href="divisionShowUrl(item.id)"
                size="small"
                variant="tonal"
                color="info"
                icon="mdi-eye"
                title="View division"
              ></v-btn>
            </template>
          </v-data-table>
        </v-card>
        <v-alert v-else type="info" variant="tonal">No divisions are linked to this directorate.</v-alert>
      </v-col>

      <v-col cols="12" md="4">
        <v-card>
          <v-card-title class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-clock-outline" class="me-2" color="primary"></v-icon>Timestamps
          </v-card-title>
          <v-list density="compact" class="py-0">
            <v-list-item>
              <v-list-item-title class="text-medium-emphasis">Created at</v-list-item-title>
              <template #append>{{ directorate.created_at || '—' }}</template>
            </v-list-item>
            <v-list-item>
              <v-list-item-title class="text-medium-emphasis">Last updated</v-list-item-title>
              <template #append>{{ directorate.updated_at || '—' }}</template>
            </v-list-item>
          </v-list>
        </v-card>
      </v-col>
    </v-row>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
            `,
        });

        app.use(vuetify);
        app.mount(mountEl);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
    }

    window.ApmVuetifyPage.bind(MOUNT_ID, bootDirectorateShow);
})();
