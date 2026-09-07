/**
 * Reusable APM document-number search (year + live query).
 * Exposes window.ApmDocumentSearch.setupSearchState / mount.
 */
(function () {
    'use strict';

    const DEBOUNCE_MS = 300;
    const MIN_CHARS = 3;

    function currentYear() {
        return String(new Date().getFullYear());
    }

    function readConfig(mountEl) {
        const script = mountEl.querySelector('.apm-document-search-config');
        if (!script) return null;
        try {
            return JSON.parse(script.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    /**
     * @param {object} cfg
     * @param {{ reactive: Function, watch: Function, onMounted: Function, computed: Function }} vueApis
     * @returns {object} reactive search state for embedding in parent templates
     */
    function setupSearchState(cfg, vueApis) {
        const { reactive, watch, onMounted, computed } = vueApis;
        const searchUrl = (cfg && cfg.searchUrl) || '';
        const yearsUrl = (cfg && cfg.yearsUrl) || '';

        const state = reactive({
            year: String((cfg && cfg.defaultYear) || currentYear()),
            years: Array.isArray(cfg && cfg.years) && cfg.years.length
                ? cfg.years.map(String)
                : [currentYear()],
            q: '',
            results: [],
            loading: false,
            error: '',
            panelOpen: false,
            placeholder: (cfg && cfg.placeholder) || 'Document number…',
            minChars: MIN_CHARS,
            yearItems: [],
            hint: '',
            openResult: function () {},
            onFocus: function () {},
        });

        let debounceTimer = null;
        let requestSeq = 0;

        function syncDerived() {
            state.yearItems = (state.years || []).map((y) => ({ title: String(y), value: String(y) }));
            const len = (state.q || '').trim().length;
            if (len === 0) state.hint = 'Type a document number to search';
            else if (len < MIN_CHARS) state.hint = 'Type at least 3 characters';
            else state.hint = '';
        }

        async function loadYears() {
            if (!yearsUrl) return;
            try {
                const res = await fetch(yearsUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                const json = await res.json();
                const list = (json && json.data && json.data.years) || [];
                if (Array.isArray(list) && list.length) {
                    state.years = list.map(String);
                }
                if (!state.years.includes(String(state.year))) {
                    state.years = [String(state.year)].concat(state.years);
                }
                syncDerived();
            } catch (e) {
                // keep defaults
            }
        }

        async function runSearch() {
            const query = (state.q || '').trim();
            if (!searchUrl || query.length < MIN_CHARS || !state.year) {
                state.results = [];
                state.loading = false;
                state.error = '';
                return;
            }

            const seq = ++requestSeq;
            state.loading = true;
            state.error = '';
            state.panelOpen = true;

            try {
                const params = new URLSearchParams({
                    q: query,
                    year: String(state.year),
                });
                const res = await fetch(`${searchUrl}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (seq !== requestSeq) return;

                if (!res.ok) {
                    let message = 'Search failed';
                    try {
                        const body = await res.json();
                        if (body && body.message) message = body.message;
                    } catch (e) { /* ignore */ }
                    state.error = message;
                    state.results = [];
                    return;
                }

                const json = await res.json();
                state.results = Array.isArray(json && json.data) ? json.data : [];
            } catch (e) {
                if (seq !== requestSeq) return;
                state.error = 'Search failed';
                state.results = [];
            } finally {
                if (seq === requestSeq) {
                    state.loading = false;
                }
            }
        }

        function scheduleSearch() {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(runSearch, DEBOUNCE_MS);
        }

        state.openResult = function (item) {
            if (!item || !item.show_url || item.show_url === '#') return;
            window.location.href = item.show_url;
        };

        state.onFocus = function () {
            if ((state.q || '').trim().length >= MIN_CHARS) {
                state.panelOpen = true;
            }
        };

        watch(
            () => state.q,
            () => {
                if (state.q == null) state.q = '';
                syncDerived();
                const len = (state.q || '').trim().length;
                if (len < MIN_CHARS) {
                    state.results = [];
                    state.error = '';
                    state.loading = false;
                    if (len === 0) state.panelOpen = false;
                    if (debounceTimer) clearTimeout(debounceTimer);
                    return;
                }
                scheduleSearch();
            }
        );

        watch(
            () => state.year,
            () => {
                if ((state.q || '').trim().length >= MIN_CHARS) {
                    scheduleSearch();
                }
            }
        );

        onMounted(() => {
            syncDerived();
            loadYears();
        });

        syncDerived();
        return state;
    }

    const SEARCH_TEMPLATE = `
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
`;

    function mount(mountEl, cfg) {
        if (!mountEl || !cfg || !window.Vue || !window.Vuetify) return null;

        mountEl.innerHTML = '';
        const { createApp, reactive, watch, onMounted, computed } = Vue;
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
                            surface: '#ffffff',
                            background: '#f8fafc',
                        },
                    },
                },
            },
        });

        const app = createApp({
            setup() {
                const docSearch = setupSearchState(cfg, { reactive, watch, onMounted, computed });
                return { docSearch };
            },
            template: `<v-app class="apm-doc-search-standalone"><v-container fluid class="pa-0">${SEARCH_TEMPLATE}</v-container></v-app>`,
        });

        app.use(vuetify);
        app.mount(mountEl);
        return app;
    }

    function bootFromDom(mountEl) {
        const cfg = readConfig(mountEl);
        if (!cfg) return;
        mount(mountEl, cfg);
    }

    window.ApmDocumentSearch = {
        setupSearchState,
        SEARCH_TEMPLATE,
        mount,
        bootFromDom,
        MIN_CHARS,
    };
})();
