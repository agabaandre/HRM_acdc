/**
 * Division detail — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'division-show-app';

    function bootDivisionShow(mountEl, cfg) {
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
                const division = ref(cfg.division || {});
                const routes = cfg.routes || {};
                const snackbar = ref({ show: false, text: '', color: 'success' });

                const statusChip = computed(() => {
                    const active = division.value.is_active;
                    if (active === true) return { text: 'Active', color: 'success' };
                    if (active === false) return { text: 'Inactive', color: 'error' };
                    return { text: 'Status not set', color: 'secondary' };
                });

                const directorateUrl = computed(() => {
                    const id = division.value.directorate?.id;
                    return id ? `${routes.directoratesShow}/${id}` : null;
                });

                function hasOic(block) {
                    return block && (block.staff_id || block.start || block.end);
                }

                onMounted(() => {
                    if (cfg.flash?.success) {
                        snackbar.value = { show: true, text: cfg.flash.success, color: 'success' };
                    }
                });

                return {
                    division,
                    routes,
                    snackbar,
                    statusChip,
                    directorateUrl,
                    hasOic,
                };
            },
            template: `
<v-app class="ds-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h4 class="text-h5 font-weight-bold mb-1">{{ division.division_name }}</h4>
        <div class="text-body-2 text-medium-emphasis">Division details and staff assignments</div>
      </div>
      <v-btn color="primary" variant="flat" :href="routes.index" prepend-icon="mdi-arrow-left">
        Back to divisions
      </v-btn>
    </div>

    <v-card class="mb-4">
      <v-card-text class="pa-4">
        <div class="d-flex flex-wrap align-center gap-2 mb-3">
          <v-chip size="small" variant="tonal" color="primary">ID {{ division.id }}</v-chip>
          <v-chip v-if="division.division_short_name" size="small" color="secondary">{{ division.division_short_name }}</v-chip>
          <v-chip v-if="division.category" size="small" variant="outlined">{{ division.category }}</v-chip>
          <v-chip size="small" :color="statusChip.color" variant="tonal">{{ statusChip.text }}</v-chip>
        </div>
        <div v-if="division.directorate" class="text-body-2 text-medium-emphasis">
          <v-icon icon="mdi-domain" size="small" class="me-1"></v-icon>
          <a v-if="directorateUrl" :href="directorateUrl" class="text-decoration-none font-weight-medium">{{ division.directorate.name }}</a>
          <span v-else>{{ division.directorate.name }}</span>
        </div>
      </v-card-text>
    </v-card>

    <v-row>
      <v-col cols="12" lg="5">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-information-outline" class="me-2" color="primary"></v-icon>Basic information
          </v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis text-uppercase">Division ID</div>
                <div class="font-weight-semibold text-primary">{{ division.id }}</div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis text-uppercase">Short name</div>
                <div>{{ division.division_short_name || '—' }}</div>
              </v-col>
              <v-col cols="12">
                <div class="text-caption text-medium-emphasis text-uppercase">Division name</div>
                <div class="font-weight-semibold">{{ division.division_name }}</div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis text-uppercase">Category</div>
                <div>{{ division.category || '—' }}</div>
              </v-col>
              <v-col cols="6">
                <div class="text-caption text-medium-emphasis text-uppercase">Status</div>
                <v-chip size="x-small" :color="statusChip.color" variant="tonal">{{ statusChip.text }}</v-chip>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card>
          <v-card-title class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-domain" class="me-2" color="info"></v-icon>Additional information
          </v-card-title>
          <v-card-text>
            <div class="mb-4">
              <div class="text-caption text-medium-emphasis text-uppercase mb-1">Directorate</div>
              <template v-if="division.directorate">
                <a v-if="directorateUrl" :href="directorateUrl" class="font-weight-medium text-decoration-none">{{ division.directorate.name }}</a>
                <span v-else class="font-weight-medium">{{ division.directorate.name }}</span>
                <div class="text-caption text-medium-emphasis">ID {{ division.directorate.id }}</div>
              </template>
              <span v-else class="text-medium-emphasis">Not specified</span>
            </div>
            <div class="mb-4">
              <div class="text-caption text-medium-emphasis text-uppercase mb-1">Director (from directorate)</div>
              <template v-if="division.director">
                <div class="font-weight-medium">{{ division.director.name }}</div>
                <div class="text-caption text-medium-emphasis">
                  {{ division.director.title }} · Staff ID {{ division.director.staff_id }}
                </div>
              </template>
              <span v-else class="text-medium-emphasis">Not assigned</span>
            </div>

            <template v-if="hasOic(division.head_oic)">
              <v-divider class="my-3"></v-divider>
              <div class="text-caption text-medium-emphasis text-uppercase mb-2">Head OIC</div>
              <v-row dense>
                <v-col cols="4"><div class="text-caption">Staff ID</div><div>{{ division.head_oic.staff_id || '—' }}</div></v-col>
                <v-col cols="4"><div class="text-caption">Start</div><div>{{ division.head_oic.start || '—' }}</div></v-col>
                <v-col cols="4"><div class="text-caption">End</div><div>{{ division.head_oic.end || '—' }}</div></v-col>
              </v-row>
            </template>

            <template v-if="hasOic(division.director_oic)">
              <v-divider class="my-3"></v-divider>
              <div class="text-caption text-medium-emphasis text-uppercase mb-2">Director OIC</div>
              <v-row dense>
                <v-col cols="4"><div class="text-caption">Staff ID</div><div>{{ division.director_oic.staff_id || '—' }}</div></v-col>
                <v-col cols="4"><div class="text-caption">Start</div><div>{{ division.director_oic.start || '—' }}</div></v-col>
                <v-col cols="4"><div class="text-caption">End</div><div>{{ division.director_oic.end || '—' }}</div></v-col>
              </v-row>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="7">
        <v-card>
          <v-card-title class="text-subtitle-1 font-weight-medium">
            <v-icon icon="mdi-account-group" class="me-2" color="success"></v-icon>Staff assignments
          </v-card-title>
          <v-card-text>
            <v-row dense>
              <v-col v-for="role in division.staff_roles" :key="role.key" cols="12" sm="6">
                <v-card variant="outlined" class="h-100">
                  <v-card-text class="d-flex gap-3 align-start">
                    <v-avatar :color="role.color" variant="tonal" size="40">
                      <v-icon :icon="role.icon" size="small"></v-icon>
                    </v-avatar>
                    <div>
                      <div class="text-caption text-medium-emphasis text-uppercase">{{ role.label }}</div>
                      <template v-if="role.staff">
                        <div class="font-weight-medium">{{ role.staff.name }}</div>
                        <div class="text-caption text-medium-emphasis">
                          {{ role.staff.title }}<span v-if="role.staff.staff_id"> · ID {{ role.staff.staff_id }}</span>
                        </div>
                      </template>
                      <div v-else class="text-medium-emphasis">Not assigned</div>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
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

    window.ApmVuetifyPage.bind(MOUNT_ID, bootDivisionShow);
})();
