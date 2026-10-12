/**
 * RBM Activity Reporting form — Vue 3 + Vuetify 3
 * Structure aligned to Africa CDC Activity Report template + PRA reporting UI.
 */
(function () {
    'use strict';

    const MOUNT_ID = 'activity-report-form-app';
    const RICH_FIELDS = [
        'objectives',
        'key_strategic_needs',
        'narrative_achievements',
        'key_challenges',
        'recommendations',
        'means_of_verification',
        'logistics_actuals',
    ];

    function daysBetween(start, end) {
        if (!start || !end) return null;
        const a = new Date(start + 'T00:00:00');
        const b = new Date(end + 'T00:00:00');
        if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime()) || b < a) return null;
        return Math.round((b - a) / 86400000) + 1;
    }

    function boot(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }
        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch, onMounted, nextTick, onBeforeUnmount } = Vue;
        const { createVuetify } = Vuetify;
        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48', secondary: '#475569', success: '#119a48',
                            error: '#dc3545', info: '#0ea5e9', warning: '#f59e0b',
                            surface: '#ffffff', background: '#f8fafc',
                            'on-surface': '#0f172a',
                        },
                    },
                },
            },
            defaults: {
                VTextField: { variant: 'outlined', color: 'primary', density: 'comfortable' },
                VSelect: { variant: 'outlined', color: 'primary', density: 'comfortable' },
                VAutocomplete: { variant: 'outlined', color: 'primary', density: 'comfortable' },
                VCombobox: { variant: 'outlined', color: 'primary', density: 'comfortable' },
                VTextarea: { variant: 'outlined', color: 'primary', density: 'comfortable' },
                VBtn: { rounded: 'lg' },
                VCard: { rounded: 'lg', elevation: 2 },
            },
        });

        const richFieldList = Array.isArray(cfg.richFields) && cfg.richFields.length
            ? cfg.richFields
            : RICH_FIELDS;
        const guidance = cfg.fieldGuidance || {};
        const extrasMap = cfg.extrasByMissionType || {};

        const app = createApp({
            setup() {
                const report = cfg.report || {};
                const form = ref({
                    ...report,
                    mission_type: report.mission_type || null,
                    location: report.location || '',
                    venue: report.venue || '',
                    key_challenges: report.key_challenges || '',
                    recommendations: report.recommendations || '',
                    member_states: Array.isArray(report.member_states) ? [...report.member_states] : [],
                    mission_extras: { ...(report.mission_extras || {}) },
                    attachments: Array.isArray(report.attachments) ? [...report.attachments] : [],
                    other_organiser_staff_ids: Array.isArray(report.other_organiser_staff_ids)
                        ? [...report.other_organiser_staff_ids]
                        : [],
                });

                const related = ref([...(cfg.related || [])]);
                const relatedItems = ref([...(cfg.relatedSuggestions || cfg.related || [])]);
                const relatedSearch = ref('');
                const relatedLoading = ref(false);
                const staffItems = ref([...(cfg.staffOptions || [])]);
                const staffSearch = ref('');
                const staffLoading = ref(false);
                const otherStaffSearch = ref('');
                const uploading = ref(false);
                const saving = ref(false);
                const fileInput = ref(null);
                const snackbar = ref({ show: false, text: '', color: 'success' });

                const showParticipants = cfg.showParticipants !== false;
                const showBudget = cfg.showBudget !== false;
                const quarterLabel = cfg.quarterLabel || '';
                const missionItems = cfg.missionTypes || [];
                const locationOptions = cfg.locationOptions || [];
                const memberStateOptions = cfg.memberStateOptions || [];

                const missionExtrasFields = computed(() => {
                    const t = form.value.mission_type;
                    if (!t) return [];
                    return extrasMap[t] || extrasMap.others || [];
                });

                const missionExtrasTitle = computed(() => {
                    const t = form.value.mission_type;
                    const found = missionItems.find((m) => m.value === t);
                    if (!found) return 'Additional information (optional)';
                    if (['official_engagement', 'training', 'events'].includes(t)) {
                        return 'Additional specific information — ' + found.title;
                    }
                    return 'Additional information (optional) — ' + found.title;
                });

                const durationLabel = computed(() => {
                    const d = form.value.duration_days;
                    if (!d) return '';
                    return d + (d === 1 ? ' day' : ' days');
                });

                watch(
                    () => [form.value.start_date, form.value.end_date],
                    ([s, e]) => {
                        const d = daysBetween(s, e);
                        if (d !== null) form.value.duration_days = d;
                    }
                );

                let searchTimer = null;
                watch(relatedSearch, (q) => {
                    clearTimeout(searchTimer);
                    const term = String(q || '').trim();
                    if (term.length === 1) return;
                    searchTimer = setTimeout(() => searchRelated(term), 250);
                });

                let staffTimer = null;
                function queueStaffSearch(q, which) {
                    clearTimeout(staffTimer);
                    const term = String(q || '').trim();
                    if (term.length === 1) return;
                    staffTimer = setTimeout(() => searchStaff(term), 250);
                    if (which === 'lead') staffSearch.value = q;
                    else otherStaffSearch.value = q;
                }

                function syncRichFieldsFromDom() {
                    if (window.ApmQuillEditor && typeof window.ApmQuillEditor.syncAll === 'function') {
                        window.ApmQuillEditor.syncAll(mountEl);
                    }
                    richFieldList.forEach((field) => {
                        const ta = mountEl.querySelector('textarea[data-arf-field="' + field + '"]');
                        if (ta) form.value[field] = ta.value;
                    });
                }

                function bootRichEditors() {
                    if (!window.ApmQuillEditor || typeof window.ApmQuillEditor.upgradeTextarea !== 'function') {
                        return;
                    }
                    richFieldList.forEach((field) => {
                        const ta = mountEl.querySelector('textarea[data-arf-field="' + field + '"]');
                        if (!ta || ta.dataset.apmQuillUpgraded === '1') return;
                        ta.value = form.value[field] || '';
                        const entry = window.ApmQuillEditor.upgradeTextarea(ta, {
                            toolbar: 'full',
                            minHeight: '140px',
                        });
                        if (entry && entry.quill && !cfg.canEdit) {
                            entry.quill.enable(false);
                        }
                    });
                }

                async function searchRelated(q) {
                    relatedLoading.value = true;
                    try {
                        const u = new URL(cfg.relatedSearchUrl, window.location.origin);
                        if (q) u.searchParams.set('q', q);
                        const res = await fetch(u.toString(), {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('Related search failed');
                        const json = await res.json();
                        const incoming = Array.isArray(json.data) ? json.data : [];
                        const byValue = new Map();
                        related.value.forEach((r) => byValue.set(r.value, r));
                        incoming.forEach((r) => byValue.set(r.value, r));
                        relatedItems.value = Array.from(byValue.values());
                    } catch (e) {
                        // keep
                    } finally {
                        relatedLoading.value = false;
                    }
                }

                function onRelatedMenu(isOpen) {
                    if (isOpen && relatedItems.value.length <= related.value.length) {
                        searchRelated(String(relatedSearch.value || '').trim());
                    }
                }

                async function searchStaff(q) {
                    staffLoading.value = true;
                    try {
                        const u = new URL(cfg.staffSearchUrl, window.location.origin);
                        if (q) u.searchParams.set('q', q);
                        const res = await fetch(u.toString(), {
                            credentials: 'same-origin',
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error('Staff search failed');
                        const json = await res.json();
                        const incoming = Array.isArray(json.data) ? json.data : [];
                        const byValue = new Map();
                        staffItems.value.forEach((s) => byValue.set(s.value, s));
                        incoming.forEach((s) => byValue.set(s.value, s));
                        staffItems.value = Array.from(byValue.values());
                    } catch (e) {
                        // keep
                    } finally {
                        staffLoading.value = false;
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

                function buildPayload() {
                    syncRichFieldsFromDom();
                    return {
                        mission_type: form.value.mission_type,
                        start_date: form.value.start_date,
                        end_date: form.value.end_date,
                        duration_days: form.value.duration_days,
                        location: form.value.location,
                        venue: form.value.venue,
                        lead_organiser_staff_id: form.value.lead_organiser_staff_id,
                        other_organiser_staff_ids: form.value.other_organiser_staff_ids,
                        objectives: form.value.objectives,
                        narrative_achievements: form.value.narrative_achievements,
                        key_challenges: form.value.key_challenges,
                        recommendations: form.value.recommendations,
                        participants_count: form.value.participants_count,
                        member_states: form.value.member_states,
                        key_strategic_needs: form.value.key_strategic_needs,
                        means_of_verification: form.value.means_of_verification,
                        budget_code: form.value.budget_code,
                        total_cost_usd: form.value.total_cost_usd,
                        logistics_actuals: form.value.logistics_actuals,
                        mission_extras: form.value.mission_extras,
                    };
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
                            body: JSON.stringify(buildPayload()),
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(json.message || 'Save failed');
                        snackbar.value = { show: true, text: 'Draft saved', color: 'success' };
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
                        snackbar.value = { show: true, text: 'Could not update not-executed flag', color: 'error' };
                        return;
                    }
                    const json = await res.json();
                    form.value.not_executed = !!(json.data && json.data.not_executed);
                    snackbar.value = {
                        show: true,
                        text: next ? 'Marked not executed (reminders stop)' : 'Cleared not-executed',
                        color: 'success',
                    };
                }

                function triggerUpload() {
                    if (!cfg.canEdit || !fileInput.value) return;
                    fileInput.value.click();
                }

                async function onFileSelected(ev) {
                    const file = ev.target.files && ev.target.files[0];
                    ev.target.value = '';
                    if (!file || !cfg.canEdit) return;
                    if (file.size > 20 * 1024 * 1024) {
                        snackbar.value = { show: true, text: 'File exceeds 20 MB limit', color: 'error' };
                        return;
                    }
                    uploading.value = true;
                    try {
                        const fd = new FormData();
                        fd.append('file', file);
                        const res = await fetch(cfg.attachmentsUploadUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': cfg.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: fd,
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(json.message || 'Upload failed');
                        form.value.attachments = Array.isArray(json.data) ? json.data : [];
                        snackbar.value = { show: true, text: 'File uploaded', color: 'success' };
                    } catch (e) {
                        snackbar.value = { show: true, text: e.message || 'Upload failed', color: 'error' };
                    } finally {
                        uploading.value = false;
                    }
                }

                async function removeAttachment(att) {
                    if (!cfg.canEdit || !att || !att.id) return;
                    const res = await fetch(cfg.attachmentsDeleteUrl, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                        },
                        body: JSON.stringify({ id: att.id }),
                    });
                    if (!res.ok) {
                        snackbar.value = { show: true, text: 'Could not remove file', color: 'error' };
                        return;
                    }
                    const json = await res.json();
                    form.value.attachments = Array.isArray(json.data) ? json.data : [];
                }

                function formatSize(n) {
                    if (!n) return '';
                    if (n < 1024) return n + ' B';
                    if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
                    return (n / 1048576).toFixed(1) + ' MB';
                }

                onMounted(() => {
                    nextTick(() => setTimeout(bootRichEditors, 50));
                    if (!relatedItems.value.length || relatedItems.value.length === related.value.length) {
                        searchRelated('');
                    }
                    searchStaff('');
                });

                onBeforeUnmount(() => {
                    if (window.ApmQuillEditor && typeof window.ApmQuillEditor.destroyAll === 'function') {
                        window.ApmQuillEditor.destroyAll(mountEl);
                    }
                });

                return {
                    cfg, form, related, relatedItems, relatedSearch, relatedLoading,
                    staffItems, staffLoading, uploading, saving, snackbar, fileInput,
                    showParticipants, showBudget, quarterLabel, missionItems, locationOptions,
                    memberStateOptions, missionExtrasFields, missionExtrasTitle, durationLabel,
                    guidance, save, toggleNotExecuted, onRelatedMenu, queueStaffSearch,
                    triggerUpload, onFileSelected, removeAttachment, formatSize,
                };
            },
            template: `
              <v-app class="arf-vuetify-app">
                <v-container fluid class="pa-0">
                  <v-card class="arf-report-card mb-4">
                    <v-card-title class="d-flex justify-space-between align-center flex-wrap ga-2">
                      <div>
                        <div class="text-h6 text-high-emphasis">RBM Activity Reporting</div>
                        <div class="text-caption text-medium-emphasis">{{ form.document_number || 'Draft' }} · {{ form.overall_status || 'draft' }}</div>
                      </div>
                      <div class="d-flex ga-2 flex-wrap">
                        <v-btn v-if="cfg.canEdit" variant="tonal" color="warning" @click="toggleNotExecuted">
                          {{ form.not_executed ? 'Clear not executed' : 'Not executed' }}
                        </v-btn>
                        <v-btn v-if="cfg.canEdit" color="primary" :loading="saving" @click="save">Save draft</v-btn>
                      </div>
                    </v-card-title>
                    <v-card-text class="arf-form-body">
                      <v-alert type="info" variant="tonal" class="mb-4" density="comfortable">
                        Activity reports written now count for <strong>{{ quarterLabel || 'the open quarter' }}</strong>
                        <span v-if="cfg.quarterWindow"> ({{ cfg.quarterWindow }})</span>.
                      </v-alert>
                      <v-alert v-if="!cfg.canEdit" type="info" variant="tonal" class="mb-4">
                        View only — report is not editable in its current status
                        <span v-if="form.overall_status"> ({{ form.overall_status }})</span>.
                      </v-alert>
                      <v-alert v-if="form.not_executed" type="warning" variant="tonal" class="mb-4">
                        Marked not executed — reminders will not send.
                      </v-alert>
                      <v-alert v-if="cfg.isExternalFunded" type="info" variant="tonal" class="mb-4">
                        External / extramural funded — budget fields are not applicable.
                      </v-alert>

                      <!-- BACKGROUND -->
                      <div class="arf-section">
                        <div class="arf-section-head">Background (from the workplan)</div>
                        <div class="arf-section-body">
                          <p class="arf-hint">{{ guidance.background }}</p>
                          <div class="arf-bg-grid">
                            <div class="arf-bg-card">
                              <div class="arf-k">1. Tier 2 outcome result contributed to</div>
                              <div class="arf-v">{{ form.tier2 || '—' }}</div>
                            </div>
                            <div class="arf-bg-card">
                              <div class="arf-k">2. Output result emanating from the activity</div>
                              <div class="arf-v accent-purple">{{ form.tier4 || '—' }}</div>
                            </div>
                            <div class="arf-bg-card">
                              <div class="arf-k">3. Activity name / code</div>
                              <div class="arf-v accent-orange">{{ form.activity_name_code || '—' }}</div>
                            </div>
                            <div class="arf-bg-card">
                              <div class="arf-k">4. Broad activity (AMERT)</div>
                              <div class="arf-v accent-orange">{{ form.broad_activity_amert || '—' }}</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- RELATED -->
                      <div class="arf-section">
                        <div class="arf-section-head">Related activities</div>
                        <div class="arf-section-body">
                          <p class="arf-hint">{{ guidance.related }}</p>
                          <v-autocomplete
                            v-model="related"
                            v-model:search="relatedSearch"
                            :items="relatedItems"
                            item-title="title"
                            item-value="value"
                            :label="quarterLabel ? ('Related activities in ' + quarterLabel) : 'Search related activities'"
                            multiple chips closable-chips
                            :loading="relatedLoading"
                            :disabled="!cfg.canEdit"
                            return-object no-filter
                            @update:menu="onRelatedMenu"
                          />
                        </div>
                      </div>

                      <!-- EDITABLE BACKGROUND DETAILS -->
                      <div class="arf-section">
                        <div class="arf-section-head">Background details</div>
                        <div class="arf-section-body">
                          <label class="arf-field-label">6. Activity and session type</label>
                          <p class="arf-hint">{{ guidance.mission_type }}</p>
                          <v-select
                            v-model="form.mission_type"
                            :items="missionItems"
                            item-title="title"
                            item-value="value"
                            label="-- select --"
                            :disabled="!cfg.canEdit"
                            class="mb-4"
                          >
                            <template #item="{ props, item }">
                              <v-list-item v-bind="props" :subtitle="item.raw.hint" />
                            </template>
                          </v-select>

                          <label class="arf-field-label">7. Start date, end date, duration</label>
                          <p class="arf-hint">{{ guidance.dates }}</p>
                          <v-row dense class="mb-4">
                            <v-col cols="12" md="4">
                              <v-text-field v-model="form.start_date" type="date" label="Start date" :disabled="!cfg.canEdit" />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field v-model="form.end_date" type="date" label="End date" :disabled="!cfg.canEdit" />
                            </v-col>
                            <v-col cols="12" md="4">
                              <v-text-field :model-value="durationLabel || (form.duration_days || '')" label="Duration (days)" readonly disabled />
                            </v-col>
                          </v-row>

                          <label class="arf-field-label">8. Location &amp; venue</label>
                          <p class="arf-hint">{{ guidance.location }}</p>
                          <v-row dense class="mb-4">
                            <v-col cols="12" md="6">
                              <v-combobox
                                v-model="form.location"
                                :items="locationOptions"
                                label="Location"
                                :disabled="!cfg.canEdit"
                                clearable
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field
                                v-model="form.venue"
                                label="Venue"
                                placeholder="e.g. Africa CDC HQ, conference room 8"
                                :disabled="!cfg.canEdit"
                              />
                            </v-col>
                          </v-row>

                          <label class="arf-field-label">9. Lead organiser &amp; other team members</label>
                          <p class="arf-hint">{{ guidance.organisers }}</p>
                          <v-row dense class="mb-4">
                            <v-col cols="12" md="6">
                              <v-autocomplete
                                v-model="form.lead_organiser_staff_id"
                                :items="staffItems"
                                item-title="title"
                                item-value="value"
                                label="Lead organiser"
                                :loading="staffLoading"
                                :disabled="!cfg.canEdit"
                                clearable
                                @update:search="(q) => queueStaffSearch(q, 'lead')"
                              />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-autocomplete
                                v-model="form.other_organiser_staff_ids"
                                :items="staffItems"
                                item-title="title"
                                item-value="value"
                                label="Other team members"
                                multiple chips closable-chips
                                :loading="staffLoading"
                                :disabled="!cfg.canEdit"
                                @update:search="(q) => queueStaffSearch(q, 'other')"
                              />
                            </v-col>
                          </v-row>

                          <label class="arf-field-label">10. Activity objectives</label>
                          <p class="arf-hint">{{ guidance.objectives }}</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="objectives" data-apm-quill-toolbar="full" data-apm-quill-min-height="140"></textarea>
                          </div>
                        </div>
                      </div>

                      <!-- ACTIVITY SUMMARY -->
                      <div class="arf-section">
                        <div class="arf-section-head">Activity summary</div>
                        <div class="arf-section-body">
                          <template v-if="showParticipants">
                            <label class="arf-field-label">10A. Number of participants &amp; Member States covered</label>
                            <p class="arf-hint">{{ guidance.participants }}</p>
                            <v-row dense class="mb-4">
                              <v-col cols="12" md="3">
                                <v-text-field v-model.number="form.participants_count" type="number" min="0" label="Number of participants" :disabled="!cfg.canEdit" />
                              </v-col>
                              <v-col cols="12" md="9">
                                <v-autocomplete
                                  v-model="form.member_states"
                                  :items="memberStateOptions"
                                  label="Member States covered"
                                  multiple chips closable-chips
                                  :disabled="!cfg.canEdit"
                                />
                              </v-col>
                            </v-row>
                          </template>

                          <label class="arf-field-label">10B. Key strategic needs addressed</label>
                          <p class="arf-hint">{{ guidance.strategic_needs }}</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="key_strategic_needs" data-apm-quill-toolbar="full" data-apm-quill-min-height="120"></textarea>
                          </div>

                          <label class="arf-field-label">10C. Narrative of key achievements *</label>
                          <p class="arf-hint">{{ guidance.narrative }}</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="narrative_achievements" data-apm-quill-toolbar="full" data-apm-quill-min-height="160"></textarea>
                          </div>

                          <label class="arf-field-label">10D. Key challenges</label>
                          <p class="arf-hint">{{ guidance.challenges }}</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="key_challenges" data-apm-quill-toolbar="full" data-apm-quill-min-height="120"></textarea>
                          </div>

                          <label class="arf-field-label">10E. Recommendations for management</label>
                          <p class="arf-hint">{{ guidance.recommendations }}</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="recommendations" data-apm-quill-toolbar="full" data-apm-quill-min-height="120"></textarea>
                          </div>

                          <label class="arf-field-label">10F. Means of verification (notes)</label>
                          <p class="arf-hint">Optional notes describing evidence. Upload the files in the section below.</p>
                          <div class="arf-rich-block">
                            <textarea class="apm-rich-editor form-control" data-arf-field="means_of_verification" data-apm-quill-toolbar="full" data-apm-quill-min-height="100"></textarea>
                          </div>
                        </div>
                      </div>

                      <!-- VERIFICATION FILES -->
                      <div class="arf-section">
                        <div class="arf-section-head arf-purple">Verification files</div>
                        <div class="arf-section-body">
                          <div class="arf-upload-note">{{ guidance.verification }} Prefer PDF for layout preservation. Word, Excel and PowerPoint are accepted.</div>
                          <input
                            ref="fileInput"
                            type="file"
                            class="d-none"
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.doc,.docx,.xls,.xlsx,.ppt,.pptx,image/*,video/*"
                            @change="onFileSelected"
                          />
                          <v-btn
                            v-if="cfg.canEdit"
                            variant="text"
                            color="primary"
                            prepend-icon="mdi-plus"
                            :loading="uploading"
                            @click="triggerUpload"
                          >
                            Upload file (PDF, image, video, Office — max 20 MB)
                          </v-btn>
                          <v-list v-if="form.attachments && form.attachments.length" density="compact" class="mt-2">
                            <v-list-item v-for="att in form.attachments" :key="att.id || att.path">
                              <v-list-item-title>
                                <a v-if="att.url" :href="att.url" target="_blank" rel="noopener">{{ att.name || att.path }}</a>
                                <span v-else>{{ att.name || att.path }}</span>
                              </v-list-item-title>
                              <v-list-item-subtitle>{{ formatSize(att.size) }} {{ att.mime ? '· ' + att.mime : '' }}</v-list-item-subtitle>
                              <template #append>
                                <v-btn v-if="cfg.canEdit" icon="mdi-close" variant="text" size="small" @click="removeAttachment(att)" />
                              </template>
                            </v-list-item>
                          </v-list>
                          <div v-else class="text-caption text-medium-emphasis mt-2">No verification files uploaded yet.</div>
                        </div>
                      </div>

                      <!-- LOGISTICS -->
                      <div v-if="showBudget" class="arf-section">
                        <div class="arf-section-head">11. Activity logistics</div>
                        <div class="arf-section-body">
                          <p class="arf-hint">{{ guidance.logistics }}</p>
                          <v-row dense>
                            <v-col cols="12" md="6">
                              <v-text-field v-model="form.budget_code" label="Budget code (from the CBF)" :disabled="!cfg.canEdit" />
                            </v-col>
                            <v-col cols="12" md="6">
                              <v-text-field v-model="form.total_cost_usd" type="number" label="Total cost used (USD)" :disabled="!cfg.canEdit" />
                            </v-col>
                            <v-col cols="12">
                              <label class="arf-field-label">Logistics actuals / notes</label>
                              <div class="arf-rich-block">
                                <textarea class="apm-rich-editor form-control" data-arf-field="logistics_actuals" data-apm-quill-toolbar="full" data-apm-quill-min-height="100"></textarea>
                              </div>
                            </v-col>
                          </v-row>
                        </div>
                      </div>

                      <!-- MISSION EXTRAS -->
                      <div v-if="form.mission_type" class="arf-section">
                        <div class="arf-section-head">{{ missionExtrasTitle }}</div>
                        <div class="arf-section-body">
                          <p class="arf-hint">{{ guidance.extras }}</p>
                          <div v-for="field in missionExtrasFields" :key="field.key" class="mb-4">
                            <label class="arf-field-label">{{ field.label }}</label>
                            <p class="arf-hint">{{ field.hint }}</p>
                            <v-textarea
                              v-model="form.mission_extras[field.key]"
                              :rows="field.rows || 3"
                              auto-grow
                              :disabled="!cfg.canEdit"
                            />
                          </div>
                        </div>
                      </div>
                      <v-alert v-else type="info" variant="tonal" class="mb-4" density="comfortable">
                        Select an activity and session type above to load the additional specific questions for that type.
                      </v-alert>

                      <div class="arf-meta-row">
                        <div>
                          <div class="arf-k">Prepared by</div>
                          <div>{{ cfg.preparedBy || '—' }}</div>
                        </div>
                        <div>
                          <div class="arf-k">Approved by</div>
                          <div>—</div>
                        </div>
                        <div>
                          <div class="arf-k">Submission date</div>
                          <div>{{ form.submitted_at || '—' }}</div>
                        </div>
                      </div>

                      <div class="d-flex justify-end ga-2 mt-4 flex-wrap" v-if="cfg.canEdit">
                        <v-btn color="primary" :loading="saving" @click="save">Save draft</v-btn>
                      </div>
                    </v-card-text>
                  </v-card>
                  <v-snackbar v-model="snackbar.show" :color="snackbar.color">{{ snackbar.text }}</v-snackbar>
                </v-container>
              </v-app>
            `,
        });

        app.use(vuetify);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.mount(mountEl);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, boot);
    }
})();
