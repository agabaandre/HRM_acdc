/**
 * Intramural SAP Budget Execution — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'intramural-sap-budget-execution-app';
    let appInstance = null;

    function fmtMoney(n) {
        return '$' + (parseFloat(n) || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function fmtPct(n) {
        if (n === null || n === undefined || Number.isNaN(Number(n))) return '—';
        return (Number(n) * 100).toFixed(1) + '%';
    }

    function boot(mountEl, cfg) {
        if (!mountEl || !cfg || !window.Vue || !window.Vuetify) return;

        const { createApp, ref, computed, onMounted, watch } = window.Vue;
        const { createVuetify } = window.Vuetify;
        const vuetify = createVuetify({ theme: { defaultTheme: 'light' } });

        appInstance = createApp({
            setup() {
                const year = ref(cfg.currentYear || new Date().getFullYear());
                const years = ref(cfg.years || [year.value]);
                const divisionId = ref(null);
                const search = ref('');
                const divisions = ref([]);
                const items = ref([]);
                const loading = ref(false);
                const error = ref(null);
                const drawer = ref(false);
                const selected = ref(null);
                const docsLoading = ref(false);
                const docs = ref({ activities: [], service_requests: [] });

                const headers = [
                    { title: 'Budget code', key: 'code', sortable: true },
                    { title: 'Division', key: 'division_name', sortable: true },
                    { title: 'Approved budget', key: 'approved_budget', sortable: true },
                    { title: 'Budget balance', key: 'budget_balance', sortable: true },
                    { title: 'Execution rate', key: 'execution_rate', sortable: true },
                ];

                async function loadData() {
                    loading.value = true;
                    error.value = null;
                    try {
                        const params = new URLSearchParams();
                        params.set('year', String(year.value));
                        if (divisionId.value) params.set('division_id', String(divisionId.value));
                        if (search.value.trim()) params.set('search', search.value.trim());
                        const res = await fetch(cfg.routes.data + '?' + params.toString(), {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load');
                        items.value = json.data || [];
                        divisions.value = (json.meta && json.meta.divisions) || [];
                    } catch (e) {
                        error.value = e.message || 'Failed to load';
                        items.value = [];
                    } finally {
                        loading.value = false;
                    }
                }

                async function openCode(row) {
                    selected.value = row;
                    drawer.value = true;
                    docsLoading.value = true;
                    docs.value = { activities: [], service_requests: [] };
                    try {
                        const url = String(cfg.routes.documents).replace('__ID__', String(row.id));
                        const res = await fetch(url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load documents');
                        docs.value = json.data || { activities: [], service_requests: [] };
                    } catch (e) {
                        error.value = e.message || 'Failed to load documents';
                    } finally {
                        docsLoading.value = false;
                    }
                }

                onMounted(loadData);
                watch([year, divisionId], loadData);

                return {
                    year,
                    years,
                    divisionId,
                    search,
                    divisions,
                    items,
                    loading,
                    error,
                    drawer,
                    selected,
                    docsLoading,
                    docs,
                    headers,
                    loadData,
                    openCode,
                    fmtMoney,
                    fmtPct,
                };
            },
            template: `
<v-app class="isbe-vuetify-app">
  <v-container fluid class="pa-0">
    <v-card class="isbe-hero mb-4 pa-5" elevation="0" rounded="lg">
      <div class="text-h5 font-weight-bold mb-1">Intramural SAP Budget Execution</div>
      <div class="text-body-2" style="opacity:.9">Approved budget, released balance, and execution rate by fund code (from Finance SAP sync).</div>
    </v-card>

    <v-card class="mb-4 pa-4" rounded="lg">
      <v-row dense align="center">
        <v-col cols="12" md="2">
          <v-select v-model="year" :items="years" label="Year" density="comfortable" hide-details></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-select
            v-model="divisionId"
            :items="[{id:null,name:'All divisions'}, ...divisions]"
            item-title="name"
            item-value="id"
            label="Division"
            density="comfortable"
            hide-details
            clearable
          ></v-select>
        </v-col>
        <v-col cols="12" md="4">
          <v-text-field v-model="search" label="Search code" density="comfortable" hide-details clearable @keyup.enter="loadData"></v-text-field>
        </v-col>
        <v-col cols="12" md="2">
          <v-btn color="primary" block :loading="loading" @click="loadData">Refresh</v-btn>
        </v-col>
      </v-row>
      <div v-if="error" class="text-error mt-3">{{ error }}</div>
    </v-card>

    <v-card rounded="lg">
      <v-data-table
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id"
        density="comfortable"
        class="elevation-0"
      >
        <template #item.code="{ item }">
          <a href="#" class="isbe-code-link text-primary" @click.prevent="openCode(item)">{{ item.code }}</a>
        </template>
        <template #item.approved_budget="{ item }">{{ fmtMoney(item.approved_budget) }}</template>
        <template #item.budget_balance="{ item }">{{ fmtMoney(item.budget_balance) }}</template>
        <template #item.execution_rate="{ item }">{{ fmtPct(item.execution_rate) }}</template>
      </v-data-table>
    </v-card>

    <v-navigation-drawer v-model="drawer" location="right" temporary width="440">
      <div class="pa-4" v-if="selected">
        <div class="text-h6 mb-1">{{ selected.code }}</div>
        <div class="text-caption text-medium-emphasis mb-3">{{ selected.activity || selected.division_name || '—' }}</div>
        <v-list density="compact" class="mb-3">
          <v-list-item title="Approved" :subtitle="fmtMoney(selected.approved_budget)"></v-list-item>
          <v-list-item title="Balance" :subtitle="fmtMoney(selected.budget_balance)"></v-list-item>
          <v-list-item title="Execution" :subtitle="fmtPct(selected.execution_rate)"></v-list-item>
        </v-list>
        <div v-if="docsLoading" class="text-medium-emphasis">Loading approved documents…</div>
        <template v-else>
          <div class="text-subtitle-2 mb-2">Approved activities ({{ docs.activities.length }})</div>
          <v-list density="compact" class="mb-4">
            <v-list-item v-for="a in docs.activities" :key="'a'+a.id" :href="a.url" target="_blank" :title="a.title" :subtitle="'#'+a.id"></v-list-item>
            <v-list-item v-if="!docs.activities.length" title="No approved activities"></v-list-item>
          </v-list>
          <div class="text-subtitle-2 mb-2">Approved service requests ({{ docs.service_requests.length }})</div>
          <v-list density="compact">
            <v-list-item v-for="s in docs.service_requests" :key="'s'+s.id" :href="s.url" target="_blank" :title="s.title" :subtitle="'#'+s.id"></v-list-item>
            <v-list-item v-if="!docs.service_requests.length" title="No approved service requests"></v-list-item>
          </v-list>
        </template>
      </div>
    </v-navigation-drawer>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
        appInstance.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, boot);
    }
})();
