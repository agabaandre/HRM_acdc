/**
 * Activity Report form (scaffold; expanded in later tasks) — Vue 3 + Vuetify 3
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

        const { createApp, ref } = Vue;
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
                const saving = ref(false);
                const snackbar = ref({ show: false, text: '', color: 'success' });
                const missionItems = (cfg.missionTypes || []).map((v) => ({
                    title: String(v).replace(/_/g, ' '),
                    value: v,
                }));

                async function save() {
                    if (!cfg.canEdit) return;
                    saving.value = true;
                    try {
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

                return { cfg, form, saving, snackbar, missionItems, save };
            },
            template: `
              <v-app class="arf-vuetify-app">
                <v-container fluid class="pa-0">
                  <v-card>
                    <v-card-title class="d-flex justify-space-between align-center">
                      <div>
                        <div class="text-h6">Activity Report</div>
                        <div class="text-caption">{{ form.document_number || 'Draft' }} · {{ form.overall_status }}</div>
                      </div>
                      <v-btn v-if="cfg.canEdit" color="primary" :loading="saving" @click="save">Save draft</v-btn>
                    </v-card-title>
                    <v-card-text>
                      <v-alert v-if="!cfg.canEdit" type="info" variant="tonal" class="mb-4">View only — report is not editable in its current status.</v-alert>
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
                        <v-col cols="12">
                          <v-textarea v-model="form.narrative_achievements" label="Narrative of key achievements" rows="4" :disabled="!cfg.canEdit" />
                        </v-col>
                        <v-col cols="12">
                          <v-textarea v-model="form.challenges_recommendations" label="Challenges & recommendations" rows="3" :disabled="!cfg.canEdit" />
                        </v-col>
                      </v-row>
                      <div class="text-caption text-medium-emphasis mt-4">Related activities, AI assist, and submit arrive in the next tasks.</div>
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
