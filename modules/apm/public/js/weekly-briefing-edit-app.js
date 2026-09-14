/**
 * Weekly brief edit form — Vue 3 + Vuetify 3 + Quill
 */
(function () {
    'use strict';

    const MOUNT_ID = 'weekly-briefing-edit-app';

    function bootWeeklyBriefingEdit(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, onMounted, nextTick } = Vue;
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
            },
        });

        const app = createApp({
            setup() {
                const section2 = ref((cfg.section2 || []).map((row) => ({ ...row })));
                const btSeq = ref(1000);
                let quillEditors = [];

                const quillToolbar = [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];

                function setHiddenHtml(el, html) {
                    if (el) {
                        el.value = html || '';
                    }
                }

                function bindQuill(editorId, sourceEl, initialHtml) {
                    const host = document.getElementById(editorId);
                    if (!host || !sourceEl || host.__quill || typeof Quill === 'undefined') return;
                    setHiddenHtml(sourceEl, initialHtml || '');
                    const opts = cfg.formEditable
                        ? { theme: 'snow', modules: { toolbar: quillToolbar } }
                        : { theme: 'snow', modules: { toolbar: false } };
                    const q = new Quill('#' + editorId, opts);
                    const html = initialHtml || '';
                    if (html) {
                        q.root.innerHTML = html;
                    }
                    if (!cfg.formEditable) {
                        q.enable(false);
                    }
                    host.__quill = q;
                    quillEditors.push({ quill: q, hidden: sourceEl });
                }

                function initSection1Row(idx) {
                    const row = (cfg.section1 || [])[idx] || {};
                    bindQuill('wb-major-q-' + idx, document.getElementById('wb-major-h-' + idx), row.major_happening || '');
                    bindQuill('wb-desc-' + idx, document.getElementById('wb-desc-h-' + idx), row.description_key_actions || '');
                    bindQuill('wb-strat-' + idx, document.getElementById('wb-strat-h-' + idx), row.strategic_relevance || '');
                }

                function initBottleneckRow(uid) {
                    const row = section2.value.find((r) => r.uid === uid) || {};
                    bindQuill('wb-bt-issue-' + uid, document.getElementById('wb-bt-issue-h-' + uid), row.issue || '');
                    bindQuill('wb-bt-impact-' + uid, document.getElementById('wb-bt-impact-h-' + uid), row.impact_risk || '');
                    bindQuill('wb-bt-action-' + uid, document.getElementById('wb-bt-action-h-' + uid), row.required_action || '');
                }

                function waitForQuill(cb, attempt) {
                    const tries = attempt || 0;
                    if (typeof Quill !== 'undefined') {
                        cb();
                        return;
                    }
                    if (tries < 60) {
                        setTimeout(function () {
                            waitForQuill(cb, tries + 1);
                        }, 50);
                    }
                }

                function initAllEditors() {
                    quillEditors = [];
                    for (let i = 0; i < 3; i++) {
                        initSection1Row(i);
                    }
                    section2.value.forEach((row) => initBottleneckRow(row.uid));
                }

                function syncEditors() {
                    quillEditors.forEach((pair) => {
                        if (pair.hidden && pair.quill) {
                            pair.hidden.value = pair.quill.root.innerHTML;
                        }
                    });
                }

                function pruneEditors() {
                    const form = document.getElementById('weekly-briefing-form');
                    if (!form) return;
                    quillEditors = quillEditors.filter((p) => p.hidden && form.contains(p.hidden));
                }

                function addBottleneck() {
                    const uid = 'j' + (++btSeq.value);
                    section2.value.push({
                        uid,
                        issue: '',
                        impact_risk: '',
                        required_action: '',
                    });
                    nextTick(() => initBottleneckRow(uid));
                }

                function removeBottleneck(uid) {
                    if (section2.value.length <= 1) return;
                    section2.value = section2.value.filter((r) => r.uid !== uid);
                    nextTick(() => pruneEditors());
                }

                function onFormSubmit(e) {
                    syncEditors();
                    const submitter = e.submitter;
                    if (submitter && submitter.name === 'submit_final') {
                        if (!window.confirm(cfg.submitConfirm)) {
                            e.preventDefault();
                        }
                    }
                }

                const reviewDialog = ref(false);
                const reviewLoading = ref(false);
                const reviewSubmitting = ref(false);
                const reviewError = ref('');
                const reviewComments = ref(cfg.report?.director_comments || '');
                const reviewPayload = ref(null);

                const reviewSection1 = computed(() => {
                    const rows = reviewPayload.value?.section1;
                    if (Array.isArray(rows) && rows.length) return rows;
                    return (cfg.section1 || [])
                        .map((row, idx) => ({
                            n: idx + 1,
                            major_happening: row.major_happening || '',
                            description_key_actions: row.description_key_actions || '',
                            strategic_relevance: row.strategic_relevance || '',
                        }))
                        .filter((row) => {
                            const plain = [row.major_happening, row.description_key_actions, row.strategic_relevance]
                                .map((v) => String(v || '').replace(/<[^>]+>/g, '').trim())
                                .join('');
                            return plain !== '';
                        });
                });

                const reviewSection2 = computed(() => {
                    const rows = reviewPayload.value?.section2;
                    if (Array.isArray(rows) && rows.length) return rows;
                    return (section2.value || []).filter((row) => {
                        const plain = [row.issue, row.impact_risk, row.required_action]
                            .map((v) => String(v || '').replace(/<[^>]+>/g, '').trim())
                            .join('');
                        return plain !== '';
                    });
                });

                async function openDirectorReviewModal() {
                    reviewError.value = '';
                    reviewDialog.value = true;
                    reviewLoading.value = true;
                    reviewPayload.value = null;
                    reviewComments.value = cfg.report?.director_comments || '';
                    try {
                        if (cfg.directorReviewModalUrl) {
                            const res = await fetch(cfg.directorReviewModalUrl, {
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            });
                            const json = await res.json();
                            if (!res.ok) {
                                throw new Error(json.message || 'Could not load director review.');
                            }
                            reviewPayload.value = json.data || null;
                            if (reviewPayload.value?.director_comments != null) {
                                reviewComments.value = reviewPayload.value.director_comments;
                            }
                        }
                    } catch (err) {
                        reviewError.value = err && err.message ? err.message : 'Could not load director review.';
                    } finally {
                        reviewLoading.value = false;
                    }
                }

                async function submitDirectorReview() {
                    if (!cfg.canMarkDirectorReview || !cfg.directorReviewUrl) return;
                    reviewSubmitting.value = true;
                    reviewError.value = '';
                    try {
                        const body = new FormData();
                        body.append('_token', cfg.csrfToken);
                        body.append('director_comments', reviewComments.value || '');
                        const res = await fetch(cfg.directorReviewUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body,
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            throw new Error(json.message || 'Could not save director review.');
                        }
                        reviewDialog.value = false;
                        window.location.href = cfg.routes?.edit || window.location.pathname + window.location.search;
                    } catch (err) {
                        reviewError.value = err && err.message ? err.message : 'Could not save director review.';
                    } finally {
                        reviewSubmitting.value = false;
                    }
                }

                onMounted(() => {
                    nextTick(() => {
                        waitForQuill(() => {
                            initAllEditors();
                            const form = document.getElementById('weekly-briefing-form');
                            if (form && cfg.formEditable) {
                                form.addEventListener('submit', onFormSubmit);
                            }
                            if (cfg.openDirectorReviewModal && cfg.canMarkDirectorReview) {
                                openDirectorReviewModal();
                            }
                        });
                    });
                });

                return {
                    cfg,
                    section2,
                    addBottleneck,
                    removeBottleneck,
                    reviewDialog,
                    reviewLoading,
                    reviewSubmitting,
                    reviewError,
                    reviewComments,
                    reviewPayload,
                    reviewSection1,
                    reviewSection2,
                    openDirectorReviewModal,
                    submitDirectorReview,
                };
            },
            template: `
<v-app class="wb-edit-vuetify-app">
  <v-container fluid class="pa-0">
    <div class="d-flex flex-wrap gap-2 mb-4">
      <v-btn :href="cfg.routes.index" variant="outlined" prepend-icon="mdi-arrow-left" size="small">Back to hub</v-btn>
      <v-btn :href="cfg.routes.pdf" target="_blank" variant="tonal" color="primary" prepend-icon="mdi-file-pdf-box" size="small">PDF</v-btn>
    </div>

    <v-alert v-if="cfg.flash?.status" type="success" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.status }}</v-alert>
    <v-alert v-if="cfg.flash?.error" type="error" variant="tonal" density="compact" class="mb-3">{{ cfg.flash.error }}</v-alert>

    <v-alert v-if="cfg.filingAsDelegate" type="info" variant="tonal" class="mb-3">
      <strong>Filing as a delegate.</strong>
      You are completing this weekly brief on behalf of the configured division head. When you submit, the briefing is attributed to the main contributor and your name is shown as filing on their behalf.
    </v-alert>
    <v-alert v-else-if="cfg.filingAsAdminAssistant" type="info" variant="tonal" class="mb-3">
      <strong>Filing on behalf of the division head.</strong>
      You are the admin assistant for this division. When you submit, the briefing is attributed to the division head; your filing is recorded on the completion trail.
    </v-alert>

    <v-alert v-if="cfg.unlockOverrideActive && cfg.unlockUntil" type="warning" variant="tonal" class="mb-3">
      <strong>Administrative unlock is active.</strong>
      Editing is allowed until <strong>{{ cfg.unlockUntil }}</strong> ({{ cfg.timezone }}).
    </v-alert>

    <v-alert v-if="cfg.hubViewOnly" color="secondary" variant="tonal" class="mb-3">
      <strong>View only.</strong> You can read this briefing here; use <strong>PDF</strong> for a printable copy. Editing is limited to the assigned contributor or director when the deadline allows.
    </v-alert>

    <v-card class="mb-4" variant="outlined">
      <v-card-text class="d-flex flex-wrap align-center justify-space-between gap-3 py-3">
        <div>
          <div class="text-body-2">
            <v-icon icon="mdi-calendar-clock" size="small" class="me-1"></v-icon>
            <strong>Submission deadline</strong>
            {{ cfg.deadline.date }} at {{ cfg.deadline.time }}
          </div>
        </div>
        <v-chip :color="cfg.deadline.badge.color" variant="tonal" size="small">{{ cfg.deadline.badge.label }}</v-chip>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-text class="d-flex flex-wrap justify-space-between gap-3">
        <div>
          <div class="text-h6 font-weight-bold mb-2">{{ cfg.report.week_range }}</div>
          <div class="d-flex flex-wrap gap-2 mb-2">
            <v-chip v-if="cfg.report.directorate_name" color="info" variant="tonal" size="small">{{ cfg.report.directorate_name }}</v-chip>
            <v-chip color="secondary" variant="tonal" size="small">{{ cfg.report.unit_label }}</v-chip>
          </div>
          <div v-if="cfg.report.division_name" class="text-caption text-medium-emphasis">APM division: {{ cfg.report.division_name }}</div>
          <div v-if="cfg.report.submitted_by" class="text-caption text-medium-emphasis mt-2">
            Submitted by <strong>{{ cfg.report.submitted_by }}</strong>
            <span v-if="cfg.report.submitted_at"> · {{ cfg.report.submitted_at }}</span>
            <span v-if="cfg.report.filed_on_behalf_line"> · <strong>{{ cfg.report.filed_on_behalf_line }}</strong></span>
            <span v-else-if="cfg.report.filed_on_behalf_by"> · Filed by <strong>{{ cfg.report.filed_on_behalf_by }}</strong></span>
          </div>
        </div>
        <div class="text-end">
          <v-chip :color="cfg.report.status_color" variant="flat" class="mb-2">{{ cfg.report.status }}</v-chip>
          <div
            v-if="cfg.report.show_director_review_block"
            class="text-caption"
            :class="cfg.report.director_review_reviewed ? 'text-success' : 'text-medium-emphasis'"
          >
            {{ cfg.report.director_review_line }}<span v-if="cfg.report.director_label"> · {{ cfg.report.director_label }}</span>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="cfg.report.requires_director_review" class="mb-4" variant="outlined" color="primary">
      <v-card-title class="text-subtitle-1 font-weight-bold text-primary py-3">
        <v-icon icon="mdi-account-tie" class="me-2"></v-icon>Director review
      </v-card-title>
      <v-card-text>
        <p v-if="cfg.report.director_review_reviewed" class="mb-2 text-success font-weight-medium">
          Reviewed<span v-if="cfg.report.director_label"> · {{ cfg.report.director_label }}</span>
        </p>
        <p v-else class="mb-2 font-weight-medium">
          Yet to be reviewed<span v-if="cfg.report.director_label"> · {{ cfg.report.director_label }}</span>
        </p>
        <div v-if="cfg.report.director_comments" class="mb-3">
          <div class="text-caption text-medium-emphasis mb-1">Director comments</div>
          <div class="text-body-2" style="white-space: pre-wrap;">{{ cfg.report.director_comments }}</div>
        </div>
        <v-btn
          v-if="cfg.canMarkDirectorReview"
          color="success"
          prepend-icon="mdi-comment-check-outline"
          @click="openDirectorReviewModal"
        >Review &amp; comment</v-btn>
        <v-btn
          v-else-if="cfg.report.director_review_reviewed"
          variant="outlined"
          color="primary"
          class="ms-2"
          prepend-icon="mdi-eye-outline"
          @click="openDirectorReviewModal"
        >View review</v-btn>
      </v-card-text>
    </v-card>

    <v-dialog v-model="reviewDialog" max-width="960" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between flex-wrap gap-2">
          <span>
            <v-icon icon="mdi-account-tie" class="me-2"></v-icon>
            Director review
          </span>
          <v-btn icon="mdi-close" variant="text" @click="reviewDialog = false"></v-btn>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text style="max-height: 70vh;">
          <v-alert v-if="reviewError" type="error" variant="tonal" density="compact" class="mb-3">{{ reviewError }}</v-alert>
          <div v-if="reviewLoading" class="py-8 text-center text-medium-emphasis">Loading division report…</div>
          <template v-else>
            <div class="mb-4">
              <div class="text-h6 font-weight-bold">{{ (reviewPayload && reviewPayload.week_range) || cfg.report.week_range }}</div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <v-chip v-if="(reviewPayload && reviewPayload.directorate_name) || cfg.report.directorate_name" color="info" variant="tonal" size="small">
                  {{ (reviewPayload && reviewPayload.directorate_name) || cfg.report.directorate_name }}
                </v-chip>
                <v-chip color="secondary" variant="tonal" size="small">
                  {{ (reviewPayload && reviewPayload.unit_label) || cfg.report.unit_label }}
                </v-chip>
              </div>
              <div class="text-caption text-medium-emphasis mt-2">
                Review the division report below, then add comments and mark reviewed. Comments appear on this division’s PDF.
              </div>
            </div>

            <div class="mb-4">
              <div class="text-subtitle-2 font-weight-bold text-primary mb-2">Section 1 — Major happenings</div>
              <div v-if="!reviewSection1.length" class="text-medium-emphasis text-body-2 mb-3">No major happenings recorded.</div>
              <v-card v-for="row in reviewSection1" :key="'rs1-' + row.n" variant="outlined" class="mb-2">
                <v-card-text class="py-3">
                  <div class="text-caption text-medium-emphasis mb-1">#{{ row.n }} Major happening</div>
                  <div class="mb-2" v-html="row.major_happening || '—'"></div>
                  <div class="text-caption text-medium-emphasis mb-1">Description &amp; key actions</div>
                  <div class="mb-2" v-html="row.description_key_actions || '—'"></div>
                  <div class="text-caption text-medium-emphasis mb-1">Strategic relevance</div>
                  <div v-html="row.strategic_relevance || '—'"></div>
                </v-card-text>
              </v-card>
            </div>

            <div class="mb-4">
              <div class="text-subtitle-2 font-weight-bold mb-2">Section 2 — Bottlenecks</div>
              <div v-if="!reviewSection2.length" class="text-medium-emphasis text-body-2 mb-3">No bottlenecks recorded.</div>
              <v-card v-for="(row, idx) in reviewSection2" :key="'rs2-' + idx" variant="outlined" class="mb-2">
                <v-card-text class="py-3">
                  <div class="text-caption text-medium-emphasis mb-1">Issue</div>
                  <div class="mb-2" v-html="row.issue || '—'"></div>
                  <div class="text-caption text-medium-emphasis mb-1">Impact / risk</div>
                  <div class="mb-2" v-html="row.impact_risk || '—'"></div>
                  <div class="text-caption text-medium-emphasis mb-1">Required action</div>
                  <div v-html="row.required_action || '—'"></div>
                </v-card-text>
              </v-card>
            </div>

            <v-textarea
              v-model="reviewComments"
              label="Director comments on approval"
              placeholder="Optional comments for this division’s weekly brief (shown on the PDF)…"
              rows="4"
              auto-grow
              variant="outlined"
              :readonly="!(reviewPayload ? reviewPayload.can_mark : cfg.canMarkDirectorReview)"
              counter="5000"
              maxlength="5000"
            ></v-textarea>
          </template>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-btn
            v-if="(reviewPayload && reviewPayload.pdf_url) || cfg.routes.pdf"
            :href="(reviewPayload && reviewPayload.pdf_url) || cfg.routes.pdf"
            target="_blank"
            variant="text"
            prepend-icon="mdi-file-pdf-box"
          >Open PDF</v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="reviewDialog = false">Close</v-btn>
          <v-btn
            v-if="reviewPayload ? reviewPayload.can_mark : cfg.canMarkDirectorReview"
            color="success"
            prepend-icon="mdi-check-circle"
            :loading="reviewSubmitting"
            :disabled="reviewLoading"
            @click="submitDirectorReview"
          >Mark reviewed</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <form id="weekly-briefing-form" method="post" :action="cfg.updateUrl">
      <input type="hidden" name="_token" :value="cfg.csrfToken">
      <input type="hidden" name="_method" value="PUT">

      <fieldset class="wb-edit-fieldset" :disabled="!cfg.formEditable">
        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 font-weight-bold text-primary py-3 border-b">
            <v-icon icon="mdi-newspaper-variant-outline" class="me-2"></v-icon>
            Section 1 — Major happenings (max 3)
          </v-card-title>
          <v-card-text>
            <p class="text-caption text-medium-emphasis mb-4">
              Complete each row: <strong>Major happening</strong> (short title), <strong>Description &amp; key actions</strong>, and <strong>Strategic relevance to Africa CDC</strong>.
            </p>
            <div class="wb-edit-table-wrap">
              <table class="wb-edit-table" id="wb-major-happenings-table">
                <thead>
                  <tr>
                    <th class="text-center" style="width:3rem">#</th>
                    <th>Major happening</th>
                    <th>Description &amp; key actions</th>
                    <th>Strategic relevance to Africa CDC</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in cfg.section1" :key="'s1-' + idx">
                    <td class="text-center text-medium-emphasis font-weight-medium">{{ idx + 1 }}</td>
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-major-q-' + idx" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section1[' + idx + '][major_happening]'" :id="'wb-major-h-' + idx"></textarea>
                      </div>
                    </td>
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-desc-' + idx" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section1[' + idx + '][description_key_actions]'" :id="'wb-desc-h-' + idx"></textarea>
                      </div>
                    </td>
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-strat-' + idx" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section1[' + idx + '][strategic_relevance]'" :id="'wb-strat-h-' + idx"></textarea>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </v-card-text>
        </v-card>

        <v-card class="mb-4">
          <v-card-title class="text-subtitle-1 font-weight-bold py-3 border-b">
            <v-icon icon="mdi-alert-circle-outline" class="me-2"></v-icon>
            Section 2 — Key bottlenecks &amp; escalation
          </v-card-title>
          <v-card-text>
            <div class="wb-edit-table-wrap mb-3">
              <table class="wb-edit-table" id="bottleneck-table">
                <thead>
                  <tr>
                    <th style="width:28%">Issue</th>
                    <th style="width:22%">Impact / risk level</th>
                    <th style="width:40%">Required action / SMT guidance or escalation</th>
                    <th style="width:4rem"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in section2" :key="row.uid" class="bottleneck-row">
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-bt-issue-' + row.uid" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section2[' + idx + '][issue]'" :id="'wb-bt-issue-h-' + row.uid"></textarea>
                      </div>
                    </td>
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-bt-impact-' + row.uid" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section2[' + idx + '][impact_risk]'" :id="'wb-bt-impact-h-' + row.uid"></textarea>
                      </div>
                    </td>
                    <td>
                      <div class="wb-quill-wrap">
                        <div :id="'wb-bt-action-' + row.uid" class="wb-quill-editor"></div>
                        <textarea class="d-none wb-quill-source" :name="'section2[' + idx + '][required_action]'" :id="'wb-bt-action-h-' + row.uid"></textarea>
                      </div>
                    </td>
                    <td class="text-center">
                      <v-btn
                        v-if="cfg.formEditable && section2.length > 1"
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        color="error"
                        @click="removeBottleneck(row.uid)"
                      ></v-btn>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <v-btn
              v-if="cfg.formEditable"
              variant="outlined"
              color="primary"
              size="small"
              prepend-icon="mdi-plus"
              @click="addBottleneck"
            >Add more bottlenecks</v-btn>
          </v-card-text>
        </v-card>

        <div v-if="cfg.formEditable" class="d-flex flex-wrap gap-2 mb-6">
          <v-btn type="submit" color="primary" prepend-icon="mdi-content-save">{{ cfg.saveLabel }}</v-btn>
          <v-btn
            v-if="cfg.canContributorSubmit"
            type="submit"
            name="submit_final"
            value="1"
            color="success"
            prepend-icon="mdi-send"
          >Submit</v-btn>
        </div>
      </fieldset>
    </form>
  </v-container>
</v-app>
            `,
        });

        window.ApmVuetifyPage.register(MOUNT_ID, app);
        app.use(vuetify);
        app.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootWeeklyBriefingEdit);
    }
})();
