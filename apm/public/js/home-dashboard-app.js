/**
 * APM Home Dashboard — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'home-dashboard-app';

    function bootHomeDashboard(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, computed } = Vue;
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
                const modules = computed(() => cfg.modules || []);
                const totalPending = computed(() => Number(cfg.totalPending || 0));
                const userName = computed(() => cfg.userName || '');

                return {
                    modules,
                    totalPending,
                    userName,
                };
            },
            template: `
<v-app class="hd-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4" elevation="1">
      <v-card-text class="pa-4 pa-md-5">
        <div class="d-flex flex-wrap align-center justify-space-between gap-3">
          <div>
            <div class="text-h5 font-weight-bold hd-title">
              <v-icon icon="mdi-view-dashboard-outline" color="primary" class="me-2"></v-icon>
              Approvals Management
            </div>
            <div v-if="userName" class="text-body-2 hd-subtitle mt-1">
              Welcome back, <strong>{{ userName }}</strong>
            </div>
          </div>
          <v-chip
            v-if="totalPending > 0"
            color="warning"
            variant="flat"
            size="large"
            prepend-icon="mdi-bell-badge-outline"
            class="hd-pending-chip font-weight-bold"
          >
            {{ totalPending }} pending approval{{ totalPending === 1 ? '' : 's' }}
          </v-chip>
          <v-chip
            v-else
            color="success"
            variant="flat"
            size="large"
            prepend-icon="mdi-check-circle-outline"
            class="hd-clear-chip"
          >
            No pending approvals
          </v-chip>
        </div>
      </v-card-text>
    </v-card>

    <v-row dense>
      <v-col
        v-for="mod in modules"
        :key="mod.key"
        cols="12"
        sm="6"
        lg="3"
      >
        <v-card
          class="hd-module-card d-flex flex-column"
          elevation="1"
        >
          <v-card-text class="pa-4 d-flex flex-column flex-grow-1">
            <div class="d-flex align-start mb-3">
              <div
                class="hd-icon-wrap me-3 flex-shrink-0"
                :style="{ background: mod.accent + '1a', color: mod.accent }"
              >
                <v-icon :icon="mod.icon" size="26"></v-icon>
              </div>
              <div class="min-w-0 flex-grow-1">
                <div class="d-flex align-center flex-wrap gap-2">
                  <div class="hd-module-title text-wrap">{{ mod.title }}</div>
                  <v-chip
                    v-if="mod.pendingCount > 0"
                    size="x-small"
                    color="error"
                    variant="flat"
                    class="font-weight-bold"
                  >{{ mod.pendingCount }} pending</v-chip>
                </div>
                <div class="hd-module-desc text-wrap mt-1">{{ mod.description }}</div>
              </div>
            </div>

            <div class="mt-auto">
              <div class="text-caption text-uppercase font-weight-bold hd-actions-label mb-2">
                Quick actions
              </div>
              <div class="d-flex flex-column gap-2">
                <v-btn
                  :href="mod.openUrl"
                  color="primary"
                  variant="flat"
                  block
                  :prepend-icon="mod.openIcon || 'mdi-open-in-new'"
                  class="text-none hd-open-btn"
                >{{ mod.openLabel || 'Open' }}</v-btn>

                <v-btn
                  v-if="mod.pendingUrl"
                  :href="mod.pendingUrl"
                  variant="outlined"
                  color="primary"
                  block
                  prepend-icon="mdi-clipboard-check-outline"
                  class="text-none hd-pending-btn"
                >
                  Pending approval
                  <v-chip
                    v-if="mod.pendingCount > 0"
                    size="x-small"
                    color="error"
                    variant="flat"
                    class="ms-2 font-weight-bold"
                  >{{ mod.pendingCount }}</v-chip>
                </v-btn>

                <template v-if="mod.extraActions && mod.extraActions.length">
                  <v-divider class="my-1"></v-divider>
                  <v-btn
                    v-for="(action, ai) in mod.extraActions"
                    :key="ai"
                    :href="action.url"
                    color="primary"
                    variant="tonal"
                    block
                    :prepend-icon="action.icon || 'mdi-link'"
                    class="text-none"
                  >{{ action.label }}</v-btn>
                </template>
              </div>
            </div>
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootHomeDashboard);
    }
})();
