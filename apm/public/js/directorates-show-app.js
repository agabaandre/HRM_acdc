/**
 * Directorate detail — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'directorates-show-app';

    function activeStatusColor(isActive) {
        if (isActive === true) return 'success';
        if (isActive === false) return 'error';
        return 'secondary';
    }

    function activeStatusLabel(isActive) {
        if (isActive === true) return 'Active';
        if (isActive === false) return 'Inactive';
        return 'Unknown';
    }

    function bootDirectorateShow(mountEl, cfg) {
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
                const directorate = ref(cfg.directorate || {});
                const director = ref(cfg.director || null);
                const divisions = ref(cfg.divisions || []);
                const routes = ref(cfg.routes || {});

                const divisionHeaders = [
                    { title: 'Division', key: 'division_name', minWidth: 200 },
                    { title: 'Short name', key: 'short_name', width: 120 },
                    { title: 'Category', key: 'category', width: 130 },
                    { title: 'Status', key: 'is_active', align: 'center', width: 110 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 90 },
                ];

                const infoFields = computed(() => [
                    { label: 'ID', value: directorate.value.id },
                    { label: 'Code', value: directorate.value.code || '—' },
                    { label: 'Description', value: directorate.value.description || '—' },
                ]);

                const timestampFields = computed(() => [
                    { label: 'Created at', value: directorate.value.created_at || '—' },
                    { label: 'Last updated', value: directorate.value.updated_at || '—' },
                ]);

                return {
                    directorate,
                    director,
                    divisions,
                    routes,
                    divisionHeaders,
                    infoFields,
                    timestampFields,
                    activeStatusColor,
                    activeStatusLabel,
                };
            },
            template: `
<v-app class="dr-show-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h4 class="text-h5 font-weight-bold mb-1">{{ directorate.name }}</h4>
        <div class="text-body-2 text-medium-emphasis">Directorate details and related divisions</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <v-btn color="primary" variant="flat" :href="routes.index" prepend-icon="mdi-arrow-left">Back to list</v-btn>
        <v-btn color="primary" variant="outlined" :href="routes.edit" prepend-icon="mdi-pencil">Edit</v-btn>
      </div>
    </div>

    <v-row>
      <v-col cols="12" lg="8">
        <v-card class="dr-show-section-card mb-4">
          <v-card-title class="dr-show-section-title d-flex flex-wrap align-center justify-space-between gap-2">
            <span><v-icon icon="mdi-domain" class="me-2" color="primary"></v-icon>Directorate information</span>
            <v-chip :color="activeStatusColor(directorate.is_active)" variant="flat" size="small">
              {{ activeStatusLabel(directorate.is_active) }}
            </v-chip>
          </v-card-title>
          <v-card-text class="pt-4">
            <v-row>
              <v-col v-for="field in infoFields" :key="field.label" cols="12" sm="6">
                <div class="dr-show-info-label">{{ field.label }}</div>
                <div class="dr-show-info-value">{{ field.value }}</div>
              </v-col>
              <v-col cols="12">
                <div class="dr-show-info-label">Director</div>
                <div v-if="director" class="dr-show-info-value">
                  <a :href="director.show_url" class="text-decoration-none text-primary font-weight-medium">{{ director.name }}</a>
                  <span class="text-medium-emphasis text-body-2 ms-2">Staff ID {{ director.staff_id }}</span>
                </div>
                <div v-else class="dr-show-info-value text-medium-emphasis">Not assigned</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card v-if="divisions.length" class="dr-show-section-card">
          <v-card-title class="dr-show-section-title">
            <v-icon icon="mdi-office-building" class="me-2" color="primary"></v-icon>
            Related divisions
            <v-chip class="ms-2" size="small" color="primary" variant="flat">{{ divisions.length }}</v-chip>
          </v-card-title>
          <v-data-table
            :headers="divisionHeaders"
            :items="divisions"
            :items-per-page="-1"
            hide-default-footer
            class="dr-show-table"
            density="comfortable"
          >
            <template #item.division_name="{ item }">
              <span class="font-weight-medium">{{ item.division_name }}</span>
            </template>
            <template #item.short_name="{ item }">
              <span>{{ item.short_name || '—' }}</span>
            </template>
            <template #item.category="{ item }">
              <v-chip v-if="item.category" size="small" variant="tonal" color="secondary">{{ item.category }}</v-chip>
              <span v-else class="text-medium-emphasis">—</span>
            </template>
            <template #item.is_active="{ item }">
              <v-chip :color="activeStatusColor(item.is_active)" size="small" variant="flat">
                {{ activeStatusLabel(item.is_active) }}
              </v-chip>
            </template>
            <template #item.actions="{ item }">
              <v-btn :href="item.show_url" variant="outlined" color="primary" size="small">Open</v-btn>
            </template>
          </v-data-table>
        </v-card>
      </v-col>

      <v-col cols="12" lg="4">
        <v-card class="dr-show-section-card">
          <v-card-title class="dr-show-section-title">
            <v-icon icon="mdi-clock-outline" class="me-2" color="primary"></v-icon>Timestamps
          </v-card-title>
          <v-card-text class="pt-2">
            <v-list density="compact" class="bg-transparent">
              <v-list-item v-for="field in timestampFields" :key="field.label" class="px-0">
                <template #prepend>
                  <span class="dr-show-info-label me-4">{{ field.label }}</span>
                </template>
                <v-list-item-title class="dr-show-info-value text-end">{{ field.value }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDirectorateShow);
    }
})();
