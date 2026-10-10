/**
 * Divisions directory — Vue 3 + Vuetify 3
 * Tabs: Divisions list | PRA integration (map PRA division/directorate codes)
 */
(function () {
    'use strict';

    const MOUNT_ID = 'divisions-app';

    function staffLabel(rel) {
        if (!rel) return { name: 'N/A', subtitle: 'Staff' };
        const name = [rel.fname, rel.lname].filter(Boolean).join(' ').trim() || 'N/A';
        return { name, subtitle: rel.position || 'Staff' };
    }

    function bootDivisions(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, watch, computed, reactive } = Vue;
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
                VAutocomplete: { variant: 'outlined', density: 'comfortable', hideDetails: true },
            },
        });

        const app = createApp({
            setup() {
                const mainTab = ref('divisions');

                const search = ref('');
                const page = ref(1);
                const itemsPerPage = ref(25);
                const sortBy = ref([{ key: 'division_name', order: 'asc' }]);
                const items = ref([]);
                const totalItems = ref(0);
                const loading = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'error' });
                const summary = ref({ total_divisions: 0, filtered_divisions: 0 });

                const pageSizeOptions = [
                    { title: '10 per page', value: 10 },
                    { title: '25 per page', value: 25 },
                    { title: '50 per page', value: 50 },
                ];

                const headers = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Division name', key: 'division_name', sortable: true, minWidth: 160 },
                    { title: 'Short name', key: 'division_short_name', sortable: true, width: 120 },
                    { title: 'Category', key: 'category', sortable: true, width: 120 },
                    { title: 'Division head', key: 'division_head', sortable: false, minWidth: 160 },
                    { title: 'Focal person', key: 'focal_person', sortable: false, minWidth: 160 },
                    { title: 'Admin assistant', key: 'admin_assistant', sortable: false, minWidth: 160 },
                    { title: 'Finance officer', key: 'finance_officer', sortable: false, minWidth: 160 },
                    { title: '', key: 'actions', sortable: false, align: 'end', width: 72 },
                ];

                const showingRange = computed(() => {
                    if (totalItems.value === 0) return '0–0';
                    const start = (page.value - 1) * itemsPerPage.value + 1;
                    const end = Math.min(page.value * itemsPerPage.value, totalItems.value);
                    return `${start}–${end}`;
                });

                const summaryKpis = computed(() => [
                    { key: 'total', icon: 'mdi-office-building', accent: '#119a48', value: summary.value.total_divisions, label: 'Total divisions' },
                    { key: 'filtered', icon: 'mdi-magnify', accent: '#0284c7', value: summary.value.filtered_divisions, label: 'Matching search' },
                ]);

                const exportUrl = computed(() => {
                    const params = new URLSearchParams();
                    if (search.value.trim()) params.set('search', search.value.trim());
                    const sort = sortBy.value[0];
                    if (sort?.key) {
                        params.set('sort_by', sort.key);
                        params.set('sort_direction', sort.order === 'desc' ? 'desc' : 'asc');
                    }
                    const qs = params.toString();
                    return qs ? `${cfg.routes.exportExcel}?${qs}` : cfg.routes.exportExcel;
                });

                // —— PRA integration ——
                const praSettings = reactive({
                    base_url: '',
                    api_key: '',
                    tiers: '3,4',
                    fiscal_year: '',
                    division_aliases: '',
                    timeout: 60,
                    api_key_set: false,
                    configured: false,
                });
                const praLoadingSettings = ref(false);
                const praSavingSettings = ref(false);
                const praFetching = ref(false);
                const praSavingMappings = ref(false);
                const praUnits = ref([]);
                const praSummary = ref({ total: 0, matched: 0, unmatched: 0, divisions: 0, directorates: 0, added: 0, preserved: 0 });
                const praLocalDivisions = ref([]);
                const praLocalDirectorates = ref([]);
                const praFilter = ref('all');
                const praSearch = ref('');
                const praFetchedAt = ref(null);
                const praFiscalYear = ref(null);
                const praLoaded = ref(false);

                const praFilterItems = [
                    { title: 'All', value: 'all' },
                    { title: 'Unmatched', value: 'unmatched' },
                    { title: 'Matched', value: 'matched' },
                    { title: 'Divisions', value: 'division' },
                    { title: 'Directorates', value: 'directorate' },
                ];

                const praHeaders = [
                    { title: 'PRA id', key: 'pra_division_id', sortable: true, width: 110 },
                    { title: 'PRA code', key: 'pra_code', sortable: true, width: 110 },
                    { title: 'PRA name', key: 'pra_name', sortable: true, minWidth: 180 },
                    { title: 'Type', key: 'entity_type', sortable: true, width: 120 },
                    { title: 'Match', key: 'match_source', sortable: true, width: 120 },
                    { title: 'Local division', key: 'local_division_id', sortable: false, minWidth: 240 },
                    { title: 'Local directorate', key: 'local_directorate_id', sortable: false, minWidth: 200 },
                ];

                const praKpis = computed(() => [
                    { key: 'total', icon: 'mdi-cloud-download', accent: '#119a48', value: praSummary.value.total, label: 'PRA units' },
                    { key: 'matched', icon: 'mdi-link-variant', accent: '#0284c7', value: praSummary.value.matched, label: 'Matched' },
                    { key: 'unmatched', icon: 'mdi-link-variant-off', accent: '#f59e0b', value: praSummary.value.unmatched, label: 'Unmatched' },
                    { key: 'added', icon: 'mdi-plus-circle-outline', accent: '#7c3aed', value: praSummary.value.added || 0, label: 'Added this fetch' },
                ]);

                const filteredPraUnits = computed(() => {
                    let rows = praUnits.value.slice();
                    const f = praFilter.value;
                    if (f === 'unmatched') rows = rows.filter((r) => !r.local_division_id && !r.local_directorate_id);
                    else if (f === 'matched') rows = rows.filter((r) => r.local_division_id || r.local_directorate_id);
                    else if (f === 'division' || f === 'directorate') rows = rows.filter((r) => r.entity_type === f);

                    const q = praSearch.value.trim().toLowerCase();
                    if (q) {
                        rows = rows.filter((r) =>
                            String(r.pra_code || '').toLowerCase().includes(q)
                            || String(r.pra_name || '').toLowerCase().includes(q)
                        );
                    }
                    return rows;
                });

                const divisionSelectItems = computed(() => [
                    { title: '— Not mapped —', value: null },
                    ...praLocalDivisions.value.map((d) => ({ title: d.label, value: d.id })),
                ]);

                const directorateSelectItems = computed(() => [
                    { title: '— Not mapped —', value: null },
                    ...praLocalDirectorates.value.map((d) => ({ title: d.label, value: d.id })),
                ]);

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function divisionShowUrl(id) {
                    return `${cfg.routes.show}/${id}`;
                }

                function jsonHeaders(extra = {}) {
                    return {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrfToken || '',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...extra,
                    };
                }

                function mapRow(division, index) {
                    const head = staffLabel(division.division_head || division.divisionHead);
                    const focal = staffLabel(division.focal_person || division.focalPerson);
                    const admin = staffLabel(division.admin_assistant || division.adminAssistant);
                    const finance = staffLabel(division.finance_officer || division.financeOfficer);
                    return {
                        ...division,
                        row_num: (page.value - 1) * itemsPerPage.value + index + 1,
                        head_name: head.name,
                        head_subtitle: head.subtitle,
                        focal_name: focal.name,
                        focal_subtitle: focal.subtitle,
                        admin_name: admin.name,
                        admin_subtitle: admin.subtitle,
                        finance_name: finance.name,
                        finance_subtitle: finance.subtitle,
                    };
                }

                function matchColor(source) {
                    if (source === 'auto') return 'success';
                    if (source === 'alias') return 'info';
                    if (source === 'manual') return 'primary';
                    return 'warning';
                }

                function onManualDivisionChange(item) {
                    item.user_set = true;
                    item.match_source = item.local_division_id || item.local_directorate_id ? 'manual' : 'unmatched';
                    item.match_label = item.local_division_id || item.local_directorate_id ? 'Manual' : 'Unmatched';
                    recomputePraSummary();
                }

                function onManualDirectorateChange(item) {
                    item.user_set = true;
                    item.match_source = item.local_division_id || item.local_directorate_id ? 'manual' : 'unmatched';
                    item.match_label = item.local_division_id || item.local_directorate_id ? 'Manual' : 'Unmatched';
                    recomputePraSummary();
                }

                function recomputePraSummary() {
                    const units = praUnits.value;
                    let matched = 0;
                    let divisions = 0;
                    let directorates = 0;
                    units.forEach((u) => {
                        if (u.entity_type === 'directorate') directorates++;
                        else divisions++;
                        if (u.local_division_id || u.local_directorate_id) matched++;
                    });
                    praSummary.value = {
                        ...praSummary.value,
                        total: units.length,
                        matched,
                        unmatched: units.length - matched,
                        divisions,
                        directorates,
                    };
                }

                async function loadItems() {
                    loading.value = true;
                    try {
                        const params = new URLSearchParams({
                            page: String(page.value),
                            pageSize: String(itemsPerPage.value),
                            search: search.value.trim(),
                        });
                        const sort = sortBy.value[0];
                        if (sort?.key) {
                            params.set('sort_by', sort.key);
                            params.set('sort_direction', sort.order === 'desc' ? 'desc' : 'asc');
                        }
                        const res = await fetch(`${cfg.routes.ajax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Could not load divisions.');

                        items.value = (data.data || []).map(mapRow);
                        totalItems.value = Number(data.recordsTotal || 0);
                        summary.value = {
                            total_divisions: data.summary?.total_divisions ?? totalItems.value,
                            filtered_divisions: data.summary?.filtered_divisions ?? totalItems.value,
                        };
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify(e.message || 'Could not load divisions.');
                    } finally {
                        loading.value = false;
                    }
                }

                async function loadPraSettings() {
                    if (!cfg.routes?.praSettings) return;
                    praLoadingSettings.value = true;
                    try {
                        const res = await fetch(cfg.routes.praSettings, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Could not load PRA settings.');
                        const d = json.data || {};
                        praSettings.base_url = d.base_url || '';
                        praSettings.api_key = '';
                        praSettings.tiers = d.tiers || '3,4';
                        praSettings.fiscal_year = d.fiscal_year ?? '';
                        praSettings.division_aliases = d.division_aliases || '';
                        praSettings.timeout = d.timeout || 60;
                        praSettings.api_key_set = !!d.api_key_set;
                        praSettings.configured = !!d.configured;
                    } catch (e) {
                        notify(e.message || 'Could not load PRA settings.');
                    } finally {
                        praLoadingSettings.value = false;
                    }
                }

                async function loadSavedMappings() {
                    if (!cfg.routes?.praMappings) return;
                    try {
                        const res = await fetch(cfg.routes.praMappings, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (!res.ok || !json.success) return;
                        praLocalDivisions.value = json.data?.local_divisions || [];
                        praLocalDirectorates.value = json.data?.local_directorates || [];
                        if ((json.data?.units || []).length && !praUnits.value.length) {
                            praUnits.value = json.data.units;
                            recomputePraSummary();
                        }
                        if (json.meta?.settings) {
                            const d = json.meta.settings;
                            praSettings.configured = !!d.configured;
                            praSettings.api_key_set = !!d.api_key_set;
                        }
                    } catch (_) { /* ignore */ }
                }

                async function savePraSettings() {
                    praSavingSettings.value = true;
                    try {
                        const body = {
                            base_url: String(praSettings.base_url || '').trim(),
                            tiers: praSettings.tiers,
                            fiscal_year: praSettings.fiscal_year === '' || praSettings.fiscal_year == null
                                ? null
                                : Number(praSettings.fiscal_year),
                            division_aliases: praSettings.division_aliases,
                            timeout: Number(praSettings.timeout) || 60,
                        };
                        if (praSettings.api_key) body.api_key = praSettings.api_key;

                        const res = await fetch(cfg.routes.praSettingsSave, {
                            method: 'PUT',
                            headers: jsonHeaders(),
                            body: JSON.stringify(body),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Could not save PRA settings.');
                        const d = json.data || {};
                        praSettings.api_key = '';
                        praSettings.base_url = d.base_url || praSettings.base_url;
                        praSettings.tiers = d.tiers || praSettings.tiers;
                        praSettings.fiscal_year = d.fiscal_year ?? '';
                        praSettings.division_aliases = d.division_aliases || '';
                        praSettings.timeout = d.timeout || 60;
                        praSettings.api_key_set = !!d.api_key_set;
                        praSettings.configured = !!d.configured;
                        notify(json.message || 'PRA settings saved.', 'success');
                    } catch (e) {
                        notify(e.message || 'Could not save PRA settings.');
                    } finally {
                        praSavingSettings.value = false;
                    }
                }

                async function fetchFromPra() {
                    if (!praSettings.configured && !praSettings.api_key_set) {
                        notify('Save PRA API URL and key first.');
                        return;
                    }
                    praFetching.value = true;
                    try {
                        const body = {};
                        if (praSettings.fiscal_year !== '' && praSettings.fiscal_year != null) {
                            body.fiscal_year = Number(praSettings.fiscal_year);
                        }
                        const res = await fetch(cfg.routes.praFetch, {
                            method: 'POST',
                            headers: jsonHeaders(),
                            body: JSON.stringify(body),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'PRA fetch failed.');
                        const d = json.data || {};
                        praUnits.value = (d.units || []).map((u) => ({ ...u, user_set: false }));
                        praSummary.value = {
                            total: 0, matched: 0, unmatched: 0, divisions: 0, directorates: 0, added: 0, preserved: 0,
                            ...(d.summary || {}),
                        };
                        praLocalDivisions.value = d.local_divisions || [];
                        praLocalDirectorates.value = d.local_directorates || [];
                        praFetchedAt.value = d.fetched_at || null;
                        praFiscalYear.value = d.fiscal_year || null;
                        notify(json.message || `Synced ${praSummary.value.total} PRA unit(s).`, 'success');
                    } catch (e) {
                        notify(e.message || 'PRA fetch failed.');
                    } finally {
                        praFetching.value = false;
                    }
                }

                async function savePraMappings() {
                    if (!praUnits.value.length) {
                        notify('Fetch from PRA first.');
                        return;
                    }
                    praSavingMappings.value = true;
                    try {
                        const units = praUnits.value.map((u) => ({
                            pra_code: u.pra_code,
                            pra_division_id: u.pra_division_id || u.pra_code,
                            pra_name: u.pra_name,
                            entity_type: u.entity_type,
                            local_division_id: u.local_division_id || null,
                            local_directorate_id: u.local_directorate_id || null,
                            match_source: u.match_source,
                            user_set: !!u.user_set || u.match_source === 'manual',
                        }));
                        const res = await fetch(cfg.routes.praMappingsSave, {
                            method: 'PUT',
                            headers: jsonHeaders(),
                            body: JSON.stringify({ units }),
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Could not save mappings.');
                        notify(json.message || 'Mappings saved.', 'success');
                    } catch (e) {
                        notify(e.message || 'Could not save mappings.');
                    } finally {
                        praSavingMappings.value = false;
                    }
                }

                let searchTimer = null;
                watch(search, () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        page.value = 1;
                        loadItems();
                    }, 400);
                });

                watch(itemsPerPage, () => {
                    page.value = 1;
                    loadItems();
                });

                watch(page, () => loadItems());

                watch(sortBy, () => {
                    page.value = 1;
                    loadItems();
                }, { deep: true });

                watch(mainTab, (tab) => {
                    if (tab === 'pra' && !praLoaded.value) {
                        praLoaded.value = true;
                        loadPraSettings();
                        loadSavedMappings();
                    }
                });

                loadItems();

                return {
                    cfg,
                    mainTab,
                    search,
                    page,
                    itemsPerPage,
                    sortBy,
                    items,
                    totalItems,
                    loading,
                    snackbar,
                    summaryKpis,
                    headers,
                    pageSizeOptions,
                    showingRange,
                    exportUrl,
                    divisionShowUrl,
                    // PRA
                    praSettings,
                    praLoadingSettings,
                    praSavingSettings,
                    praFetching,
                    praSavingMappings,
                    praUnits,
                    praSummary,
                    praKpis,
                    praFilter,
                    praFilterItems,
                    praSearch,
                    praHeaders,
                    filteredPraUnits,
                    divisionSelectItems,
                    directorateSelectItems,
                    praFetchedAt,
                    praFiscalYear,
                    matchColor,
                    onManualDivisionChange,
                    onManualDirectorateChange,
                    savePraSettings,
                    fetchFromPra,
                    savePraMappings,
                };
            },
            template: `
<v-app class="dv-vuetify-app">
  <v-container fluid class="pa-0">
    <v-alert v-if="cfg.flash?.success" type="success" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.success }}</v-alert>
    <v-alert v-if="cfg.flash?.error" type="error" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.error }}</v-alert>

    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4 px-md-6 pb-0">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-office-building" color="primary" class="me-2"></v-icon>
            Divisions
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            View divisions and map PRA division / directorate codes
          </div>
        </div>
      </v-card-title>

      <v-tabs v-model="mainTab" color="primary" class="px-4 px-md-6">
        <v-tab value="divisions" prepend-icon="mdi-office-building">Divisions</v-tab>
        <v-tab value="pra" prepend-icon="mdi-cloud-sync">PRA integration</v-tab>
      </v-tabs>
      <v-divider></v-divider>

      <v-window v-model="mainTab">
        <v-window-item value="divisions">
          <v-card-text class="px-4 px-md-6 pt-4">
            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-4">
              <div class="d-flex flex-wrap align-center gap-2">
                <v-text-field
                  v-model="search"
                  placeholder="Search divisions…"
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  style="min-width: 240px; max-width: 320px;"
                  @click:clear="search = ''"
                ></v-text-field>
                <v-select v-model="itemsPerPage" :items="pageSizeOptions" item-title="title" item-value="value" style="width: 140px;"></v-select>
              </div>
              <v-btn :href="exportUrl" variant="outlined" color="success" prepend-icon="mdi-file-excel">Export</v-btn>
            </div>

            <v-row dense class="mb-4 dv-kpi-row">
              <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" sm="3">
                <v-card class="dv-kpi-card" elevation="0" :style="{ '--dv-kpi-accent': kpi.accent }">
                  <v-card-text class="d-flex align-center pa-4">
                    <div class="dv-kpi-icon-wrap me-3 flex-shrink-0" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                      <v-icon :icon="kpi.icon" size="22"></v-icon>
                    </div>
                    <div class="min-w-0">
                      <div class="dv-kpi-value">{{ kpi.value }}</div>
                      <div class="dv-kpi-label">{{ kpi.label }}</div>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <v-data-table
              v-model:sort-by="sortBy"
              class="dv-table elevation-0 border rounded-lg"
              :headers="headers"
              :items="items"
              :loading="loading"
              :items-per-page="itemsPerPage"
              hide-default-footer
              must-sort
            >
              <template #item.row_num="{ item }">
                <v-chip size="small" variant="tonal" color="secondary">{{ item.row_num }}</v-chip>
              </template>
              <template #item.division_name="{ item }">
                <span class="font-weight-medium text-wrap">{{ item.division_name }}</span>
              </template>
              <template #item.division_short_name="{ item }">
                <v-chip v-if="item.division_short_name" size="small" color="primary" variant="tonal">{{ item.division_short_name }}</v-chip>
                <span v-else class="text-medium-emphasis">—</span>
              </template>
              <template #item.category="{ item }">
                <v-chip v-if="item.category" size="small" color="secondary" variant="tonal">{{ item.category }}</v-chip>
                <span v-else class="text-medium-emphasis">—</span>
              </template>
              <template #item.division_head="{ item }">
                <div>
                  <div class="text-body-2">{{ item.head_name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.head_subtitle }}</div>
                </div>
              </template>
              <template #item.focal_person="{ item }">
                <div>
                  <div class="text-body-2">{{ item.focal_name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.focal_subtitle }}</div>
                </div>
              </template>
              <template #item.admin_assistant="{ item }">
                <div>
                  <div class="text-body-2">{{ item.admin_name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.admin_subtitle }}</div>
                </div>
              </template>
              <template #item.finance_officer="{ item }">
                <div>
                  <div class="text-body-2">{{ item.finance_name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.finance_subtitle }}</div>
                </div>
              </template>
              <template #item.actions="{ item }">
                <v-btn :href="divisionShowUrl(item.id)" icon="mdi-eye-outline" variant="text" color="primary" size="small" title="View division"></v-btn>
              </template>
              <template #no-data>
                <v-alert type="info" variant="tonal" class="ma-4">No divisions found. Try adjusting your search.</v-alert>
              </template>
              <template #bottom>
                <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
                  <span class="text-body-2 text-medium-emphasis">Showing {{ showingRange }} of {{ totalItems }} divisions</span>
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
          </v-card-text>
        </v-window-item>

        <v-window-item value="pra">
          <v-card-text class="px-4 px-md-6 pt-4">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
              APM-local PRA settings (does not change Staff Portal → Settings → Workplan / PRA).
              Refetch keeps existing matches and only adds new PRA codes. Each row stores a PRA division id for wiring.
              A daily job (<code>pra:sync-org-units --notify</code>) emails the app support address when mismatches remain.
            </v-alert>

            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1 font-weight-medium py-3 px-4">
                <v-icon icon="mdi-cog-outline" class="me-2" size="20"></v-icon>
                PRA API settings
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="px-4 pt-4">
                <div v-if="praLoadingSettings" class="text-medium-emphasis mb-3">Loading settings…</div>
                <template v-else>
                  <v-text-field
                    v-model="praSettings.base_url"
                    label="PRA API URL"
                    hint="e.g. https://pra.africacdc.org/api/public/workplan"
                    persistent-hint
                    class="mb-3"
                    :hide-details="false"
                  ></v-text-field>
                  <v-text-field
                    v-model="praSettings.api_key"
                    label="API key"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="praSettings.api_key_set ? 'Leave blank to keep the current key' : 'Required — from PRA'"
                    :hint="praSettings.api_key_set ? 'A key is already saved. Enter a new value only to replace it.' : 'Fetch stays disabled until a key is saved.'"
                    persistent-hint
                    class="mb-3"
                    :hide-details="false"
                  ></v-text-field>
                  <v-row dense>
                    <v-col cols="12" md="4">
                      <v-text-field v-model="praSettings.tiers" label="Tiers" hint="Default 3,4" persistent-hint :hide-details="false"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4">
                      <v-text-field v-model="praSettings.fiscal_year" type="number" label="Fiscal year" hint="Blank = current year" persistent-hint clearable :hide-details="false"></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4">
                      <v-text-field v-model.number="praSettings.timeout" type="number" min="10" max="300" label="Timeout (seconds)" :hide-details="false"></v-text-field>
                    </v-col>
                  </v-row>
                  <v-text-field
                    v-model="praSettings.division_aliases"
                    label="Division aliases"
                    hint="PRA code → local short name, e.g. MIS:DHIS,CT:RD&CT"
                    persistent-hint
                    class="mt-3"
                    :hide-details="false"
                  ></v-text-field>
                </template>
              </v-card-text>
              <v-card-actions class="px-4 pb-4">
                <v-btn color="primary" :loading="praSavingSettings" @click="savePraSettings">Save PRA settings</v-btn>
                <v-chip v-if="praSettings.configured" size="small" color="success" variant="tonal" class="ms-2">Configured</v-chip>
                <v-chip v-else size="small" color="warning" variant="tonal" class="ms-2">Not configured</v-chip>
              </v-card-actions>
            </v-card>

            <div class="d-flex flex-wrap align-center justify-space-between gap-2 mb-3">
              <div class="d-flex flex-wrap align-center gap-2">
                <v-btn color="primary" prepend-icon="mdi-cloud-download" :loading="praFetching" :disabled="!praSettings.configured && !praSettings.api_key_set" @click="fetchFromPra">
                  Sync from PRA
                </v-btn>
                <v-btn color="success" variant="tonal" prepend-icon="mdi-content-save" :loading="praSavingMappings" :disabled="!praUnits.length" @click="savePraMappings">
                  Save mappings
                </v-btn>
                <span v-if="praFetchedAt" class="text-caption text-medium-emphasis">
                  FY {{ praFiscalYear }} · fetched {{ praFetchedAt }}
                </span>
              </div>
              <div class="d-flex flex-wrap align-center gap-2">
                <v-text-field
                  v-model="praSearch"
                  placeholder="Search PRA units…"
                  prepend-inner-icon="mdi-magnify"
                  clearable
                  style="min-width: 200px; max-width: 260px;"
                ></v-text-field>
                <v-select
                  v-model="praFilter"
                  :items="praFilterItems"
                  item-title="title"
                  item-value="value"
                  style="width: 160px;"
                ></v-select>
              </div>
            </div>

            <v-row dense class="mb-4 dv-kpi-row">
              <v-col v-for="kpi in praKpis" :key="kpi.key" cols="6" sm="3">
                <v-card class="dv-kpi-card" elevation="0" :style="{ '--dv-kpi-accent': kpi.accent }">
                  <v-card-text class="d-flex align-center pa-4">
                    <div class="dv-kpi-icon-wrap me-3 flex-shrink-0" :style="{ background: kpi.accent + '14', color: kpi.accent }">
                      <v-icon :icon="kpi.icon" size="22"></v-icon>
                    </div>
                    <div class="min-w-0">
                      <div class="dv-kpi-value">{{ kpi.value }}</div>
                      <div class="dv-kpi-label">{{ kpi.label }}</div>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <v-data-table
              class="dv-table elevation-0 border rounded-lg"
              :headers="praHeaders"
              :items="filteredPraUnits"
              :items-per-page="50"
              :loading="praFetching"
            >
              <template #item.pra_division_id="{ item }">
                <code class="text-caption">{{ item.pra_division_id || item.pra_code }}</code>
              </template>
              <template #item.pra_code="{ item }">
                <v-chip size="small" color="primary" variant="tonal">{{ item.pra_code }}</v-chip>
              </template>
              <template #item.pra_name="{ item }">
                <span class="text-wrap">{{ item.pra_name || '—' }}</span>
              </template>
              <template #item.entity_type="{ item }">
                <v-chip size="small" :color="item.entity_type === 'directorate' ? 'purple' : 'secondary'" variant="tonal">
                  {{ item.entity_type }}
                </v-chip>
              </template>
              <template #item.match_source="{ item }">
                <v-chip size="small" :color="matchColor(item.match_source)" variant="tonal">
                  {{ item.match_label || item.match_source }}
                </v-chip>
              </template>
              <template #item.local_division_id="{ item }">
                <v-autocomplete
                  v-model="item.local_division_id"
                  :items="divisionSelectItems"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  hide-details
                  clearable
                  placeholder="Select division…"
                  style="min-width: 220px;"
                  @update:model-value="onManualDivisionChange(item)"
                ></v-autocomplete>
              </template>
              <template #item.local_directorate_id="{ item }">
                <v-autocomplete
                  v-if="item.entity_type === 'directorate'"
                  v-model="item.local_directorate_id"
                  :items="directorateSelectItems"
                  item-title="title"
                  item-value="value"
                  density="compact"
                  hide-details
                  clearable
                  placeholder="Select directorate…"
                  style="min-width: 180px;"
                  @update:model-value="onManualDirectorateChange(item)"
                ></v-autocomplete>
                <span v-else class="text-medium-emphasis">—</span>
              </template>
              <template #no-data>
                <v-alert type="info" variant="tonal" class="ma-4">
                  No PRA units yet. Configure the API key, then click <strong>Fetch from PRA</strong>.
                </v-alert>
              </template>
            </v-data-table>
          </v-card-text>
        </v-window-item>
      </v-window>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="4000" location="top">{{ snackbar.text }}</v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootDivisions);
    }
})();
