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

        const { createApp, computed, reactive, watch, onMounted } = Vue;
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
                const docSearch = (window.ApmDocumentSearch && window.ApmDocumentSearch.setupSearchState)
                    ? window.ApmDocumentSearch.setupSearchState(cfg.documentSearch || {}, { reactive, watch, onMounted, computed })
                    : null;

                return {
                    modules,
                    totalPending,
                    userName,
                    docSearch,
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

        <div v-if="docSearch" class="mt-4">
          <div class="text-caption text-medium-emphasis mb-2">Look up a document by number</div>
          <div class="apm-doc-search position-relative">
            <v-row dense align="center">
              <v-col cols="12" sm="3" md="2">
                <v-select
                  v-model="docSearch.year"
                  :items="docSearch.yearItems"
                  label="Year"
                  density="comfortable"
                  hide-details
                  variant="outlined"
                ></v-select>
              </v-col>
              <v-col cols="12" sm="9" md="10">
                <v-text-field
                  v-model="docSearch.q"
                  :label="docSearch.placeholder"
                  prepend-inner-icon="mdi-magnify"
                  density="comfortable"
                  hide-details
                  clearable
                  variant="outlined"
                  autocomplete="off"
                  @focus="docSearch.onFocus"
                ></v-text-field>
              </v-col>
            </v-row>
            <v-progress-linear
              v-if="docSearch.loading"
              indeterminate
              color="primary"
              class="mt-2"
              height="2"
            ></v-progress-linear>
            <v-card
              v-if="docSearch.panelOpen && (docSearch.hint || docSearch.error || docSearch.results.length || (!docSearch.loading && (docSearch.q || '').trim().length >= docSearch.minChars))"
              class="apm-doc-search-results mt-2"
              elevation="3"
            >
              <v-list density="compact" lines="two">
                <v-list-item v-if="docSearch.hint && (docSearch.q || '').trim().length < docSearch.minChars">
                  <v-list-item-title class="text-medium-emphasis">{{ docSearch.hint }}</v-list-item-title>
                </v-list-item>
                <v-list-item v-else-if="docSearch.error">
                  <v-list-item-title class="text-error">{{ docSearch.error }}</v-list-item-title>
                </v-list-item>
                <v-list-item v-else-if="!docSearch.loading && docSearch.results.length === 0 && (docSearch.q || '').trim().length >= docSearch.minChars">
                  <v-list-item-title class="text-medium-emphasis">No documents found for this year</v-list-item-title>
                </v-list-item>
                <v-list-item
                  v-for="item in docSearch.results"
                  :key="item.document_type + '-' + item.id"
                  :href="item.show_url"
                  @click.prevent="docSearch.openResult(item)"
                >
                  <template #prepend>
                    <v-chip size="x-small" color="primary" variant="tonal" class="me-2">{{ item.document_type }}</v-chip>
                  </template>
                  <v-list-item-title class="font-weight-medium">
                    {{ item.document_number || '—' }}
                  </v-list-item-title>
                  <v-list-item-subtitle>
                    <span class="text-capitalize">{{ item.overall_status || '—' }}</span>
                    <span v-if="item.title"> · {{ item.title }}</span>
                  </v-list-item-subtitle>
                  <template #append>
                    <v-icon icon="mdi-chevron-right" size="small"></v-icon>
                  </template>
                </v-list-item>
              </v-list>
            </v-card>
          </div>
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
