/**
 * System configs — Audit logs (Vue 3 + Vuetify 3)
 */
(function () {
    'use strict';

    const MOUNT_ID = 'audit-logs-app';

    function detectModelTable(auditTable) {
        let modelTable = String(auditTable || '').replace(/^audit_/, '');
        return modelTable.replace(/_logs$/, '');
    }

    function formatJsonBlock(value) {
        if (value == null || value === '') return null;
        if (typeof value === 'object') return JSON.stringify(value, null, 2);
        const text = String(value).trim();
        if (!text) return null;
        try {
            return JSON.stringify(JSON.parse(text), null, 2);
        } catch (e) {
            return text;
        }
    }

    function actionColor(action) {
        if (action === 'created') return 'success';
        if (action === 'updated') return 'warning';
        if (action === 'deleted') return 'error';
        if (action === 'reversed') return 'info';
        return 'secondary';
    }

    function reversalMeta(action) {
        if (action === 'created') return { label: 'Delete', icon: 'mdi-delete-outline', defaultType: 'delete' };
        if (action === 'deleted') return { label: 'Recover', icon: 'mdi-backup-restore', defaultType: 'restore' };
        if (action === 'updated') return { label: 'Restore', icon: 'mdi-history', defaultType: 'restore' };
        return { label: 'Reverse', icon: 'mdi-undo-variant', defaultType: 'restore' };
    }

    function staffInitials(name) {
        if (!name || !String(name).trim()) return 'S';
        return String(name)
            .trim()
            .split(/\s+/)
            .map((p) => p[0] || '')
            .join('')
            .slice(0, 2)
            .toUpperCase();
    }

    function tableLabel(table) {
        return String(table || '').replace(/^audit_/, '');
    }

    function bootAuditLogs(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, reactive, computed, watch } = Vue;
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
                VCard: { rounded: 'lg', elevation: 0, variant: 'outlined' },
                VBtn: { rounded: 'lg' },
                VTextField: { variant: 'outlined', density: 'compact', hideDetails: true, color: 'primary' },
                VSelect: { variant: 'outlined', density: 'compact', hideDetails: true, color: 'primary' },
                VTextarea: { variant: 'outlined', density: 'compact', hideDetails: 'auto', color: 'primary' },
                VDataTable: { density: 'compact', hover: true },
            },
        });

        const app = createApp({
            setup() {
                const page = ref(1);
                const itemsPerPage = ref(25);
                const draw = ref(1);
                const items = ref([]);
                const totalItems = ref(0);
                const loading = ref(false);
                const stats = ref(cfg.stats || {});

                const filters = reactive({
                    search: cfg.filters?.search || '',
                    action: cfg.filters?.action || '',
                    table: cfg.filters?.table || '',
                    date_from: cfg.filters?.date_from || '',
                    date_to: cfg.filters?.date_to || '',
                    suspicious: cfg.filters?.suspicious || '',
                });

                const applied = reactive({ ...filters });

                const snackbar = ref({ show: false, text: '', color: 'success' });

                const detailOpen = ref(false);
                const detailRow = ref(null);

                const reverseOpen = ref(false);
                const reverseBusy = ref(false);
                const reverseRow = ref(null);
                const reverseForm = reactive({
                    action_type: 'restore',
                    model_table: '',
                    reason: '',
                    confirm: false,
                });

                const cleanupOpen = ref(false);
                const cleanupBusy = ref(false);
                const cleanupStats = ref({ total_logs: 0, old_logs: 0, retention_days: 365 });
                const cleanupForm = reactive({
                    retention_days: 365,
                    confirm: false,
                });

                const actionItems = computed(() => [
                    { title: 'All actions', value: '' },
                    ...(cfg.actions || []).map((a) => ({ title: a, value: a })),
                ]);

                const tableItems = computed(() => [
                    { title: 'All tables', value: '' },
                    ...(cfg.tables || []).map((t) => ({ title: tableLabel(t), value: t })),
                ]);

                const suspiciousItems = [
                    { title: 'All', value: '' },
                    { title: 'Suspicious only', value: '1' },
                    { title: 'Not suspicious', value: '0' },
                ];

                const pageSizeOptions = [
                    { title: '10', value: 10 },
                    { title: '25', value: 25 },
                    { title: '50', value: 50 },
                    { title: '100', value: 100 },
                ];

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56, align: 'center' },
                    { title: 'Action', key: 'action', sortable: false, width: 120 },
                    { title: 'Entity', key: 'entity_id', sortable: false, width: 100 },
                    { title: 'Table', key: 'source_table', sortable: false, minWidth: 140 },
                    { title: 'Causer', key: 'causer', sortable: false, minWidth: 180 },
                    { title: 'Division & duty station', key: 'division', sortable: false, minWidth: 160 },
                    { title: 'Source', key: 'source', sortable: false, width: 110 },
                    { title: 'Suspicious', key: 'is_suspicious', sortable: false, width: 110 },
                    { title: 'Date / time', key: 'created_at', sortable: false, width: 150 },
                    { title: '', key: 'actions', sortable: false, width: 64, align: 'end' },
                ];

                const statCards = computed(() => [
                    {
                        key: 'total',
                        icon: 'mdi-format-list-bulleted',
                        color: 'primary',
                        accent: '#119a48',
                        label: 'Total logs',
                        value: Number(stats.value.total_logs || 0).toLocaleString(),
                        sub: 'All time',
                    },
                    {
                        key: 'recent',
                        icon: 'mdi-clock-outline',
                        color: 'success',
                        accent: '#119a48',
                        label: 'Recent activity',
                        value: Number(stats.value.recent_activity || 0).toLocaleString(),
                        sub: 'Last 24 hours',
                    },
                    {
                        key: 'action',
                        icon: 'mdi-chart-line',
                        color: 'info',
                        accent: '#0ea5e9',
                        label: 'Top action',
                        value: stats.value.top_action || 'N/A',
                        sub: `${stats.value.top_action_count || 0} times`,
                    },
                    {
                        key: 'table',
                        icon: 'mdi-database-outline',
                        color: 'warning',
                        accent: '#f59e0b',
                        label: 'Top table',
                        value: tableLabel(stats.value.top_table || 'N/A'),
                        sub: `${stats.value.top_table_count || 0} records`,
                    },
                ]);

                const tableRangeLabel = computed(() => {
                    if (!totalItems.value) return 'No logs match the current filters';
                    const start = (page.value - 1) * itemsPerPage.value + 1;
                    const end = Math.min(page.value * itemsPerPage.value, totalItems.value);
                    return `Showing ${start}–${end} of ${Number(totalItems.value).toLocaleString()} logs`;
                });

                const exportUrl = computed(() => {
                    const params = new URLSearchParams({ tab: 'audit-logs', export: 'csv' });
                    if (applied.search) params.set('search', applied.search);
                    if (applied.action) params.set('action', applied.action);
                    if (applied.table) params.set('table', applied.table);
                    if (applied.date_from) params.set('date_from', applied.date_from);
                    if (applied.date_to) params.set('date_to', applied.date_to);
                    if (applied.suspicious) params.set('suspicious', applied.suspicious);
                    return `${cfg.routes.export}?${params.toString()}`;
                });

                const reverseCanSubmit = computed(() =>
                    reverseForm.confirm
                    && reverseForm.reason.trim().length >= 10
                    && reverseForm.model_table.trim().length > 0
                );

                const cleanupCanSubmit = computed(() =>
                    cleanupForm.confirm && Number(cleanupForm.retention_days) >= 30
                );

                function notify(text, color = 'success') {
                    snackbar.value = { show: true, text, color };
                }

                function mapRow(row, index) {
                    return {
                        ...row,
                        row_num: (page.value - 1) * itemsPerPage.value + index + 1,
                    };
                }

                function formatWhen(iso) {
                    if (!iso) return '—';
                    try {
                        const d = new Date(iso);
                        return d.toLocaleString(undefined, {
                            year: 'numeric',
                            month: 'short',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                        });
                    } catch (e) {
                        return iso;
                    }
                }

                async function loadItems() {
                    loading.value = true;
                    draw.value += 1;
                    try {
                        const params = new URLSearchParams({
                            draw: String(draw.value),
                            start: String((page.value - 1) * itemsPerPage.value),
                            length: String(itemsPerPage.value),
                            search: applied.search.trim(),
                            action: applied.action,
                            table: applied.table,
                            date_from: applied.date_from,
                            date_to: applied.date_to,
                            suspicious: applied.suspicious,
                        });
                        const res = await fetch(`${cfg.routes.data}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        if (!res.ok) throw new Error(json.message || 'Could not load audit logs.');

                        items.value = (json.data || []).map(mapRow);
                        totalItems.value = Number(json.recordsFiltered || json.recordsTotal || 0);
                        if (json.stats) stats.value = json.stats;
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify(e.message || 'Could not load audit logs.', 'error');
                    } finally {
                        loading.value = false;
                    }
                }

                function applyFilters() {
                    Object.assign(applied, filters);
                    page.value = 1;
                    loadItems();
                }

                function resetFilters() {
                    filters.search = '';
                    filters.action = '';
                    filters.table = '';
                    filters.date_from = '';
                    filters.date_to = '';
                    filters.suspicious = '';
                    applyFilters();
                }

                function openDetail(row) {
                    detailRow.value = row;
                    detailOpen.value = true;
                }

                function openReverse(row) {
                    if (!cfg.canReverse) return;
                    reverseRow.value = row;
                    const meta = reversalMeta(row.action);
                    reverseForm.action_type = meta.defaultType;
                    reverseForm.model_table = detectModelTable(row.source_table);
                    reverseForm.reason = '';
                    reverseForm.confirm = false;
                    reverseOpen.value = true;
                }

                async function submitReverse() {
                    if (!reverseRow.value || reverseBusy.value || !reverseCanSubmit.value) return;
                    reverseBusy.value = true;
                    try {
                        const res = await fetch(cfg.routes.reverse, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                            },
                            body: JSON.stringify({
                                log_id: reverseRow.value.id,
                                table: reverseRow.value.source_table,
                                model_table: reverseForm.model_table.trim(),
                                action_type: reverseForm.action_type,
                                reason: reverseForm.reason.trim(),
                            }),
                        });
                        const json = await res.json();
                        if (!json.success) throw new Error(json.message || 'Reversal failed.');
                        notify(json.message || 'Reversal completed.');
                        reverseOpen.value = false;
                        loadItems();
                    } catch (e) {
                        notify(e.message || 'Reversal failed.', 'error');
                    } finally {
                        reverseBusy.value = false;
                    }
                }

                async function openCleanup() {
                    cleanupOpen.value = true;
                    cleanupForm.confirm = false;
                    try {
                        const res = await fetch(cfg.routes.cleanupModal, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        cleanupStats.value = json;
                        cleanupForm.retention_days = json.retention_days || 365;
                    } catch (e) {
                        notify('Could not load cleanup statistics.', 'error');
                    }
                }

                async function submitCleanup() {
                    if (cleanupBusy.value || !cleanupCanSubmit.value) return;
                    cleanupBusy.value = true;
                    try {
                        const res = await fetch(cfg.routes.cleanup, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': cfg.csrf,
                            },
                            body: JSON.stringify({ retention_days: Number(cleanupForm.retention_days) }),
                        });
                        const json = await res.json();
                        if (!json.success) throw new Error(json.message || 'Cleanup failed.');
                        notify(json.message || 'Cleanup completed.');
                        cleanupOpen.value = false;
                        loadItems();
                    } catch (e) {
                        notify(e.message || 'Cleanup failed.', 'error');
                    } finally {
                        cleanupBusy.value = false;
                    }
                }

                function canReverseRow(row) {
                    return cfg.canReverse && ['created', 'updated', 'deleted'].includes(row.action);
                }

                watch(itemsPerPage, () => {
                    page.value = 1;
                    loadItems();
                });

                watch(page, () => loadItems());

                loadItems();

                return {
                    cfg,
                    page,
                    itemsPerPage,
                    items,
                    totalItems,
                    loading,
                    filters,
                    statCards,
                    headers,
                    actionItems,
                    tableItems,
                    suspiciousItems,
                    pageSizeOptions,
                    tableRangeLabel,
                    exportUrl,
                    detailOpen,
                    detailRow,
                    reverseOpen,
                    reverseBusy,
                    reverseRow,
                    reverseForm,
                    reverseCanSubmit,
                    cleanupOpen,
                    cleanupBusy,
                    cleanupStats,
                    cleanupForm,
                    cleanupCanSubmit,
                    snackbar,
                    applyFilters,
                    resetFilters,
                    openDetail,
                    openReverse,
                    submitReverse,
                    openCleanup,
                    submitCleanup,
                    canReverseRow,
                    actionColor,
                    reversalMeta,
                    staffInitials,
                    tableLabel,
                    formatWhen,
                    formatJsonBlock,
                };
            },
            template: `
<v-app class="al-vuetify-app">
  <v-container fluid class="pa-0">
    <v-row dense class="mb-4">
      <v-col v-for="card in statCards" :key="card.key" cols="12" sm="6" md="3">
        <v-card class="al-stat-card" :style="{ borderLeftColor: card.accent }">
          <v-card-text class="text-center py-4">
            <v-icon :icon="card.icon" :color="card.color" size="32" class="mb-2"></v-icon>
            <div class="text-caption text-uppercase font-weight-bold text-medium-emphasis">{{ card.label }}</div>
            <div class="text-h5 font-weight-bold mt-1 al-stat-value">{{ card.value }}</div>
            <div class="text-caption text-medium-emphasis">{{ card.sub }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card class="mb-4">
      <v-card-item>
        <v-card-title class="text-subtitle-1 font-weight-bold pa-0 d-flex align-center">
          <v-icon icon="mdi-filter-outline" size="small" class="me-2"></v-icon>
          Filters
        </v-card-title>
      </v-card-item>
      <v-divider></v-divider>
      <v-card-text>
        <v-form @submit.prevent="applyFilters">
          <v-row dense>
            <v-col cols="12" md="4">
              <v-text-field
                v-model="filters.search"
                label="Search"
                placeholder="Staff name, email, action, table, entity ID…"
                prepend-inner-icon="mdi-magnify"
                clearable
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.action" :items="actionItems" item-title="title" item-value="value" label="Action"></v-select>
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.table" :items="tableItems" item-title="title" item-value="value" label="Table"></v-select>
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-text-field
                v-model="filters.date_from"
                label="From date"
                type="date"
                prepend-inner-icon="mdi-calendar-start"
                placeholder="Select start date"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-text-field
                v-model="filters.date_to"
                label="To date"
                type="date"
                prepend-inner-icon="mdi-calendar-end"
                placeholder="Select end date"
              ></v-text-field>
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.suspicious" :items="suspiciousItems" item-title="title" item-value="value" label="Suspicious"></v-select>
            </v-col>
            <v-col cols="12" class="d-flex flex-wrap gap-2">
              <v-btn type="submit" color="primary" prepend-icon="mdi-magnify" :loading="loading">Apply filters</v-btn>
              <v-btn variant="outlined" prepend-icon="mdi-close" @click="resetFilters">Clear</v-btn>
              <v-spacer class="d-none d-md-flex"></v-spacer>
              <v-btn :href="exportUrl" variant="outlined" color="success" prepend-icon="mdi-download">Export CSV</v-btn>
              <v-btn variant="outlined" color="warning" prepend-icon="mdi-delete-sweep" @click="openCleanup">Cleanup old logs</v-btn>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-item class="d-flex flex-wrap align-center justify-space-between">
        <v-card-title class="text-subtitle-1 font-weight-bold pa-0 d-flex align-center">
          <v-icon icon="mdi-format-list-bulleted" size="small" class="me-2"></v-icon>
          Audit logs
        </v-card-title>
        <template #append>
          <span class="text-caption text-medium-emphasis">{{ tableRangeLabel }}</span>
        </template>
      </v-card-item>
      <v-divider></v-divider>
      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading"
        :items-per-page="itemsPerPage"
        hide-default-footer
        class="al-table"
      >
        <template #item.row_num="{ item }">
          <v-chip size="x-small" variant="tonal" color="primary" label>#{{ item.row_num }}</v-chip>
        </template>
        <template #item.action="{ item }">
          <v-chip size="small" variant="flat" :color="actionColor(item.action)" label>{{ item.action }}</v-chip>
        </template>
        <template #item.entity_id="{ item }">
          <span class="font-weight-medium">ID: {{ item.entity_id ?? 'N/A' }}</span>
        </template>
        <template #item.source_table="{ item }">
          <v-chip size="x-small" variant="outlined" label>{{ tableLabel(item.source_table) }}</v-chip>
        </template>
        <template #item.causer="{ item }">
          <div v-if="item.causer_id" class="d-flex align-start">
            <v-avatar size="28" color="primary" variant="tonal" class="me-2 mt-1">
              <span class="text-caption font-weight-bold">{{ staffInitials(item.causer_name) }}</span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">{{ item.causer_name || 'Unknown User' }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.causer_job_title || 'N/A' }}</div>
              <div class="text-caption text-medium-emphasis">{{ item.causer_email || 'N/A' }}</div>
            </div>
          </div>
          <span v-else class="text-medium-emphasis">System</span>
        </template>
        <template #item.division="{ item }">
          <div v-if="item.causer_id">
            <v-chip size="x-small" color="primary" variant="tonal" class="mb-1">{{ item.causer_division_name || 'N/A' }}</v-chip>
            <div><v-chip size="x-small" color="secondary" variant="tonal">{{ item.causer_duty_station_name || 'N/A' }}</v-chip></div>
          </div>
          <span v-else class="text-medium-emphasis">—</span>
        </template>
        <template #item.source="{ item }">
          <v-chip size="x-small" color="info" variant="tonal">{{ item.source || 'Unknown' }}</v-chip>
        </template>
        <template #item.is_suspicious="{ item }">
          <v-chip
            v-if="item.is_suspicious"
            size="x-small"
            color="error"
            variant="flat"
            prepend-icon="mdi-shield-alert"
            :title="item.suspicious_reasons || 'Suspicious activity'"
          >Yes</v-chip>
          <v-chip v-else size="x-small" color="success" variant="tonal" prepend-icon="mdi-shield-check">No</v-chip>
        </template>
        <template #item.created_at="{ item }">
          <div class="text-body-2">{{ formatWhen(item.created_at) }}</div>
        </template>
        <template #item.actions="{ item }">
          <v-menu location="bottom end">
            <template #activator="{ props }">
              <v-btn v-bind="props" icon="mdi-dots-vertical" variant="text" size="small" aria-label="Row actions"></v-btn>
            </template>
            <v-list density="compact">
              <v-list-item prepend-icon="mdi-eye-outline" title="View details" @click="openDetail(item)"></v-list-item>
              <v-list-item
                v-if="canReverseRow(item)"
                :prepend-icon="reversalMeta(item.action).icon"
                :title="reversalMeta(item.action).label"
                @click="openReverse(item)"
              ></v-list-item>
            </v-list>
          </v-menu>
        </template>
        <template #no-data>
          <div class="text-center py-8">
            <v-icon icon="mdi-database-off-outline" size="40" color="disabled" class="mb-2"></v-icon>
            <p class="text-medium-emphasis mb-0">No audit logs match your filters.</p>
          </div>
        </template>
        <template #bottom>
          <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
            <v-select
              v-model="itemsPerPage"
              :items="pageSizeOptions"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              style="max-width: 100px;"
            ></v-select>
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
    </v-card>

    <v-dialog v-model="detailOpen" max-width="960" scrollable>
      <v-card v-if="detailRow">
        <v-card-title class="d-flex align-center">
          <v-icon icon="mdi-information-outline" color="primary" class="me-2"></v-icon>
          Audit log details
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pt-4">
          <v-row dense>
            <v-col cols="12" md="6">
              <v-list density="compact" class="al-detail-list">
                <v-list-item title="ID" :subtitle="String(detailRow.id)"></v-list-item>
                <v-list-item title="Action">
                  <template #append>
                    <v-chip size="small" :color="actionColor(detailRow.action)" variant="flat" label>{{ detailRow.action }}</v-chip>
                  </template>
                </v-list-item>
                <v-list-item title="Entity ID" :subtitle="String(detailRow.entity_id ?? 'N/A')"></v-list-item>
                <v-list-item title="Table" :subtitle="detailRow.source_table"></v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list density="compact" class="al-detail-list">
                <v-list-item title="Causer" :subtitle="detailRow.causer_name || 'Unknown User'"></v-list-item>
                <v-list-item title="Email" :subtitle="detailRow.causer_email || 'N/A'"></v-list-item>
                <v-list-item title="Job title" :subtitle="detailRow.causer_job_title || 'N/A'"></v-list-item>
                <v-list-item title="Division" :subtitle="detailRow.causer_division_name || 'N/A'"></v-list-item>
                <v-list-item title="Duty station" :subtitle="detailRow.causer_duty_station_name || 'N/A'"></v-list-item>
                <v-list-item title="Source" :subtitle="detailRow.source || 'Unknown'"></v-list-item>
                <v-list-item title="Created" :subtitle="formatWhen(detailRow.created_at)"></v-list-item>
              </v-list>
            </v-col>
          </v-row>
          <v-row v-if="formatJsonBlock(detailRow.old_values) || formatJsonBlock(detailRow.new_values)" dense class="mt-2">
            <v-col v-if="formatJsonBlock(detailRow.old_values)" cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title class="text-subtitle-2 text-error">Old values</v-card-title>
                <v-card-text><pre class="al-json">{{ formatJsonBlock(detailRow.old_values) }}</pre></v-card-text>
              </v-card>
            </v-col>
            <v-col v-if="formatJsonBlock(detailRow.new_values)" cols="12" md="6">
              <v-card variant="outlined">
                <v-card-title class="text-subtitle-2 text-success">New values</v-card-title>
                <v-card-text><pre class="al-json">{{ formatJsonBlock(detailRow.new_values) }}</pre></v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-card v-if="formatJsonBlock(detailRow.metadata)" variant="outlined" class="mt-3">
            <v-card-title class="text-subtitle-2">Metadata</v-card-title>
            <v-card-text><pre class="al-json">{{ formatJsonBlock(detailRow.metadata) }}</pre></v-card-text>
          </v-card>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="reverseOpen" max-width="560" persistent>
      <v-card v-if="reverseRow">
        <v-card-title class="d-flex align-center">
          <v-icon icon="mdi-undo-variant" color="warning" class="me-2"></v-icon>
          Reverse audit action
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pt-4">
          <v-alert type="warning" variant="tonal" density="comfortable" class="mb-4">
            This will create a reversal entry for <strong>{{ reverseRow.action }}</strong> on entity #{{ reverseRow.entity_id }}.
          </v-alert>
          <v-text-field
            v-model="reverseForm.model_table"
            label="Model table"
            hint="The database table for the model (auto-detected from audit table)"
            persistent-hint
            class="mb-3"
          ></v-text-field>
          <div class="text-subtitle-2 mb-2">Action type</div>
          <v-radio-group v-model="reverseForm.action_type" inline density="compact" class="mb-3">
            <v-radio label="Restore record" value="restore"></v-radio>
            <v-radio label="Delete record" value="delete"></v-radio>
          </v-radio-group>
          <v-textarea
            v-model="reverseForm.reason"
            label="Reason"
            rows="3"
            maxlength="500"
            counter="500"
            hint="Minimum 10 characters"
            persistent-hint
          ></v-textarea>
          <v-checkbox
            v-model="reverseForm.confirm"
            label="I understand this creates a permanent reversal entry and cannot be undone."
            hide-details
            class="mt-2"
          ></v-checkbox>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="reverseOpen = false">Cancel</v-btn>
          <v-btn color="warning" variant="flat" :loading="reverseBusy" :disabled="!reverseCanSubmit" @click="submitReverse">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="cleanupOpen" max-width="520" persistent>
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-icon icon="mdi-delete-sweep" color="warning" class="me-2"></v-icon>
          Cleanup old audit logs
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pt-4">
          <v-alert type="warning" variant="tonal" density="comfortable" class="mb-4">
            This permanently deletes old audit log entries. This cannot be undone.
          </v-alert>
          <v-row dense class="mb-4 text-center">
            <v-col cols="4">
              <div class="text-caption text-medium-emphasis">Total logs</div>
              <div class="text-h6 font-weight-bold text-primary">{{ Number(cleanupStats.total_logs || 0).toLocaleString() }}</div>
            </v-col>
            <v-col cols="4">
              <div class="text-caption text-medium-emphasis">Old logs</div>
              <div class="text-h6 font-weight-bold text-warning">{{ Number(cleanupStats.old_logs || 0).toLocaleString() }}</div>
            </v-col>
            <v-col cols="4">
              <div class="text-caption text-medium-emphasis">Retention</div>
              <div class="text-h6 font-weight-bold text-info">{{ cleanupStats.retention_days || 365 }} days</div>
            </v-col>
          </v-row>
          <v-text-field
            v-model.number="cleanupForm.retention_days"
            label="Retention period (days)"
            type="number"
            min="30"
            max="3650"
            hint="Logs older than this will be deleted"
            persistent-hint
          ></v-text-field>
          <v-checkbox
            v-model="cleanupForm.confirm"
            label="I understand this action cannot be undone."
            hide-details
            class="mt-2"
          ></v-checkbox>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="cleanupOpen = false">Cancel</v-btn>
          <v-btn color="warning" variant="flat" :loading="cleanupBusy" :disabled="!cleanupCanSubmit" @click="submitCleanup">Cleanup</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="5000" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootAuditLogs);
    }
})();
