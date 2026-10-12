/**
 * Activity Report form — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'activity-report-form-app';

    function boot(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }
        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, watch } = Vue;
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
        });

        const app = createApp({
            setup() {
                const form = ref({ ...(cfg.report || {}) });
                const related = ref([...(cfg.related || [])]);
                const relatedItems = ref([...(cfg.related || [])]);
                const relatedSearch = ref('');
                const relatedLoading = ref(false);
                const saving = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'success' });
                const missionItems = (cfg.missionTypes || []).map((v) => ({
                    title: String(v).replace(/_/g, ' '),
                    value: v,
                }));

                let searchTimer = null;
                watch(relatedSearch, (q) => {
                    clearTimeout(searchTimer);
                    if (!q || String(q).length < 2) return;
                    searchTimer = setTimeout(() => searchRelated(String(q)), 300);
                });

                async function searchRelated(q) {
                    relatedLoading.value = true;
                    try {
                        const u = new URL(cfg.relatedSearchUrl, window.location.origin);
                        u.searchParams.set('q', q);
                        const res = await fetch(u.toString(), { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        const incoming = json.data || [];
                        const byValue = new Map();
                        related.value.forEach((r) => byValue.set(r.value, r));
                        incoming.forEach((r) => byValue.set(r.value, r));
                        relatedItems.value = Array.from(byValue.values());
                    } catch (e) {
                        // ignore
                    } finally {
                        relatedLoading.value = false;
                    }
                }

                async function saveRelated() {
                    if (!cfg.canEdit) return;
                    const payload = {
                        related: related.value.map((r) => ({
                            related_source_type: r.related_source_type,
                            related_source_id: r.related_source_id,
                        })),
                    };
                    const res = await fetch(cfg.relatedSyncUrl, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                        },
                        body: JSON.stringify(payload),
                    });
                    if (!res.ok) throw new Error('Related activities save failed');
                }

                async function save() {
                    if (!cfg.canEdit) return;
                    saving.value = true;
                    try {
                        await saveRelated();
                        const res = await fetch(cfg.updateUrl, {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken,
                            },
                            body: JSON.stringify({
                                mission_type: form.value.mission_type,
                                location_venue: form.value.location_venue,
                                narrative_achievements: form.value.narrative_achievements,
                                challenges_recommendations: form.value.challenges_recommendations,
                                objectives: form.value.objectives,
                                participants_count: form.value.participants_count,
                                member_states_covered: form.value.member_states_covered,
                                key_strategic_needs: form.value.key_strategic_needs,
                                means_of_verification: form.value.means_of_verification,
                                budget_code: form.value.budget_code,
                                total_cost_usd: form.value.total_cost_usd,
                                logistics_actuals: form.value.logistics_actuals,
                                mission_extras: form.value.mission_extras,
                            }),
                        });
                        if (!res.ok) throw new Error('Save failed');
                        snackbar.value = { show: true, text: 'Saved', color: 'success' };
                    } catch (e) {
                        snackbar.value = { show: true, text: e.message || 'Save failed', color: 'error' };
                    } finally {
                        saving.value = false;
                    }
                }

                async function toggleNotExecuted() {
                    if (!cfg.canEdit) return;
                    const next = !form.value.not_executed;
                    const res = await fetch(cfg.notExecutedUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                        },
                        body: JSON.stringify({ not_executed: next }),
                    });
                    if (!res.ok) {
                        snackbar.value = { show: true, text: 'Could not update not-executed', color: 'error' };
                        return;
                    }
                    form.value.not_executed = next;
                    snackbar.value = {
                        show: true,
                        text: next ? 'Marked not executed (reminders stop)' : 'Cleared not-executed',
                        color: 'success',
                    };
                }

                return {
                    cfg, form, related, relatedItems, relatedSearch, relatedLoading,
                    saving, snackbar, missionItems, save, toggleNotExecuted,
                };
            },
            template: `
              <v-app class="arf-vuetify-app">
                <v-container fluid class="pa-0">
                  <v-card>
                    <v-card-title class="d-flex justify-space-between align-center flex-wrap ga-2">
                      <div>
                        <div class="text-h6">Activity Report</div>
                        <div class="text-caption">{{ form.document_number || 'Draft' }} · {{ form.overall_status }}</div>
                      </div>
                      <div class="d-flex ga-2">
                        <v-btn v-if="cfg.canEdit" variant="tonal" color="warning" @click="toggleNotExecuted">
                          {{ form.not_executed ? 'Clear not executed' : 'Not executed' }}
                        </v-btn>
                        <v-btn v-if="cfg.canEdit" color="primary" :loading="saving" @click="save">Save draft</v-btn>
                      </div>
                    </v-card-title>
                    <v-card-text>
                      <v-alert v-if="!cfg.canEdit" type="info" variant="tonal" class="mb-4">View only — report is not editable in its current status.</v-alert>
                      <v-alert v-if="form.not_executed" type="warning" variant="tonal" class="mb-4">Marked not executed — reminders will not send.</v-alert>

                      <div class="text-subtitle-2 mb-2">Related activities</div>
                      <v-autocomplete
                        v-model="related"
                        v-model:search="relatedSearch"
                        :items="relatedItems"
                        item-title="title"
                        item-value="value"
                        label="Search and tag related activities"
                        multiple
                        chips
                        closable-chips
                        :loading="relatedLoading"
                        :disabled="!cfg.canEdit"
                        return-object
                        class="mb-4"
                      >
                        <template #item="{ props, item }">
                          <v-list-item v-bind="props">
                            <template #prepend>
                              <v-checkbox-btn :model-value="related.some(r => r.value === item.raw.value)" />
                            </template>
                          </v-list-item>
                        </template>
                      </v-autocomplete>
                      <div class="text-caption text-medium-emphasis mb-4">Context only — tags do not merge form fields. AI can optionally include them later.</div>

                      <v-row dense>
                        <v-col cols="12" md="6">
                          <v-select v-model="form.mission_type" :items="missionItems" label="Mission type" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field v-model="form.location_venue" label="Location & venue" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.objectives" label="Objectives" rows="3" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12" md="4">
                          <v-text-field v-model.number="form.participants_count" type="number" label="Participants count" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12" md="8">
                          <v-text-field v-model="form.member_states_covered" label="Member States covered" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.key_strategic_needs" label="Key strategic needs" rows="2" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.narrative_achievements" label="Narrative of key achievements" rows="4" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.challenges_recommendations" label="Challenges & recommendations" rows="3" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.means_of_verification" label="Means of verification" rows="2" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field v-model="form.budget_code" label="Budget code" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12" md="6">
                          <v-text-field v-model="form.total_cost_usd" type="number" label="Total cost (USD)" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.logistics_actuals" label="Logistics actuals" rows="2" :disabled="!cfg.canEdit" />
                        </v-col>
                      </v-row>
                      <div class="text-caption text-medium-emphasis mt-4">Submit for HOD approval and AI assist come next.</div>
                    </v-card-text>
                  </v-card>
                  <v-snackbar v-model="snackbar.show" :color="snackbar.color">{{ snackbar.text }}</v-snackbar>
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

    window.ApmVuetifyPage.registerBooter('activity-report-form', boot);
})();
