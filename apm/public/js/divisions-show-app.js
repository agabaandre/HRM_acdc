/**
 * Division detail — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'divisions-show-app';

    function activeStatusColor(isActive) {
        if (isActive === true) return 'success';
        if (isActive === false) return 'error';
        return 'secondary';
    }

    function activeStatusLabel(isActive) {
        if (isActive === true) return 'Active';
        if (isActive === false) return 'Inactive';
        return 'Not set';
    }

    function hasOic(oic) {
        return !!(oic && (oic.staff_id || oic.start || oic.end));
    }

    function bootDivisionShow(mountEl, cfg) {
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
                const division = ref(cfg.division || {});
                const directorate = ref(cfg.directorate || null);
                const director = ref(cfg.director || null);
                const staffRoles = ref(cfg.staffRoles || []);
                const routes = ref(cfg.routes || {});
                const flash = ref(cfg.flash || {});

                const basicFields = computed(() => [
                    { label: 'Division ID', value: division.value.id },
                    { label: 'Short name', value: division.value.division_short_name || 'Not specified' },
                    { label: 'Division name', value: division.value.division_name, full: true },
                    { label: 'Category', value: division.value.category || 'Not specified' },
                    { label: 'Status', value: activeStatusLabel(division.value.is_active), isStatus: true },
                ]);

                const additionalFields = computed(() => [
                    {
                        label: 'Directorate',
                        value: directorate.value ? directorate.value.name : (division.value.directorate_id ? `ID ${division.value.directorate_id}` : 'Not specified'),
                        url: directorate.value?.show_url || null,
                    },
                    {
                        label: 'Director',
                        value: director.value
                            ? director.value.name
                            : (division.value.director_id ? `Staff ID ${division.value.director_id}` : 'Not assigned'),
                        url: director.value?.show_url || null,
                        meta: director.value?.staff_id ? `Staff ID ${director.value.staff_id}` : null,
                    },
                ]);

                return {
                    division,
                    directorate,
                    director,
                    staffRoles,
                    routes,
                    flash,
                    basicFields,
                    additionalFields,
                    activeStatusColor,
                    activeStatusLabel,
                    hasOic,
                };
            },
            template: `
<v-app class="dv-show-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-alert v-if="flash.success" type="success" variant="tonal" class="mb-4" closable>{{ flash.success }}</v-alert>

    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <div class="d-flex flex-wrap align-center gap-2 mb-2">
          <v-chip size="small" color="primary" variant="flat">ID {{ division.id }}</v-chip>
          <v-chip v-if="division.division_short_name" size="small" color="secondary" variant="flat">{{ division.division_short_name }}</v-chip>
          <v-chip v-if="division.category" size="small" variant="tonal" color="secondary">{{ division.category }}</v-chip>
          <v-chip :color="activeStatusColor(division.is_active)" size="small" variant="flat">{{ activeStatusLabel(division.is_active) }}</v-chip>
        </div>
        <h4 class="text-h5 font-weight-bold mb-1">{{ division.division_name }}</h4>
        <div v-if="directorate" class="text-body-2 text-medium-emphasis">
          <v-icon icon="mdi-domain" size="small" class="me-1"></v-icon>
          <a :href="directorate.show_url" class="text-decoration-none text-primary">{{ directorate.name }}</a>
        </div>
      </div>
      <v-btn color="primary" variant="flat" :href="routes.index" prepend-icon="mdi-arrow-left">Back to divisions</v-btn>
    </div>

    <v-row>
      <v-col cols="12" lg="5">
        <v-card class="dv-show-section-card mb-4">
          <v-card-title class="dv-show-section-title">
            <v-icon icon="mdi-information-outline" class="me-2" color="primary"></v-icon>Basic information
          </v-card-title>
          <v-card-text class="pt-4">
            <v-row>
              <v-col
                v-for="field in basicFields"
                :key="field.label"
                :cols="field.full ? 12 : 6"
              >
                <div class="dv-show-info-label">{{ field.label }}</div>
                <div v-if="field.isStatus">
                  <v-chip :color="activeStatusColor(division.is_active)" size="small" variant="flat">{{ field.value }}</v-chip>
                </div>
                <div v-else class="dv-show-info-value" :class="{ 'font-weight-semibold': field.full }">{{ field.value }}</div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <v-card class="dv-show-section-card">
          <v-card-title class="dv-show-section-title">
            <v-icon icon="mdi-text-box-outline" class="me-2" color="info"></v-icon>Additional information
          </v-card-title>
          <v-card-text class="pt-4">
            <v-row>
              <v-col v-for="field in additionalFields" :key="field.label" cols="12" sm="6">
                <div class="dv-show-info-label">{{ field.label }}</div>
                <div class="dv-show-info-value">
                  <a v-if="field.url" :href="field.url" class="text-decoration-none text-primary font-weight-medium">{{ field.value }}</a>
                  <span v-else>{{ field.value }}</span>
                  <div v-if="field.meta" class="text-body-2 text-medium-emphasis mt-1">{{ field.meta }}</div>
                </div>
              </v-col>
            </v-row>

            <template v-if="hasOic(division.head_oic)">
              <v-divider class="my-4"></v-divider>
              <div class="dv-show-info-label mb-3">Head OIC</div>
              <v-row>
                <v-col cols="4"><div class="dv-show-info-label">OIC staff ID</div><div class="dv-show-info-value">{{ division.head_oic.staff_id || '—' }}</div></v-col>
                <v-col cols="4"><div class="dv-show-info-label">Start</div><div class="dv-show-info-value">{{ division.head_oic.start || '—' }}</div></v-col>
                <v-col cols="4"><div class="dv-show-info-label">End</div><div class="dv-show-info-value">{{ division.head_oic.end || '—' }}</div></v-col>
              </v-row>
            </template>

            <template v-if="hasOic(division.director_oic)">
              <v-divider class="my-4"></v-divider>
              <div class="dv-show-info-label mb-3">Director OIC</div>
              <v-row>
                <v-col cols="4"><div class="dv-show-info-label">OIC staff ID</div><div class="dv-show-info-value">{{ division.director_oic.staff_id || '—' }}</div></v-col>
                <v-col cols="4"><div class="dv-show-info-label">Start</div><div class="dv-show-info-value">{{ division.director_oic.start || '—' }}</div></v-col>
                <v-col cols="4"><div class="dv-show-info-label">End</div><div class="dv-show-info-value">{{ division.director_oic.end || '—' }}</div></v-col>
              </v-row>
            </template>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" lg="7">
        <v-card class="dv-show-section-card h-100">
          <v-card-title class="dv-show-section-title">
            <v-icon icon="mdi-account-group-outline" class="me-2" color="success"></v-icon>Staff assignments
          </v-card-title>
          <v-card-text class="pt-4">
            <v-row>
              <v-col v-for="role in staffRoles" :key="role.key" cols="12" sm="6">
                <v-card variant="outlined" class="dv-show-role-card" :class="'dv-show-role-card--' + role.color">
                  <v-card-text class="d-flex align-start gap-3 pa-4">
                    <v-avatar :color="role.color" variant="tonal" size="44">
                      <v-icon :icon="role.icon"></v-icon>
                    </v-avatar>
                    <div class="flex-grow-1">
                      <div class="dv-show-info-label mb-1">{{ role.label }}</div>
                      <template v-if="role.staff">
                        <a :href="role.staff.show_url" class="text-decoration-none text-high-emphasis font-weight-semibold">{{ role.staff.name }}</a>
                        <div class="text-body-2 text-medium-emphasis mt-1">
                          {{ role.staff.meta }}
                          <span v-if="role.staff.staff_id"> · ID {{ role.staff.staff_id }}</span>
                        </div>
                      </template>
                      <div v-else class="text-medium-emphasis font-italic">Not assigned</div>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <p class="text-body-2 text-medium-emphasis mt-4 mb-0">
      <v-icon icon="mdi-information-outline" size="small" class="me-1"></v-icon>
      Divisions are managed in the main system.
    </p>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDivisionShow);
    }
})();
