/**
 * System configs — Stale memos (Vue 3 + Vuetify 3)
 * @see https://vuetifyjs.com/en/components/data-tables/
 */
(function () {
    'use strict';

    const MOUNT_ID = 'stale-memos-app';

    function bootStaleMemos(mountEl, cfg) {
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
                VTextField: { variant: 'outlined', density: 'comfortable', hideDetails: true },
                VSelect: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const policy = cfg.policy || {};
                const routes = cfg.routes || {};
                const csrf = cfg.csrf || '';

                const tab = ref('pending');
                const search = ref('');
                const page = ref(1);
                const archivedPage = ref(1);
                const itemsPerPage = ref(25);
                const loadingAction = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'success' });

                const pending = ref(Array.isArray(cfg.pendingStale) ? cfg.pendingStale : []);
                const archived = ref(Array.isArray(cfg.archived) ? cfg.archived : []);

                const confirmDialog = ref({
                    show: false,
                    mode: '',
                    title: '',
                    text: '',
                    item: null,
                });

                const pageSizeOptions = [
                    { title: '10 per page', value: 10 },
                    { title: '25 per page', value: 25 },
                    { title: '50 per page', value: 50 },
                    { title: '100 per page', value: 100 },
                ];

                const pendingHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Type', key: 'type_label', sortable: true, minWidth: 120 },
                    { title: 'Title', key: 'title', sortable: true, minWidth: 220 },
                    { title: 'Creator / Responsible', key: 'people', sortable: false, minWidth: 180 },
                    { title: 'Document', key: 'document_number', sortable: true, minWidth: 110 },
                    { title: 'Last updated', key: 'updated_at', sortable: true, minWidth: 130 },
                    { title: 'Budget', key: 'budget_total', sortable: true, minWidth: 100 },
                    { title: 'Scheduled archive', key: 'scheduled_archive_at', sortable: true, minWidth: 140 },
                    { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 170 },
                ];

                const archivedHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Archived at', key: 'archived_at', sortable: true, minWidth: 130 },
                    { title: 'Type', key: 'type_label', sortable: true, minWidth: 120 },
                    { title: 'Title', key: 'title', sortable: true, minWidth: 220 },
                    { title: 'Creator / Responsible', key: 'people', sortable: false, minWidth: 180 },
                    { title: 'Document', key: 'document_number', sortable: true, minWidth: 110 },
                    { title: 'Memo updated', key: 'memo_updated_at', sortable: true, minWidth: 130 },
                    { title: 'Budget', key: 'budget_total', sortable: true, minWidth: 100 },
                    { title: 'Trigger', key: 'trigger', sortable: true, width: 110 },
                    { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: 140 },
                ];

                function matchesSearch(item, q) {
                    if (!q) return true;
                    const hay = [
                        item.type_label,
                        item.title,
                        item.document_number,
                        item.creator_name,
                        item.responsible_name,
                        item.people_label,
                        item.trigger,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();
                    return hay.includes(q);
                }

                const filteredPending = computed(() => {
                    const q = search.value.trim().toLowerCase();
                    return pending.value.filter((item) => matchesSearch(item, q));
                });

                const filteredArchived = computed(() => {
                    const q = search.value.trim().toLowerCase();
                    return archived.value.filter((item) => matchesSearch(item, q));
                });

                const pendingItems = computed(() =>
                    filteredPending.value.map((item, index) => ({
                        ...item,
                        item_key: `${item.type || 'memo'}-${item.id}`,
                        row_num: index + 1,
                        people_lines: item.people_label || `Creator: ${item.creator_name || '—'}`,
                        budget_label: `$${item.budget_total_formatted || Number(item.budget_total || 0).toFixed(2)}`,
                        document_label: item.document_number || '—',
                        scheduled_label: item.scheduled_archive_at || '—',
                    }))
                );

                const archivedItems = computed(() =>
                    filteredArchived.value.map((item, index) => ({
                        ...item,
                        item_key: `archive-${item.id}`,
                        row_num: index + 1,
                        people_lines: item.people_label || `Creator: ${item.creator_name || '—'}`,
                        budget_label: `$${item.budget_total_formatted || Number(item.budget_total || 0).toFixed(2)}`,
                        document_label: item.document_number || '—',
                        memo_updated_label: item.memo_updated_at || '—',
                        archived_label: item.archived_at || '—',
                        trigger_label: item.trigger ? String(item.trigger).charAt(0).toUpperCase() + String(item.trigger).slice(1) : '—',
                    }))
                );

                const summaryKpis = computed(() => [
                    {
                        key: 'pending',
                        icon: 'mdi-timer-sand',
                        accent: '#f59e0b',
                        value: pending.value.length,
                        label: 'Pending stale',
                    },
                    {
                        key: 'archived',
                        icon: 'mdi-archive',
                        accent: '#64748b',
                        value: archived.value.length,
                        label: 'Archive history',
                    },
                    {
                        key: 'unarchiveable',
                        icon: 'mdi-archive-arrow-up',
                        accent: '#0284c7',
                        value: archived.value.filter((r) => r.can_unarchive).length,
                        label: 'Can unarchive',
                    },
                    {
                        key: 'policy',
                        icon: 'mdi-calendar-clock',
                        accent: '#119a48',
                        value: policy.draftMaxAgeMonths ?? 2,
                        label: 'Max age (months)',
                    },
                ]);

                function notify(text, color = 'success') {
                    snackbar.value = { show: true, text, color };
                }

                if (cfg.flash?.success) {
                    notify(cfg.flash.success, 'success');
                } else if (cfg.flash?.error) {
                    notify(cfg.flash.error, 'error');
                }

                async function postAction(url, body) {
                    loadingAction.value = true;
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(body),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.success === false) {
                            throw new Error(data.message || 'Action failed.');
                        }
                        notify(data.message || 'Done.', 'success');
                        window.location.href = routes.index || window.location.href;
                    } catch (e) {
                        notify(e.message || 'Action failed.', 'error');
                    } finally {
                        loadingAction.value = false;
                        confirmDialog.value.show = false;
                    }
                }

                function openArchiveItem(item) {
                    confirmDialog.value = {
                        show: true,
                        mode: 'archive-one',
                        title: 'Archive stale draft?',
                        text: `Archive “${item.title || 'Untitled'}”? overall_status will be set to archived and held budget will be released.`,
                        item,
                    };
                }

                function openArchiveAll() {
                    confirmDialog.value = {
                        show: true,
                        mode: 'archive-all',
                        title: 'Archive all stale drafts?',
                        text: `Archive all ${pending.value.length} pending stale draft memo(s) now? This releases held budget.`,
                        item: null,
                    };
                }

                function openUnarchiveItem(item) {
                    confirmDialog.value = {
                        show: true,
                        mode: 'unarchive-one',
                        title: 'Unarchive memo?',
                        text: `Restore “${item.title || 'Untitled'}” from archived status? It may commit budget again if still within draft age limits.`,
                        item,
                    };
                }

                function confirmAction() {
                    const mode = confirmDialog.value.mode;
                    const item = confirmDialog.value.item;
                    if (mode === 'archive-all') {
                        postAction(routes.archiveAll, {});
                        return;
                    }
                    if (mode === 'archive-one' && item) {
                        postAction(routes.archiveOne, {
                            memo_type: item.type,
                            memo_id: item.id,
                        });
                        return;
                    }
                    if (mode === 'unarchive-one' && item) {
                        postAction(routes.unarchiveOne, {
                            memo_type: item.memo_type,
                            memo_id: item.memo_id,
                            archive_id: item.id,
                        });
                    }
                }

                return {
                    policy,
                    tab,
                    search,
                    page,
                    archivedPage,
                    itemsPerPage,
                    pageSizeOptions,
                    pendingHeaders,
                    archivedHeaders,
                    pendingItems,
                    archivedItems,
                    summaryKpis,
                    snackbar,
                    confirmDialog,
                    loadingAction,
                    openArchiveItem,
                    openArchiveAll,
                    openUnarchiveItem,
                    confirmAction,
                };
            },
            template: `
<v-app class="sm-vuetify-app">
  <v-container fluid class="pa-0">
    <v-card class="mb-4" elevation="1">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4 px-md-6">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-archive-clock" color="warning" class="me-2"></v-icon>
            Stale draft memos
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            Drafts older than {{ policy.draftMaxAgeMonths }} month(s) holding budget.
            Auto-archive {{ policy.autoArchiveEnabled ? 'enabled' : 'disabled' }}
            <span v-if="policy.autoArchiveEnabled"> — {{ policy.weeklyRunLabel }} (next: {{ policy.nextWeeklyRun }})</span>.
          </div>
        </div>
        <div class="d-flex flex-wrap align-center gap-2">
          <v-text-field
            v-model="search"
            placeholder="Search title, creator, document…"
            prepend-inner-icon="mdi-magnify"
            clearable
            style="min-width: 240px; max-width: 320px;"
            @click:clear="search = ''"
          ></v-text-field>
          <v-select
            v-model="itemsPerPage"
            :items="pageSizeOptions"
            item-title="title"
            item-value="value"
            style="width: 140px;"
          ></v-select>
          <v-btn
            v-if="pendingItems.length"
            color="warning"
            prepend-icon="mdi-archive-arrow-down"
            :loading="loadingAction"
            @click="openArchiveAll"
          >
            Archive all
          </v-btn>
        </div>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="px-4 px-md-6 pt-4">
        <v-row dense class="mb-4">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
            <v-card class="sm-kpi-card" elevation="0" :style="{ '--sm-kpi-accent': kpi.accent }">
              <v-card-text class="d-flex align-center pa-4">
                <div
                  class="sm-kpi-icon-wrap me-3 flex-shrink-0"
                  :style="{ background: kpi.accent + '14', color: kpi.accent }"
                >
                  <v-icon :icon="kpi.icon" size="22"></v-icon>
                </div>
                <div class="min-w-0">
                  <div class="sm-kpi-value">{{ kpi.value }}</div>
                  <div class="sm-kpi-label">{{ kpi.label }}</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-tabs v-model="tab" color="primary" class="mb-3">
          <v-tab value="pending" prepend-icon="mdi-timer-sand">
            Pending
            <v-chip class="ms-2 apm-tab-count-chip" size="x-small" color="warning" variant="flat">{{ pendingItems.length }}</v-chip>
          </v-tab>
          <v-tab value="archived" prepend-icon="mdi-archive">
            Archived
            <v-chip class="ms-2 apm-tab-count-chip" size="x-small" color="secondary" variant="flat">{{ archivedItems.length }}</v-chip>
          </v-tab>
        </v-tabs>

        <v-window v-model="tab">
          <v-window-item value="pending">
            <v-data-table
              class="sm-table elevation-0 border rounded-lg"
              :headers="pendingHeaders"
              :items="pendingItems"
              :items-per-page="itemsPerPage"
              v-model:page="page"
              item-value="item_key"
            >
              <template #item.row_num="{ item }">
                <v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip>
              </template>

              <template #item.type_label="{ item }">
                <v-chip size="small" variant="tonal" color="info" label>{{ item.type_label }}</v-chip>
              </template>

              <template #item.title="{ item }">
                <div class="sm-title-cell text-body-2 font-weight-medium py-1">{{ item.title || 'Untitled' }}</div>
              </template>

              <template #item.people="{ item }">
                <div class="sm-people-cell text-body-2 text-medium-emphasis py-1">{{ item.people_lines }}</div>
              </template>

              <template #item.document_number="{ item }">
                <span class="text-body-2">{{ item.document_label }}</span>
              </template>

              <template #item.budget_total="{ item }">
                <span class="text-body-2 font-weight-medium">{{ item.budget_label }}</span>
              </template>

              <template #item.scheduled_archive_at="{ item }">
                <span class="text-body-2">{{ item.scheduled_label }}</span>
              </template>

              <template #item.actions="{ item }">
                <div class="d-flex justify-end align-center ga-1 flex-nowrap">
                  <v-btn
                    v-if="item.edit_url"
                    :href="item.edit_url"
                    icon="mdi-open-in-new"
                    variant="text"
                    color="primary"
                    size="small"
                    title="Open"
                  ></v-btn>
                  <v-btn
                    v-if="item.can_archive"
                    icon="mdi-archive-arrow-down"
                    variant="text"
                    color="warning"
                    size="small"
                    title="Archive"
                    :disabled="loadingAction"
                    @click="openArchiveItem(item)"
                  ></v-btn>
                </div>
              </template>

              <template #no-data>
                <v-alert type="info" variant="tonal" class="ma-4">
                  No stale draft memos currently holding budget.
                </v-alert>
              </template>
            </v-data-table>
          </v-window-item>

          <v-window-item value="archived">
            <v-data-table
              class="sm-table elevation-0 border rounded-lg"
              :headers="archivedHeaders"
              :items="archivedItems"
              :items-per-page="itemsPerPage"
              v-model:page="archivedPage"
              item-value="item_key"
            >
              <template #item.row_num="{ item }">
                <v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip>
              </template>

              <template #item.archived_at="{ item }">
                <span class="text-body-2">{{ item.archived_label }}</span>
              </template>

              <template #item.type_label="{ item }">
                <v-chip size="small" variant="tonal" color="secondary" label>{{ item.type_label }}</v-chip>
              </template>

              <template #item.title="{ item }">
                <div class="sm-title-cell text-body-2 font-weight-medium py-1">{{ item.title || 'Untitled' }}</div>
              </template>

              <template #item.people="{ item }">
                <div class="sm-people-cell text-body-2 text-medium-emphasis py-1">{{ item.people_lines }}</div>
              </template>

              <template #item.document_number="{ item }">
                <span class="text-body-2">{{ item.document_label }}</span>
              </template>

              <template #item.memo_updated_at="{ item }">
                <span class="text-body-2">{{ item.memo_updated_label }}</span>
              </template>

              <template #item.budget_total="{ item }">
                <span class="text-body-2 font-weight-medium">{{ item.budget_label }}</span>
              </template>

              <template #item.trigger="{ item }">
                <v-chip
                  size="small"
                  label
                  :color="item.trigger === 'manual' ? 'warning' : 'secondary'"
                  variant="tonal"
                >
                  {{ item.trigger_label }}
                </v-chip>
              </template>

              <template #item.actions="{ item }">
                <v-btn
                  v-if="item.can_unarchive"
                  color="info"
                  variant="tonal"
                  size="small"
                  prepend-icon="mdi-archive-arrow-up"
                  :disabled="loadingAction"
                  @click="openUnarchiveItem(item)"
                >
                  Unarchive
                </v-btn>
                <v-chip v-else size="small" variant="text" color="secondary">Restored</v-chip>
              </template>

              <template #no-data>
                <v-alert type="info" variant="tonal" class="ma-4">
                  No archived stale drafts yet.
                </v-alert>
              </template>
            </v-data-table>
          </v-window-item>
        </v-window>
      </v-card-text>
    </v-card>

    <v-dialog v-model="confirmDialog.show" max-width="520">
      <v-card>
        <v-card-title class="text-h6">{{ confirmDialog.title }}</v-card-title>
        <v-card-text>{{ confirmDialog.text }}</v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
          <v-btn
            :color="confirmDialog.mode === 'unarchive-one' ? 'info' : 'warning'"
            :loading="loadingAction"
            @click="confirmAction"
          >
            Confirm
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4500" location="top">
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
        window.ApmVuetifyPage.bind(MOUNT_ID, bootStaleMemos);
    }
})();
