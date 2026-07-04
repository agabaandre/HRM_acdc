/**
 * Pending approvals — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'pending-approvals-app';

    const CATEGORY_ICONS = {
        Matrix: 'mdi-calendar-range',
        'Special Memo': 'mdi-email-newsletter',
        'Non-Travel Memo': 'mdi-file-document-outline',
        'Single Memo': 'mdi-file-document',
        'Other Memo': 'mdi-note-text',
        'Service Request': 'mdi-tools',
        ARF: 'mdi-file-sign',
        'Change Request': 'mdi-file-edit-outline',
    };

    function fmtDate(iso) {
        if (!iso) return 'N/A';
        try {
            return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
        } catch (e) {
            return 'N/A';
        }
    }

    function daysWaiting(iso) {
        if (!iso) return 0;
        try {
            return Math.floor((Date.now() - new Date(iso).getTime()) / 86400000);
        } catch (e) {
            return 0;
        }
    }

    function fmtWaiting(iso) {
        const days = daysWaiting(iso);
        if (days < 1) return 'Received today';
        if (days === 1) return '1 day waiting';
        return days + ' days waiting';
    }

    function navigateFilters(cfg, category, division) {
        const url = new URL(cfg.routes.index, window.location.origin);
        url.searchParams.set('category', category || 'all');
        url.searchParams.set('division', division || 'all');
        if (cfg.filters.staff_id) url.searchParams.set('staff_id', String(cfg.filters.staff_id));
        if (cfg.filters.year) url.searchParams.set('year', String(cfg.filters.year));
        if (cfg.filters.month) url.searchParams.set('month', String(cfg.filters.month));
        const target = url.pathname + url.search;
        if (typeof Livewire !== 'undefined' && Livewire.navigate) {
            Livewire.navigate(target);
        } else {
            window.location.href = target;
        }
    }

    function bootPendingApprovals(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch } = Vue;
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
                VSelect: { variant: 'outlined', density: 'comfortable' },
            },
        });

        const app = createApp({
            setup() {
                const category = ref(cfg.filters.category || 'all');
                const division = ref(cfg.filters.division || 'all');
                const snackbar = ref({ show: false, text: '', color: 'info' });

                const staleKeySet = new Set(cfg.staleKeys || []);

                const summary = computed(() => cfg.summaryStats || { total_pending: 0, by_category: {} });
                const byCat = computed(() => summary.value.by_category || {});

                const memoCardCount = computed(() =>
                    (byCat.value['Special Memo'] || 0)
                    + (byCat.value['Non-Travel Memo'] || 0)
                    + (byCat.value['Single Memo'] || 0)
                    + (byCat.value['Other Memo'] || 0)
                );

                const requestsCardCount = computed(() =>
                    (byCat.value['Service Request'] || 0)
                    + (byCat.value['ARF'] || 0)
                    + (byCat.value['Change Request'] || 0)
                );

                const totalPending = computed(() => Number(summary.value.total_pending || 0));

                const kpis = computed(() => [
                    { key: 'total', icon: 'mdi-clock-outline', accent: '#0ea5e9', value: totalPending.value, label: 'Total pending' },
                    { key: 'matrix', icon: 'mdi-calendar-range', accent: '#d97706', value: byCat.value.Matrix || 0, label: 'Matrices' },
                    { key: 'memos', icon: 'mdi-file-document-multiple', accent: '#475569', value: memoCardCount.value, label: 'Memos' },
                    { key: 'requests', icon: 'mdi-cog-outline', accent: '#2563eb', value: requestsCardCount.value, label: 'Requests' },
                ]);

                const categoryGroups = computed(() => {
                    const groups = [];
                    const data = cfg.pendingApprovals || {};
                    Object.keys(data).forEach((name) => {
                        const items = data[name] || [];
                        if (items.length) {
                            groups.push({ name, items, icon: CATEGORY_ICONS[name] || 'mdi-folder-open' });
                        }
                    });
                    return groups;
                });

                const hasItems = computed(() => categoryGroups.value.length > 0);

                const categoryItems = computed(() =>
                    (cfg.groupedCategories || []).map((c) => ({
                        title: `${c.label} (${c.count})`,
                        value: c.value,
                    }))
                );

                const divisionItems = computed(() => {
                    const list = [{ title: 'All divisions', value: 'all' }];
                    (cfg.divisions || []).forEach((d) => list.push({ title: d.name, value: String(d.id) }));
                    return list;
                });

                const tableHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Title', key: 'title', sortable: false, minWidth: 220 },
                    { title: 'Division', key: 'division', sortable: false, minWidth: 140 },
                    { title: 'Submitted by', key: 'submitted_by', sortable: false, minWidth: 130 },
                    { title: 'Date received', key: 'date_received', sortable: false, minWidth: 140 },
                    { title: 'Level', key: 'approval_level', sortable: false, width: 90, align: 'center' },
                    { title: 'Workflow role', key: 'workflow_role', sortable: false, minWidth: 120 },
                    { title: 'Actions', key: 'actions', sortable: false, width: 110, align: 'center' },
                ];

                function itemKey(item) {
                    const type = String(item.item_type || item.type || '');
                    const id = String(item.item_id ?? item.id ?? '');
                    return type && id ? `${type}:${id}` : '';
                }

                function isStaleItem(item) {
                    const key = itemKey(item);
                    return key !== '' && staleKeySet.has(key);
                }

                function rowProps({ item }) {
                    return {
                        class: isStaleItem(item) ? 'pa-row-stale' : '',
                    };
                }

                function categoryIcon(name) {
                    return CATEGORY_ICONS[name] || 'mdi-folder-open';
                }

                let filterTimer = null;
                watch([category, division], () => {
                    clearTimeout(filterTimer);
                    filterTimer = setTimeout(() => {
                        navigateFilters(cfg, category.value, division.value);
                    }, 150);
                });

                function refreshPage() {
                    if (typeof Livewire !== 'undefined' && Livewire.navigate) {
                        Livewire.navigate(window.location.pathname + window.location.search);
                    } else {
                        window.location.reload();
                    }
                }

                function exportSoon() {
                    snackbar.value = { show: true, text: 'Export functionality coming soon.', color: 'info' };
                }

                return {
                    cfg,
                    category,
                    division,
                    snackbar,
                    kpis,
                    totalPending,
                    categoryGroups,
                    hasItems,
                    categoryItems,
                    divisionItems,
                    tableHeaders,
                    fmtDate,
                    fmtWaiting,
                    daysWaiting,
                    isStaleItem,
                    rowProps,
                    categoryIcon,
                    refreshPage,
                    exportSoon,
                };
            },
            template: `
<v-app class="pa-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4" elevation="1">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2 py-4">
        <div class="flex-grow-1 min-w-0">
          <div class="text-h6 font-weight-bold text-primary d-flex align-center">
            <v-icon icon="mdi-clipboard-check-outline" class="me-2"></v-icon>
            Pending approvals
          </div>
          <div
            v-if="(cfg.avgLast5Display && cfg.avgLast5Hours > 0) || (cfg.avgApprovalTimeDisplay && cfg.avgApprovalTimeHours > 0)"
            class="pa-avg-time-inline d-flex flex-wrap align-center justify-space-between gap-2 mt-3 mb-2"
          >
            <div class="d-flex flex-wrap align-center gap-3">
              <div v-if="cfg.avgLast5Display && cfg.avgLast5Hours > 0" class="text-body-2 d-flex align-center flex-wrap gap-1">
                <v-icon icon="mdi-file-clock-outline" color="warning" size="18"></v-icon>
                <span class="text-medium-emphasis">Avg. last 5 docs:</span>
                <v-chip
                  v-if="cfg.timingReportUrl"
                  :href="cfg.timingReportUrl"
                  tag="a"
                  size="small"
                  variant="flat"
                  class="pa-chip-last5 text-decoration-none"
                >{{ cfg.avgLast5Display }}</v-chip>
                <strong v-else class="text-warning">{{ cfg.avgLast5Display }}</strong>
              </div>
              <div v-if="cfg.avgApprovalTimeDisplay && cfg.avgApprovalTimeHours > 0" class="text-body-2 d-flex align-center flex-wrap gap-1">
                <v-icon icon="mdi-speedometer" color="primary" size="18"></v-icon>
                <span class="text-medium-emphasis">Avg. all docs:</span>
                <v-chip
                  v-if="cfg.timingReportUrl"
                  :href="cfg.timingReportUrl"
                  tag="a"
                  size="small"
                  variant="flat"
                  class="pa-chip-all text-decoration-none"
                >{{ cfg.avgApprovalTimeDisplay }}</v-chip>
                <strong v-else class="text-primary">{{ cfg.avgApprovalTimeDisplay }}</strong>
              </div>
            </div>
            <v-btn
              v-if="cfg.timingReportUrl"
              :href="cfg.timingReportUrl"
              color="primary"
              variant="tonal"
              size="x-small"
              prepend-icon="mdi-table"
            >
              Average time per document
            </v-btn>
          </div>
          <div class="text-body-2 text-medium-emphasis">Items waiting for your action at the current workflow step</div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
          <v-btn color="primary" variant="flat" prepend-icon="mdi-refresh" size="small" @click="refreshPage">Refresh</v-btn>
          <v-btn variant="outlined" color="primary" prepend-icon="mdi-download" size="small" @click="exportSoon">Export</v-btn>
        </div>
      </v-card-title>
    </v-card>

    <v-card
      v-if="cfg.staleCount > 0"
      class="mb-3 pa-stale-alert"
      variant="flat"
      elevation="0"
    >
      <v-card-text class="pa-stale-alert-body py-2 px-3">
        <div class="d-flex align-start gap-2">
          <v-icon icon="mdi-timer-sand" size="20" color="warning" class="pa-stale-inline-icon flex-shrink-0 mt-1"></v-icon>
          <div class="flex-grow-1 min-w-0">
            <div class="pa-stale-heading font-weight-bold mb-1">
              Friendly reminder — {{ cfg.staleCount }} item(s) overdue at your level
            </div>
            <p class="pa-stale-copy mb-1">
              These have been waiting more than <strong>{{ cfg.approvalWarningDays }}</strong> day(s) since they reached your step.
              Please <strong>approve</strong> or <strong>return</strong> when ready; reminders stop once cleared.
            </p>
            <div v-if="cfg.staleItems && cfg.staleItems.length" class="d-flex flex-wrap gap-1 mt-1">
              <v-chip
                v-for="stale in cfg.staleItems"
                :key="stale.key"
                :href="stale.view_url"
                class="pa-stale-chip text-decoration-none"
                size="x-small"
                variant="flat"
              >
                {{ stale.title }} — {{ stale.days_waiting }}d
              </v-chip>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-row dense class="mb-4 pa-kpi-row">
      <v-col v-for="kpi in kpis" :key="kpi.key" cols="6" sm="3">
        <v-card class="pa-kpi-card" elevation="0" :style="{ '--pa-accent': kpi.accent }">
          <v-card-text class="d-flex align-center pa-4">
            <div
              class="pa-kpi-icon-wrap me-3 flex-shrink-0"
              :style="{ background: kpi.accent + '14', color: kpi.accent }"
            >
              <v-icon :icon="kpi.icon" size="22"></v-icon>
            </div>
            <div class="min-w-0">
              <div class="pa-kpi-value">{{ kpi.value }}</div>
              <div class="pa-kpi-label">{{ kpi.label }}</div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-expansion-panels class="mb-4" variant="accordion">
      <v-expansion-panel title="How these numbers are derived" prepend-icon="mdi-information-outline">
        <v-expansion-panel-text class="text-body-2 text-medium-emphasis">
          <ul class="ps-4 mb-0">
            <li class="mb-2"><strong>Total pending</strong> counts every item where you can take action now.</li>
            <li class="mb-2">Category filters below only change the tables; top counts always reflect your full queue.</li>
            <li v-if="cfg.isAdminAssistant" class="mb-2">Admin assistants may see view-only rows for supported approvers.</li>
            <li v-if="cfg.avgLast5Display && cfg.avgLast5Hours > 0" class="mb-2">
              <strong>Avg. last 5 docs</strong> uses only your five most recent completed approval actions (no pending wait).
            </li>
            <li v-if="cfg.avgApprovalTimeDisplay && cfg.avgApprovalTimeHours > 0" class="mb-2">
              <strong>Avg. all docs</strong> blends completed approvals with time elapsed on items still waiting at your level.
            </li>
            <li class="mb-2">
              <strong>Aging reminders</strong> (banner above and email at 11:00 daily) use <strong>approval_warning_days</strong> in System settings (default 7): items are flagged when they have been at your level for <em>more than</em> that many days since they were handed to your step.
            </li>
            <li>
              <strong>Escalation emails</strong> (same schedule) also notify the document creator, Head of Division, and senior approvers above the stuck step for all workflows. On the <strong>general workflow (workflow 1)</strong> only, additional oversight approvers are taken from <strong>general_workflow_stale_escalation_orders</strong> in System settings.
            </li>
          </ul>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <v-card class="mb-4" elevation="1">
      <v-card-title class="text-subtitle-1 font-weight-medium py-3">
        <v-icon icon="mdi-filter-outline" class="me-2"></v-icon>Queue filters
      </v-card-title>
      <v-card-text class="pt-0">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select v-model="category" :items="categoryItems" label="Category" hide-details></v-select>
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="division" :items="divisionItems" label="Division" hide-details></v-select>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card v-if="cfg.approverInfo" class="mb-4 pa-approver-card" variant="outlined" elevation="0">
      <v-card-text>
        <div class="d-flex align-start gap-3">
          <v-avatar color="primary" size="48"><v-icon icon="mdi-account" color="white"></v-icon></v-avatar>
          <div>
            <div class="text-h6 font-weight-bold">{{ cfg.approverInfo.name }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ cfg.approverInfo.email }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ cfg.approverInfo.division_name }}</div>
            <div v-if="cfg.approverInfo.roles && cfg.approverInfo.roles.length" class="d-flex flex-wrap gap-1 mt-2">
              <v-chip v-for="(role, ri) in cfg.approverInfo.roles" :key="ri" size="small" variant="tonal" color="primary">{{ role }}</v-chip>
            </div>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-for="group in categoryGroups" :key="group.name" class="mb-4 pa-category-card" elevation="0">
      <v-card-title class="pa-category-header d-flex align-center justify-space-between py-3 px-4">
        <span class="pa-category-title d-flex align-center">
          <span class="pa-category-icon-wrap me-3">
            <v-icon :icon="group.icon" size="20"></v-icon>
          </span>
          {{ group.name }}
        </span>
        <v-chip size="small" class="pa-open-badge" variant="flat">{{ group.items.length }} open</v-chip>
      </v-card-title>
      <v-data-table
        :headers="tableHeaders"
        :items="group.items"
        :items-per-page="-1"
        :row-props="rowProps"
        density="comfortable"
        class="pa-approvals-table"
        hide-default-footer
      >
        <template #item.row_num="{ index }">{{ index + 1 }}</template>
        <template #item.title="{ item }">
          <div class="d-flex flex-wrap align-center gap-2">
            <div class="font-weight-medium text-wrap">{{ item.title }}</div>
            <v-chip
              v-if="isStaleItem(item)"
              size="x-small"
              variant="flat"
              class="pa-chip-stale"
              prepend-icon="mdi-timer-sand"
            >
              {{ daysWaiting(item.date_received) }}d overdue
            </v-chip>
          </div>
          <div class="text-caption text-medium-emphasis">{{ item.category }}</div>
        </template>
        <template #item.division="{ item }">
          <v-chip size="small" variant="flat" class="pa-chip-division text-wrap">{{ item.division }}</v-chip>
        </template>
        <template #item.submitted_by="{ item }">{{ item.submitted_by }}</template>
        <template #item.date_received="{ item }">
          <div :class="isStaleItem(item) ? 'font-weight-medium pa-stale-date' : ''">{{ fmtDate(item.date_received) }}</div>
          <div
            class="text-caption"
            :class="isStaleItem(item) ? 'pa-stale-date font-weight-bold' : 'text-medium-emphasis'"
          >{{ fmtWaiting(item.date_received) }}</div>
        </template>
        <template #item.approval_level="{ item }">
          <v-chip size="small" variant="flat" class="pa-chip-info">L{{ item.approval_level }}</v-chip>
        </template>
        <template #item.workflow_role="{ item }">
          <v-chip size="small" variant="flat" class="pa-chip-role text-wrap">{{ item.workflow_role }}</v-chip>
        </template>
        <template #item.actions="{ item }">
          <v-btn :href="item.view_url" color="primary" variant="tonal" size="small" prepend-icon="mdi-open-in-new">Open</v-btn>
          <div v-if="cfg.isAdminAssistant && item.is_admin_assistant_view" class="text-caption text-medium-emphasis mt-1">
            <v-icon icon="mdi-eye-outline" size="x-small"></v-icon> View only
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-card v-if="!hasItems" class="pa-empty-card" variant="outlined">
      <v-card-text class="text-center py-8">
        <v-icon icon="mdi-check-circle-outline" size="64" color="success" class="mb-3"></v-icon>
        <div class="text-h6 font-weight-bold mb-2">No pending approvals</div>
        <div class="text-body-2 text-medium-emphasis">Nothing in this queue with the current filters.</div>
      </v-card-text>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3500" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootPendingApprovals);
    }
})();
