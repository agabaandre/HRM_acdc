/**
 * Weekly brief hub — Vue 3 + Vuetify 3
 */
(function () {
    'use strict';

    const MOUNT_ID = 'weekly-briefing-app';
    let appInstance = null;

    function navigateWeeklyBrief(cfg, params) {
        const url = new URL(cfg.routes.index, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                url.searchParams.set(key, String(value));
            }
        });
        const target = url.pathname + url.search;
        if (typeof Livewire !== 'undefined' && Livewire.navigate) {
            Livewire.navigate(target);
        } else {
            window.location.href = target;
        }
    }

    function bootWeeklyBriefing(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            appInstance = null;
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        appInstance = null;
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

        appInstance = createApp({
            setup() {
                const tab = ref(cfg.tab || 'this_week');
                const twStatus = ref(cfg.filters?.tw_status || '');
                const twSearch = ref(cfg.filters?.tw_search || '');
                const allYear = ref(String(cfg.filters?.year ?? cfg.defaults?.all_year ?? ''));
                const allWeek = ref(cfg.filters?.week != null ? String(cfg.filters.week) : '');
                const allStatus = ref(cfg.filters?.status || '');
                const allSearch = ref(cfg.filters?.search || '');

                const thisWeekHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56, align: 'center' },
                    { title: 'Division / reporting unit', key: 'label', sortable: false, minWidth: 200 },
                    { title: 'Directorate', key: 'directorate', sortable: false, minWidth: 160 },
                    { title: 'Status', key: 'status', sortable: false, minWidth: 140 },
                    { title: 'Actions', key: 'actions', sortable: false, width: 220, align: 'end' },
                ];

                const allReportsHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56, align: 'center' },
                    { title: 'Reporting week', key: 'reporting_week', sortable: false, minWidth: 150 },
                    { title: 'Reporting unit', key: 'unit_label', sortable: false, minWidth: 160 },
                    { title: 'Directorate', key: 'directorate', sortable: false, minWidth: 150 },
                    { title: 'Week start → end', key: 'week_range', sortable: false, minWidth: 140 },
                    { title: 'Status', key: 'status', sortable: false, minWidth: 140 },
                    { title: 'Actions', key: 'actions', sortable: false, width: 220, align: 'end' },
                ];

                function switchTab(value) {
                    if (value === 'this_week') {
                        navigateWeeklyBrief(cfg, {
                            tab: 'this_week',
                            tw_status: twStatus.value,
                            tw_search: twSearch.value,
                        });
                        return;
                    }
                    navigateWeeklyBrief(cfg, {
                        tab: 'all',
                        year: allYear.value,
                        week: allWeek.value,
                        status: allStatus.value,
                        search: allSearch.value,
                    });
                }

                function applyThisWeekFilters() {
                    navigateWeeklyBrief(cfg, {
                        tab: 'this_week',
                        tw_status: twStatus.value,
                        tw_search: twSearch.value,
                    });
                }

                function resetThisWeekFilters() {
                    navigateWeeklyBrief(cfg, { tab: 'this_week' });
                }

                function applyAllFilters() {
                    navigateWeeklyBrief(cfg, {
                        tab: 'all',
                        year: allYear.value,
                        week: allWeek.value,
                        status: allStatus.value,
                        search: allSearch.value,
                    });
                }

                function resetAllFilters() {
                    navigateWeeklyBrief(cfg, {
                        tab: 'all',
                        year: String(cfg.defaults?.all_year ?? ''),
                        week: String(cfg.defaults?.all_week ?? ''),
                    });
                }

                function buildThisWeekPage(page) {
                    navigateWeeklyBrief(cfg, {
                        tab: 'this_week',
                        tw_status: twStatus.value,
                        tw_search: twSearch.value,
                        tw_page: page,
                    });
                }

                function buildAllReportsPage(page) {
                    navigateWeeklyBrief(cfg, {
                        tab: 'all',
                        year: allYear.value,
                        week: allWeek.value,
                        status: allStatus.value,
                        search: allSearch.value,
                        page,
                    });
                }

                const reviewDialog = ref(false);
                const reviewLoading = ref(false);
                const reviewSubmitting = ref(false);
                const reviewError = ref('');
                const reviewFlash = ref('');
                const reviewComments = ref('');
                const reviewPayload = ref(null);
                const reviewSubmitUrl = ref('');
                const reviewRowRef = ref(null);

                async function openDirectorReview(action, item) {
                    if (!action || !action.modal_url) {
                        if (action?.url) window.location.href = action.url;
                        return;
                    }
                    reviewRowRef.value = item || null;
                    reviewSubmitUrl.value = action.submit_url || '';
                    reviewError.value = '';
                    reviewFlash.value = '';
                    reviewComments.value = '';
                    reviewPayload.value = null;
                    reviewDialog.value = true;
                    reviewLoading.value = true;
                    try {
                        const res = await fetch(action.modal_url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const json = await res.json();
                        if (!res.ok) {
                            throw new Error(json.message || 'Could not load director review.');
                        }
                        reviewPayload.value = json.data || null;
                        reviewComments.value = reviewPayload.value?.director_comments || '';
                        if (reviewPayload.value?.submit_url) {
                            reviewSubmitUrl.value = reviewPayload.value.submit_url;
                        }
                    } catch (err) {
                        reviewError.value = err && err.message ? err.message : 'Could not load director review.';
                    } finally {
                        reviewLoading.value = false;
                    }
                }

                function onHubAction(action, item) {
                    if (action && action.action === 'director_review') {
                        openDirectorReview(action, item);
                        return;
                    }
                    if (action?.url) {
                        if (action.target === '_blank') {
                            window.open(action.url, '_blank');
                        } else {
                            window.location.href = action.url;
                        }
                    }
                }

                async function submitDirectorReview() {
                    if (!reviewSubmitUrl.value || !reviewPayload.value?.can_mark) return;
                    reviewSubmitting.value = true;
                    reviewError.value = '';
                    try {
                        const body = new FormData();
                        body.append('_token', cfg.csrfToken || '');
                        body.append('director_comments', reviewComments.value || '');
                        const res = await fetch(reviewSubmitUrl.value, {
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
                        if (reviewRowRef.value) {
                            reviewRowRef.value.director_review_line = json.data?.director_review_line || 'Reviewed';
                            reviewRowRef.value.director_review_reviewed = true;
                            if (Array.isArray(reviewRowRef.value.actions)) {
                                reviewRowRef.value.actions = reviewRowRef.value.actions.filter((a) => a.action !== 'director_review');
                            }
                        }
                        reviewFlash.value = json.message || 'Recorded as reviewed by director.';
                        reviewPayload.value = {
                            ...reviewPayload.value,
                            can_mark: false,
                            director_review_reviewed: true,
                            director_comments: reviewComments.value || '',
                        };
                        setTimeout(() => {
                            reviewDialog.value = false;
                        }, 700);
                    } catch (err) {
                        reviewError.value = err && err.message ? err.message : 'Could not save director review.';
                    } finally {
                        reviewSubmitting.value = false;
                    }
                }

                return {
                    cfg,
                    tab,
                    twStatus,
                    twSearch,
                    allYear,
                    allWeek,
                    allStatus,
                    allSearch,
                    thisWeekHeaders,
                    allReportsHeaders,
                    switchTab,
                    applyThisWeekFilters,
                    resetThisWeekFilters,
                    applyAllFilters,
                    resetAllFilters,
                    buildThisWeekPage,
                    buildAllReportsPage,
                    reviewDialog,
                    reviewLoading,
                    reviewSubmitting,
                    reviewError,
                    reviewFlash,
                    reviewComments,
                    reviewPayload,
                    openDirectorReview,
                    onHubAction,
                    submitDirectorReview,
                };
            },
            template: `
<v-app class="wb-vuetify-app">
  <v-container fluid class="pa-0">
    <v-alert v-if="cfg.flash?.status" type="success" variant="tonal" density="compact" class="mb-4">{{ cfg.flash.status }}</v-alert>
    <v-alert v-if="cfg.flash?.error" type="error" variant="tonal" density="compact" class="mb-4">{{ cfg.flash.error }}</v-alert>

    <v-card class="mb-4" elevation="1">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-3 py-4">
        <div class="min-w-0">
          <div class="text-h6 font-weight-bold text-primary d-flex align-center">
            <v-icon icon="mdi-newspaper-variant-outline" class="me-2"></v-icon>
            Weekly brief
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">{{ cfg.hubSubtitle }}</div>
          <div class="text-caption text-medium-emphasis mt-2">
            <strong>Active reporting week:</strong> {{ cfg.filing.human_range }}
          </div>
          <div class="text-caption text-medium-emphasis">
            <v-icon icon="mdi-calendar-clock" size="x-small" class="me-1"></v-icon>
            <strong>Submission deadline</strong> {{ cfg.filing.deadline_date }} at {{ cfg.filing.deadline_time }}
          </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-end">
          <template v-if="cfg.headerActions.filingWeekReports.length === 1">
            <v-btn :href="cfg.headerActions.filingWeekReports[0].edit_url" color="primary" prepend-icon="mdi-pencil">Continue reporting week</v-btn>
            <v-btn :href="cfg.headerActions.filingWeekReports[0].pdf_url" target="_blank" variant="outlined" prepend-icon="mdi-file-pdf-box">PDF</v-btn>
          </template>
          <v-menu v-else-if="cfg.headerActions.filingWeekReports.length > 1">
            <template #activator="{ props }">
              <v-btn v-bind="props" color="primary" append-icon="mdi-menu-down">Continue reporting week</v-btn>
            </template>
            <v-list density="compact">
              <v-list-item v-for="item in cfg.headerActions.filingWeekReports" :key="item.id" :href="item.edit_url" :title="item.label"></v-list-item>
            </v-list>
          </v-menu>
          <v-btn
            v-for="unit in cfg.headerActions.startUnits"
            :key="unit.key"
            :href="unit.url"
            size="small"
            variant="outlined"
            color="primary"
          >Start {{ unit.label }}</v-btn>
          <template v-if="cfg.headerActions.canCompiledExports">
            <v-btn :href="cfg.headerActions.compiledPdfUrl" target="_blank" variant="outlined" color="primary" prepend-icon="mdi-file-document-multiple">Compiled PDF</v-btn>
            <v-btn :href="cfg.headerActions.completionSummaryUrl" target="_blank" variant="outlined" prepend-icon="mdi-clipboard-list-outline">Completion summary</v-btn>
          </template>
          <template v-if="cfg.headerActions.directorCombinedOptions.length === 1">
            <v-btn :href="cfg.headerActions.directorCombinedOptions[0].url" target="_blank" variant="outlined" color="info" prepend-icon="mdi-layers-triple">Director report — my directorate</v-btn>
          </template>
          <v-menu v-else-if="cfg.headerActions.directorCombinedOptions.length > 1">
            <template #activator="{ props }">
              <v-btn v-bind="props" variant="outlined" color="info" prepend-icon="mdi-layers-triple" append-icon="mdi-menu-down">Director report — my directorates</v-btn>
            </template>
            <v-list density="compact">
              <v-list-item
                v-for="opt in cfg.headerActions.directorCombinedOptions"
                :key="opt.directorate_id"
                :href="opt.url"
                target="_blank"
                :title="opt.label"
              ></v-list-item>
            </v-list>
          </v-menu>
        </div>
      </v-card-title>
    </v-card>

    <v-card elevation="1">
      <v-tabs :model-value="tab" @update:model-value="switchTab" color="primary" class="apm-vuetify-tabs px-2">
        <v-tab value="this_week">This reporting week</v-tab>
        <v-tab value="all">All reports</v-tab>
      </v-tabs>

      <v-window :model-value="tab">
        <v-window-item value="this_week">
          <v-card-text class="bg-grey-lighten-5 border-b py-3">
            <div class="text-caption text-medium-emphasis mb-3"><strong>Active reporting week:</strong> {{ cfg.filing.human_range }}</div>
            <v-row dense>
              <v-col cols="12" md="3">
                <v-select v-model="twStatus" :items="cfg.twStatusOptions" item-title="title" item-value="value" label="Status" hide-details></v-select>
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field v-model="twSearch" label="Division / unit" placeholder="Search by unit, contributor, directorate…" hide-details clearable prepend-inner-icon="mdi-magnify"></v-text-field>
              </v-col>
              <v-col cols="12" md="auto" class="d-flex gap-2 align-center">
                <v-btn color="primary" prepend-icon="mdi-filter" @click="applyThisWeekFilters">Apply</v-btn>
                <v-btn variant="outlined" @click="resetThisWeekFilters">Reset</v-btn>
              </v-col>
            </v-row>
          </v-card-text>

          <div v-if="cfg.configuredUnitCount === 0" class="pa-6 text-medium-emphasis">No reporting units are configured for your access.</div>
          <div v-else-if="!cfg.thisWeekRows.length" class="pa-6 text-medium-emphasis">No rows match the current filters.</div>
          <template v-else>
            <div class="px-4 py-2 text-caption text-medium-emphasis border-b">
              Showing <strong>{{ cfg.thisWeekPagination.from }}</strong>–<strong>{{ cfg.thisWeekPagination.to }}</strong>
              of <strong>{{ cfg.thisWeekPagination.total }}</strong> divisions / reporting units
            </div>
            <v-data-table
              :headers="thisWeekHeaders"
              :items="cfg.thisWeekRows"
              density="comfortable"
              class="wb-hub-table"
              hide-default-footer
              :items-per-page="-1"
            >
              <template #item.label="{ item }">
                <div class="font-weight-medium">{{ item.label }}</div>
                <div v-if="item.staff_name" class="text-caption text-medium-emphasis">{{ item.staff_name }}</div>
              </template>
              <template #item.directorate="{ item }">
                <div v-if="item.directorate_name" class="text-body-2 font-weight-medium">{{ item.directorate_name }}</div>
                <div v-if="item.director_name" class="text-caption text-medium-emphasis">{{ item.director_name }}</div>
              </template>
              <template #item.status="{ item }">
                <v-chip size="small" :color="item.status_color" variant="tonal">{{ item.status }}</v-chip>
                <div
                  v-if="item.director_review_line"
                  class="text-caption mt-1"
                  :class="item.director_review_reviewed ? 'text-success' : 'text-medium-emphasis'"
                >{{ item.director_review_line }}</div>
              </template>
              <template #item.actions="{ item }">
                <div class="d-flex flex-wrap gap-1 justify-end">
                  <v-btn
                    v-for="(action, ai) in item.actions"
                    :key="ai"
                    size="small"
                    :variant="action.variant"
                    :color="action.color"
                    @click="onHubAction(action, item)"
                  >{{ action.label }}</v-btn>
                </div>
              </template>
            </v-data-table>
            <div v-if="cfg.thisWeekPagination.last_page > 1" class="d-flex flex-wrap align-center justify-space-between gap-2 px-4 py-3 border-t">
              <span class="text-caption text-medium-emphasis">Page {{ cfg.thisWeekPagination.current_page }} of {{ cfg.thisWeekPagination.last_page }}</span>
              <v-pagination
                :model-value="cfg.thisWeekPagination.current_page"
                :length="cfg.thisWeekPagination.last_page"
                density="compact"
                total-visible="7"
                @update:model-value="buildThisWeekPage"
              ></v-pagination>
            </div>
          </template>
        </v-window-item>

        <v-window-item value="all">
          <v-card-text v-if="cfg.filterWeekRangeLabel" class="border-b py-2">
            <strong>Showing:</strong> {{ cfg.filterWeekRangeLabel }}
          </v-card-text>
          <v-card-text class="bg-grey-lighten-5 border-b py-3">
            <v-row dense>
              <v-col cols="6" md="2">
                <v-select v-model="allYear" :items="cfg.yearOptions" item-title="title" item-value="value" label="Year" hide-details></v-select>
              </v-col>
              <v-col cols="6" md="4">
                <v-select v-model="allWeek" :items="cfg.weekOptions" item-title="title" item-value="value" label="Week" hide-details></v-select>
              </v-col>
              <v-col cols="6" md="2">
                <v-select v-model="allStatus" :items="cfg.allStatusOptions" item-title="title" item-value="value" label="Status" hide-details></v-select>
              </v-col>
              <v-col cols="12" md="3">
                <v-text-field v-model="allSearch" label="Reporting unit" placeholder="Search by unit, contributor, directorate…" hide-details clearable prepend-inner-icon="mdi-magnify"></v-text-field>
              </v-col>
              <v-col cols="12" md="auto" class="d-flex gap-2 align-center">
                <v-btn color="primary" prepend-icon="mdi-filter" @click="applyAllFilters">Apply</v-btn>
                <v-btn variant="outlined" @click="resetAllFilters">Reset</v-btn>
              </v-col>
            </v-row>
          </v-card-text>

          <v-data-table
            :headers="allReportsHeaders"
            :items="cfg.allReportRows"
            density="comfortable"
            class="wb-hub-table"
            hide-default-footer
            :items-per-page="-1"
          >
            <template #item.directorate="{ item }">
              <div v-if="item.directorate_name" class="text-body-2 font-weight-medium">{{ item.directorate_name }}</div>
              <div v-if="item.director_name" class="text-caption text-medium-emphasis">{{ item.director_name }}</div>
            </template>
            <template #item.status="{ item }">
              <v-chip size="small" :color="item.status_color" variant="tonal">{{ item.status }}</v-chip>
              <div
                v-if="item.director_review_line"
                class="text-caption mt-1"
                :class="item.director_review_reviewed ? 'text-success' : 'text-medium-emphasis'"
              >{{ item.director_review_line }}</div>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex flex-wrap gap-1 justify-end">
                <v-btn
                  v-for="(action, ai) in item.actions"
                  :key="ai"
                  size="small"
                  :variant="action.variant"
                  :color="action.color"
                  @click="onHubAction(action, item)"
                >{{ action.label }}</v-btn>
              </div>
            </template>
            <template #no-data>
              <div class="text-center text-medium-emphasis py-8">No reports match the current filters.</div>
            </template>
          </v-data-table>
          <div v-if="cfg.allReportsPagination && cfg.allReportsPagination.last_page > 1" class="d-flex flex-wrap align-center justify-space-between gap-2 px-4 py-3 border-t">
            <span class="text-caption text-medium-emphasis">Page {{ cfg.allReportsPagination.current_page }} of {{ cfg.allReportsPagination.last_page }}</span>
            <v-pagination
              :model-value="cfg.allReportsPagination.current_page"
              :length="cfg.allReportsPagination.last_page"
              density="compact"
              total-visible="7"
              @update:model-value="buildAllReportsPage"
            ></v-pagination>
          </div>
        </v-window-item>
      </v-window>
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
          <v-alert v-if="reviewFlash" type="success" variant="tonal" density="compact" class="mb-3">{{ reviewFlash }}</v-alert>
          <v-alert v-if="reviewError" type="error" variant="tonal" density="compact" class="mb-3">{{ reviewError }}</v-alert>
          <div v-if="reviewLoading" class="py-8 text-center text-medium-emphasis">Loading division report…</div>
          <template v-else-if="reviewPayload">
            <div class="mb-4">
              <div class="text-h6 font-weight-bold">{{ reviewPayload.week_range }}</div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                <v-chip v-if="reviewPayload.directorate_name" color="info" variant="tonal" size="small">{{ reviewPayload.directorate_name }}</v-chip>
                <v-chip color="secondary" variant="tonal" size="small">{{ reviewPayload.unit_label }}</v-chip>
              </div>
              <div v-if="reviewPayload.submitted_by" class="text-caption text-medium-emphasis mt-2">
                Submitted by <strong>{{ reviewPayload.submitted_by }}</strong>
                <span v-if="reviewPayload.submitted_at"> · {{ reviewPayload.submitted_at }}</span>
              </div>
              <div class="text-caption text-medium-emphasis mt-2">
                Review the division report below, then add comments and mark reviewed. Comments appear on this division’s PDF.
              </div>
            </div>

            <div class="mb-4">
              <div class="text-subtitle-2 font-weight-bold text-primary mb-2">Section 1 — Major happenings</div>
              <div v-if="!(reviewPayload.section1 || []).length" class="text-medium-emphasis text-body-2 mb-3">No major happenings recorded.</div>
              <v-card v-for="row in (reviewPayload.section1 || [])" :key="'hub-s1-' + row.n" variant="outlined" class="mb-2">
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
              <div v-if="!(reviewPayload.section2 || []).length" class="text-medium-emphasis text-body-2 mb-3">No bottlenecks recorded.</div>
              <v-card v-for="(row, idx) in (reviewPayload.section2 || [])" :key="'hub-s2-' + idx" variant="outlined" class="mb-2">
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
              :readonly="!reviewPayload.can_mark"
              counter="5000"
              maxlength="5000"
            ></v-textarea>
          </template>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-btn
            v-if="reviewPayload && reviewPayload.pdf_url"
            :href="reviewPayload.pdf_url"
            target="_blank"
            variant="text"
            prepend-icon="mdi-file-pdf-box"
          >Open PDF</v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="reviewDialog = false">Close</v-btn>
          <v-btn
            v-if="reviewPayload && reviewPayload.can_mark"
            color="success"
            prepend-icon="mdi-check-circle"
            :loading="reviewSubmitting"
            :disabled="reviewLoading"
            @click="submitDirectorReview"
          >Mark reviewed</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</v-app>
            `,
        });

        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
        appInstance.use(vuetify);
        appInstance.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootWeeklyBriefing);
    }
})();
