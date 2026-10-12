/**
 * Reporting & Finance Registry — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'reporting-registry-app';

    function boot(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, onMounted } = Vue;
        const { createVuetify } = Vuetify;

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48', secondary: '#64748b', success: '#119a48',
                            error: '#dc3545', info: '#0ea5e9', warning: '#f59e0b',
                            surface: '#ffffff', background: '#f8fafc',
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
                const items = ref([]);
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'success' });
                const previewOpen = ref(false);
                const preview = ref(null);
                const previewLoading = ref(false);

                const headers = [
                    { title: 'Ref', key: 'document_number', width: '140px' },
                    { title: 'Type', key: 'type_label', width: '120px' },
                    { title: 'Funding', key: 'is_external_funded', width: '110px' },
                    { title: 'Title', key: 'title' },
                    { title: 'Dates', key: 'dates', width: '160px' },
                    { title: 'Process', key: 'process_status', width: '110px' },
                    { title: 'Report', key: 'report_status', width: '120px' },
                    { title: 'Actions', key: 'actions', sortable: false, width: '280px' },
                ];

                async function load() {
                    loading.value = true;
                    try {
                        const res = await fetch(cfg.dataUrl, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        items.value = (json.data || []).map((r) => ({
                            ...r,
                            dates: [r.start_date, r.end_date].filter(Boolean).join(' → '),
                        }));
                    } catch (e) {
                        snackbar.value = { show: true, text: 'Failed to load registry', color: 'error' };
                    } finally {
                        loading.value = false;
                    }
                }

                async function postAction(url, row) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                        },
                        body: JSON.stringify({
                            source_type: row.source_type,
                            source_id: row.source_id,
                        }),
                    });
                    if (!res.ok) {
                        const err = await res.json().catch(() => ({}));
                        throw new Error(err.message || 'Action failed');
                    }
                    await load();
                }

                async function markStarted(row) {
                    try {
                        await postAction(cfg.startUrl, row);
                        snackbar.value = { show: true, text: 'Marked started', color: 'success' };
                    } catch (e) {
                        snackbar.value = { show: true, text: e.message || 'Failed', color: 'error' };
                    }
                }

                async function markProcessed(row) {
                    try {
                        await postAction(cfg.processUrl, row);
                        snackbar.value = { show: true, text: 'Marked processed', color: 'success' };
                    } catch (e) {
                        snackbar.value = { show: true, text: e.message || 'Failed', color: 'error' };
                    }
                }

                function openReport(row) {
                    const u = new URL(cfg.openReportUrl, window.location.origin);
                    u.searchParams.set('source_type', row.source_type);
                    u.searchParams.set('source_id', String(row.source_id));
                    window.location.href = u.toString();
                }

                async function openPreview(row) {
                    previewOpen.value = true;
                    preview.value = null;
                    previewLoading.value = true;
                    try {
                        const u = new URL(cfg.previewUrl, window.location.origin);
                        u.searchParams.set('source_type', row.source_type);
                        u.searchParams.set('source_id', String(row.source_id));
                        const res = await fetch(u.toString(), { headers: { Accept: 'application/json' } });
                        if (!res.ok) {
                            throw new Error(res.status === 404 ? 'No report yet' : 'Preview failed');
                        }
                        const json = await res.json();
                        preview.value = json.data;
                    } catch (e) {
                        snackbar.value = { show: true, text: e.message || 'Preview failed', color: 'error' };
                        previewOpen.value = false;
                    } finally {
                        previewLoading.value = false;
                    }
                }

                onMounted(load);

                return {
                    cfg, items, loading, headers, snackbar, previewOpen, preview, previewLoading,
                    markStarted, markProcessed, openReport, openPreview, load,
                };
            },
            template: `
              <v-app class="rr-vuetify-app">
                <v-container fluid class="pa-0">
                  <v-card>
                    <v-card-title class="d-flex align-center justify-space-between flex-wrap ga-2">
                      <div>
                        <div class="text-h6">Reporting &amp; Finance Registry</div>
                        <div class="text-caption text-medium-emphasis">Q{{ cfg.quarter }} {{ cfg.year }}</div>
                      </div>
                      <v-btn color="primary" variant="tonal" :loading="loading" @click="load">Refresh</v-btn>
                    </v-card-title>
                    <v-card-text>
                      <v-data-table :headers="headers" :items="items" :loading="loading" item-value="source_id" class="bg-transparent">
                        <template #item.is_external_funded="{ item }">
                          <v-chip v-if="item.is_external_funded" size="small" color="warning" variant="tonal">External</v-chip>
                          <span v-else class="text-medium-emphasis">—</span>
                        </template>
                        <template #item.process_status="{ item }">
                          <v-chip size="small" :color="item.process_status === 'processed' ? 'success' : (item.process_status === 'started' ? 'info' : 'secondary')" variant="tonal">
                            {{ item.process_status }}
                          </v-chip>
                        </template>
                        <template #item.report_status="{ item }">
                          <v-chip size="small" variant="outlined">{{ item.report_status }}</v-chip>
                        </template>
                        <template #item.actions="{ item }">
                          <div class="d-flex flex-wrap ga-1">
                            <v-btn v-if="cfg.canProcess" size="small" variant="tonal" color="info" @click="markStarted(item)">Start</v-btn>
                            <v-btn v-if="cfg.canProcess" size="small" variant="tonal" color="success" @click="markProcessed(item)">Process</v-btn>
                            <v-btn size="small" color="primary" @click="openReport(item)">Open report</v-btn>
                            <v-btn size="small" variant="text" @click="openPreview(item)">Preview</v-btn>
                          </div>
                        </template>
                      </v-data-table>
                    </v-card-text>
                  </v-card>

                  <v-dialog v-model="previewOpen" max-width="720">
                    <v-card>
                      <v-card-title>Report preview</v-card-title>
                      <v-card-text>
                        <div v-if="previewLoading" class="text-medium-emphasis">Loading…</div>
                        <template v-else-if="preview">
                          <div class="mb-2"><strong>Mission type:</strong> {{ preview.mission_type || '—' }}</div>
                          <div class="mb-2"><strong>Activity:</strong> {{ preview.activity_name_code || '—' }}</div>
                          <div class="mb-2"><strong>Status:</strong> {{ preview.overall_status }}</div>
                          <div class="mb-2"><strong>Location:</strong> {{ preview.location_venue || '—' }}</div>
                          <div class="mb-2"><strong>Achievements:</strong></div>
                          <div class="text-body-2" style="white-space: pre-wrap">{{ preview.narrative_achievements || '—' }}</div>
                        </template>
                      </v-card-text>
                      <v-card-actions>
                        <v-spacer />
                        <v-btn variant="text" @click="previewOpen = false">Close</v-btn>
                      </v-card-actions>
                    </v-card>
                  </v-dialog>

                  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">{{ snackbar.text }}</v-snackbar>
                </v-container>
              </v-app>
            `,
        });

        app.use(vuetify);
        app.mount(mountEl);
        window.ApmVuetifyPage.register(MOUNT_ID, () => {
            try { app.unmount(); } catch (e) {}
        });
    }

    window.ApmVuetifyPage.registerBooter('reporting-registry', boot);
})();
