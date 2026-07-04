/**
 * Returned memos — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'returned-memos-app';

    const TYPE_COLORS = {
        Matrix: 'warning',
        'Special Memo': 'info',
        'Non-Travel Memo': 'success',
        'Single Memo': 'error',
        'Other Memo': 'secondary',
        'Service Request': 'primary',
        ARF: 'secondary',
        'Change Request': 'deep-purple',
    };

    function fmtDate(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
                + ' ' + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '—';
        }
    }

    function navigateCategory(cfg, category) {
        const url = new URL(cfg.routes.index, window.location.origin);
        url.searchParams.set('category', category || 'all');
        const target = url.pathname + url.search;
        if (typeof Livewire !== 'undefined' && Livewire.navigate) {
            Livewire.navigate(target);
        } else {
            window.location.href = target;
        }
    }

    function submitHiddenForm(action, method, csrf) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value = csrf;
        form.appendChild(token);
        if (method && method !== 'POST') {
            const m = document.createElement('input');
            m.type = 'hidden';
            m.name = '_method';
            m.value = method;
            form.appendChild(m);
        }
        document.body.appendChild(form);
        form.submit();
    }

    function bootReturnedMemos(mountEl, cfg) {
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
                VTextField: { variant: 'outlined', density: 'comfortable' },
                VSelect: { variant: 'outlined', density: 'comfortable' },
            },
        });

        const app = createApp({
            setup() {
                const search = ref('');
                const category = ref(cfg.filters.category || 'all');
                const snackbar = ref({ show: false, text: '', color: 'info' });
                const archiveDialog = ref(false);
                const deleteDialog = ref(false);
                const dialogTitle = ref('');
                const dialogAction = ref('');
                const dialogMethod = ref('POST');

                const summary = computed(() => cfg.summaryStats || { total_returned: 0, by_category: {} });
                const byCat = computed(() => summary.value.by_category || {});

                const memoCardCount = computed(() =>
                    (byCat.value['Special Memo'] || 0)
                    + (byCat.value['Non-Travel Memo'] || 0)
                    + (byCat.value['Single Memo'] || 0)
                    + (byCat.value['Other Memo'] || 0)
                );

                const requestsCardCount = computed(() =>
                    (byCat.value['Service Request'] || 0) + (byCat.value['ARF'] || 0)
                );

                const kpis = computed(() => [
                    { key: 'total', icon: 'mdi-undo-variant', accent: '#119a48', value: summary.value.total_returned || 0, label: 'Total returned' },
                    { key: 'matrix', icon: 'mdi-grid', accent: '#d97706', value: byCat.value['Matrix'] || 0, label: 'Matrices' },
                    { key: 'memos', icon: 'mdi-file-document-multiple', accent: '#475569', value: memoCardCount.value, label: 'Memos' },
                    { key: 'requests', icon: 'mdi-clipboard-list-outline', accent: '#9f2240', value: requestsCardCount.value, label: 'Requests' },
                ]);

                const allRows = computed(() => {
                    const rows = [];
                    const grouped = cfg.returnedMemos || {};
                    Object.keys(grouped).forEach((cat) => {
                        (grouped[cat] || []).forEach((item) => {
                            rows.push({ ...item, categoryName: cat });
                        });
                    });
                    return rows;
                });

                const filteredRows = computed(() => {
                    const q = search.value.trim().toLowerCase();
                    if (!q) return allRows.value;
                    return allRows.value.filter((row) => {
                        const hay = [
                            row.title,
                            row.document_number,
                            row.submitted_by_text,
                            row.type,
                            row.categoryName,
                        ].join(' ').toLowerCase();
                        return hay.includes(q);
                    });
                });

                const categoryItems = computed(() =>
                    (cfg.groupedCategories || []).map((c) => ({
                        title: `${c.label} (${c.count})`,
                        value: c.value,
                    }))
                );

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Title', key: 'title', sortable: false, minWidth: 180 },
                    { title: 'Type', key: 'type', sortable: false, width: 120 },
                    { title: 'Document #', key: 'document_number', sortable: false, width: 120 },
                    { title: 'Submitted by', key: 'submitted_by_text', sortable: false, minWidth: 130 },
                    { title: 'Date returned', key: 'date_received', sortable: false, minWidth: 150 },
                    { title: 'Level', key: 'approval_level', sortable: false, width: 80, align: 'center' },
                    { title: 'Status', key: 'overall_status', sortable: false, width: 100 },
                    { title: 'Actions', key: 'actions', sortable: false, minWidth: 200 },
                ];

                function applyCategory() {
                    navigateCategory(cfg, category.value);
                }

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

                function typeColor(type) {
                    return TYPE_COLORS[type] || 'secondary';
                }

                function openArchive(item) {
                    dialogTitle.value = item.title || 'this item';
                    dialogAction.value = item.archive_url || '';
                    dialogMethod.value = 'POST';
                    archiveDialog.value = true;
                }

                function openDelete(item) {
                    dialogTitle.value = item.title || 'this item';
                    dialogAction.value = item.delete_url || '';
                    dialogMethod.value = 'DELETE';
                    deleteDialog.value = true;
                }

                function confirmArchive() {
                    if (dialogAction.value) {
                        submitHiddenForm(dialogAction.value, 'POST', cfg.csrf);
                    }
                    archiveDialog.value = false;
                }

                function confirmDelete() {
                    if (dialogAction.value) {
                        submitHiddenForm(dialogAction.value, 'DELETE', cfg.csrf);
                    }
                    deleteDialog.value = false;
                }

                return {
                    cfg,
                    search,
                    category,
                    snackbar,
                    archiveDialog,
                    deleteDialog,
                    dialogTitle,
                    kpis,
                    filteredRows,
                    categoryItems,
                    headers,
                    fmtDate,
                    applyCategory,
                    refreshPage,
                    exportSoon,
                    typeColor,
                    openArchive,
                    openDelete,
                    confirmArchive,
                    confirmDelete,
                };
            },
            template: `
<v-app class="rm-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-4" elevation="1">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div class="text-h6 font-weight-bold text-primary">
          <v-icon icon="mdi-undo-variant" class="me-2"></v-icon>
          Returned / draft memos
        </div>
        <div class="d-flex gap-2">
          <v-btn color="primary" variant="tonal" prepend-icon="mdi-refresh" size="small" @click="refreshPage">Refresh</v-btn>
          <v-btn variant="outlined" color="warning" prepend-icon="mdi-download" size="small" @click="exportSoon">Export</v-btn>
        </div>
      </v-card-title>
    </v-card>

    <v-row dense class="mb-4">
      <v-col v-for="kpi in kpis" :key="kpi.key" cols="6" md="3">
        <v-card class="rm-kpi-card" elevation="1" :style="{ '--rm-accent': kpi.accent }">
          <v-card-text class="text-center pa-4">
            <div class="rm-kpi-icon-wrap mx-auto mb-2" :style="{ background: kpi.accent + '1a', color: kpi.accent }">
              <v-icon :icon="kpi.icon" size="24"></v-icon>
            </div>
            <div class="rm-kpi-value">{{ kpi.value }}</div>
            <div class="rm-kpi-label">{{ kpi.label }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-card class="mb-4" elevation="1">
      <v-card-text>
        <v-row dense align="end">
          <v-col cols="12" md="5">
            <v-text-field v-model="search" label="Search" prepend-inner-icon="mdi-magnify" clearable hide-details
              placeholder="Title, document number, submitted by…"></v-text-field>
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="category" :items="categoryItems" label="Category" hide-details></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-btn color="primary" variant="flat" block prepend-icon="mdi-filter" @click="applyCategory">Apply filter</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card elevation="1">
      <v-data-table
        :headers="headers"
        :items="filteredRows"
        :items-per-page="25"
        density="comfortable"
        class="rm-returned-table"
      >
        <template #item.row_num="{ index }">{{ index + 1 }}</template>
        <template #item.title="{ item }">
          <div class="font-weight-medium text-wrap">{{ item.title }}</div>
        </template>
        <template #item.type="{ item }">
          <v-chip size="small" :color="typeColor(item.type)" variant="tonal">{{ item.type }}</v-chip>
        </template>
        <template #item.document_number="{ item }">
          <span class="text-medium-emphasis">{{ item.document_number || '—' }}</span>
        </template>
        <template #item.submitted_by_text="{ item }">{{ item.submitted_by_text || '—' }}</template>
        <template #item.date_received="{ item }">{{ fmtDate(item.date_received) }}</template>
        <template #item.approval_level="{ item }">
          <v-chip size="x-small" variant="outlined">{{ item.approval_level }}</v-chip>
        </template>
        <template #item.overall_status="{ item }">
          <v-chip size="small" :color="item.overall_status === 'returned' ? 'info' : 'secondary'" variant="flat">
            {{ item.overall_status }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex flex-wrap gap-1 py-1">
            <v-btn :href="item.view_url" color="primary" variant="flat" size="x-small" prepend-icon="mdi-eye">Open</v-btn>
            <v-btn
              v-if="item.can_edit && item.edit_url"
              :href="item.edit_url"
              color="warning"
              variant="tonal"
              size="x-small"
              prepend-icon="mdi-pencil"
            >Edit</v-btn>
            <v-btn
              v-if="item.can_archive && item.archive_url"
              color="error"
              variant="tonal"
              size="x-small"
              prepend-icon="mdi-archive"
              @click="openArchive(item)"
            >Archive</v-btn>
            <v-btn
              v-if="item.can_delete && item.delete_url"
              color="error"
              variant="flat"
              size="x-small"
              prepend-icon="mdi-delete"
              @click="openDelete(item)"
            >Delete</v-btn>
          </div>
        </template>
        <template #no-data>
          <v-alert type="info" variant="tonal" class="ma-4">No returned memos match your search or filters.</v-alert>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="archiveDialog" max-width="480">
      <v-card>
        <v-card-title>Archive returned memo</v-card-title>
        <v-card-text>
          Archive <strong>{{ dialogTitle }}</strong>? It will be hidden from active lists until an administrator unarchives it.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="archiveDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="confirmArchive">Archive</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="deleteDialog" max-width="480">
      <v-card>
        <v-card-title>Confirm delete</v-card-title>
        <v-card-text>
          Delete <strong>{{ dialogTitle }}</strong>? This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="flat" @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3500" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootReturnedMemos);
    }
})();
