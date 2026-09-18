<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  fetchRisks,
  type RiskListMeta,
  type RiskListRow,
  type RiskLookups,
} from '@/lib/riskApi'
import { api } from '@/lib/api'
import { getStoredToken } from '@/lib/api'
import { themeDisplayName } from '@/lib/riskLabels'
import { downloadClientExcel } from '@/lib/clientTableExport'
import { useRiskLookups } from '@/composables/useRiskLookups'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'
import RiskApprovalTrail, { type RiskApprovalStep } from '@/components/risks/RiskApprovalTrail.vue'

const router = useRouter()
const locale = useLocaleStore()
const auth = useAuthStore()
const { loadLookups } = useRiskLookups()

const rows = ref<RiskListRow[]>([])
const meta = ref<RiskListMeta | null>(null)
const lookups = ref<RiskLookups | null>(null)
const loading = ref(true)
const exporting = ref(false)
const error = ref<string | null>(null)
const expandedRiskId = ref<number | null>(null)
const trailByRisk = ref<Record<number, RiskApprovalStep[]>>({})
const trailLoadingId = ref<number | null>(null)
const approvingId = ref<number | null>(null)

const ioModalOpen = ref(false)
const ioLoading = ref(false)
const ioSaving = ref(false)
const ioError = ref<string | null>(null)
const ioRisk = ref<RiskListRow | null>(null)
const ioForm = ref({
  year: 2026,
  quarter: 2,
  timeline: '',
  action_update: '',
  oio_verification_notes: '',
})

const myStaffId = computed(() => Number(auth.me?.profile?.staff_id || 0))

/** Current register baseline is treated as Q2 2026. */
const year = ref<number | null>(2026)
const quarter = ref<number | null>(2)
const divisionId = ref<number | null>(null)
const directorateId = ref<number | null>(null)
const themeId = ref<number | null>(null)
const divisionPage = ref(1)
const showAllRisks = ref(false)

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y + 1, y, y - 1, y - 2, y - 3].map((n) => ({ label: String(n), value: n }))
})

const quarterOptions = computed(() => [
  { label: locale.t('rr.quarter_q1', 'Q1'), value: 1 },
  { label: locale.t('rr.quarter_q2', 'Q2'), value: 2 },
  { label: locale.t('rr.quarter_q3', 'Q3'), value: 3 },
  { label: locale.t('rr.quarter_q4', 'Q4'), value: 4 },
])

const divisionOptions = computed(() => {
  const list = meta.value?.divisions || []
  return [
    { label: locale.t('rr.all_divisions', 'All divisions'), value: null as number | null },
    ...list
      .filter((d) => d.division_id != null)
      .map((d) => ({ label: d.division_name, value: d.division_id as number })),
  ]
})

const directorateOptions = computed(() => {
  const list = meta.value?.directorates || []
  return [
    { label: locale.t('rr.all_directorates', 'All directorates'), value: null as number | null },
    ...list.map((d) => ({ label: d.directorate_name, value: d.directorate_id })),
  ]
})

const themeOptions = computed(() => {
  const list = lookups.value?.enterprise_themes || []
  return [
    { label: locale.t('rr.all_themes', 'All themes'), value: null as number | null },
    ...list.map((t) => ({ label: themeDisplayName(t.name), value: t.id })),
  ]
})

const periodBadge = computed(() => {
  if (year.value != null && quarter.value != null) return `Q${quarter.value} ${year.value}`
  return locale.t('rr.all_periods', 'All periods')
})

const registerSubParts = computed(() => {
  const raw = locale.t(
    'rr.register_sub',
    'Enterprise risks by division · {period} · click a risk for its profile; use IO review for quarterly OIO notes',
  )
  const parts = raw.split('{period}')
  return { before: parts[0] ?? '', after: parts[1] ?? '' }
})

function scoreKey(label: string | null | undefined, score: number | null | undefined): string {
  const dash = locale.t('rr.none_dash', '—')
  if (score == null && !label) return dash
  if (label && score != null) return `${label}(${score})`
  if (score != null) return locale.t('rr.score_paren', 'Score({score})', { score })
  return label || dash
}

function ratingCell(score: number | null | undefined, rating: string | null | undefined): string {
  const dash = locale.t('rr.none_dash', '—')
  if (score == null && !rating) return dash
  if (score != null && rating) return `${score} ${rating}`
  if (score != null) return String(score)
  return rating || dash
}

function ratingStyle(fill?: string | null, text?: string | null): Record<string, string> | undefined {
  if (!fill) return undefined
  return {
    backgroundColor: fill,
    color: text || '#0F172A',
    fontWeight: '600',
    textAlign: 'center',
  }
}

function openProfile(id: number) {
  void router.push({ name: 'risk-show', params: { id } })
}

function openEdit(id: number, ev: Event) {
  ev.stopPropagation()
  void router.push({ name: 'risk-edit', params: { id } })
}

function colCount(): number {
  return showAllRisks.value ? 10 : 9
}

function canApproveStep(steps: RiskApprovalStep[]): number | null {
  if (myStaffId.value < 1) return null
  const current = steps.find((s) => s.is_current && s.status === 'pending')
  if (!current) return null
  if (Number(current.assignee_staff_id) !== myStaffId.value) return null
  return current.id
}

async function loadTrail(riskId: number, force = false) {
  if (!force && trailByRisk.value[riskId]) return
  trailLoadingId.value = riskId
  try {
    const { data } = await api.get<{ data: RiskApprovalStep[] }>(`/api/v1/risks/${riskId}/approvals`)
    trailByRisk.value = { ...trailByRisk.value, [riskId]: data.data || [] }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_trail_error', 'Could not load approval trail'))
    trailByRisk.value = { ...trailByRisk.value, [riskId]: [] }
  } finally {
    trailLoadingId.value = null
  }
}

async function toggleTrail(id: number, ev?: Event) {
  ev?.stopPropagation()
  if (expandedRiskId.value === id) {
    expandedRiskId.value = null
    return
  }
  expandedRiskId.value = id
  await loadTrail(id)
}

async function openIoReview(row: RiskListRow, ev?: Event) {
  ev?.stopPropagation()
  ioRisk.value = row
  ioError.value = null
  ioForm.value = {
    year: year.value ?? 2026,
    quarter: quarter.value ?? 2,
    timeline: '',
    action_update: '',
    oio_verification_notes: '',
  }
  ioModalOpen.value = true
  ioLoading.value = true
  try {
    const [detail, trends] = await Promise.all([
      api.get<{
        data: {
          timeline?: string | null
          action_update?: string | null
          oio_verification_notes?: string | null
        }
      }>(`/api/v1/risks/${row.id}`),
      api.get<{
        quarters: Array<{
          year: number
          quarter: number
          timeline?: string | null
          action_update?: string | null
          oio_verification_notes?: string | null
        }>
      }>(`/api/v1/risks/${row.id}/trends`),
    ])
    const y = ioForm.value.year
    const q = ioForm.value.quarter
    const saved = (trends.data.quarters || []).find(
      (r) => Number(r.year) === y && Number(r.quarter) === q,
    )
    const risk = detail.data.data
    ioForm.value = {
      year: y,
      quarter: q,
      timeline: (saved?.timeline ?? risk.timeline ?? '') || '',
      action_update: (saved?.action_update ?? risk.action_update ?? '') || '',
      oio_verification_notes:
        (saved?.oio_verification_notes ?? risk.oio_verification_notes ?? '') || '',
    }
  } catch (e) {
    ioError.value = apiErrorMessage(e, locale.t('rr.load_io_review_error', 'Could not load IO review'))
  } finally {
    ioLoading.value = false
  }
}

async function reloadIoPeriod() {
  if (!ioRisk.value) return
  ioLoading.value = true
  ioError.value = null
  try {
    const { data } = await api.get<{
      quarters: Array<{
        year: number
        quarter: number
        timeline?: string | null
        action_update?: string | null
        oio_verification_notes?: string | null
      }>
    }>(`/api/v1/risks/${ioRisk.value.id}/trends`)
    const saved = (data.quarters || []).find(
      (r) => Number(r.year) === ioForm.value.year && Number(r.quarter) === ioForm.value.quarter,
    )
    if (saved) {
      ioForm.value.timeline = saved.timeline || ''
      ioForm.value.action_update = saved.action_update || ''
      ioForm.value.oio_verification_notes = saved.oio_verification_notes || ''
    } else {
      ioForm.value.timeline = ''
      ioForm.value.action_update = ''
      ioForm.value.oio_verification_notes = ''
    }
  } catch (e) {
    ioError.value = apiErrorMessage(e, locale.t('rr.load_io_review_error', 'Could not load IO review'))
  } finally {
    ioLoading.value = false
  }
}

async function saveIoReview() {
  if (!ioRisk.value) return
  ioSaving.value = true
  ioError.value = null
  try {
    await api.post(`/api/v1/risks/${ioRisk.value.id}/reviews`, {
      year: ioForm.value.year,
      quarter: ioForm.value.quarter,
      timeline: ioForm.value.timeline,
      action_update: ioForm.value.action_update,
      oio_verification_notes: ioForm.value.oio_verification_notes,
    })
    ioModalOpen.value = false
  } catch (e) {
    ioError.value = apiErrorMessage(e, locale.t('rr.io_review_save_failed', 'Could not save IO review'))
  } finally {
    ioSaving.value = false
  }
}

function closeIoReview() {
  ioModalOpen.value = false
  ioRisk.value = null
  ioError.value = null
}

async function approveFromTrail(approvalId: number) {
  approvingId.value = approvalId
  try {
    await api.post(`/api/v1/approvals/${approvalId}/approve`)
    if (expandedRiskId.value) {
      await loadTrail(expandedRiskId.value, true)
      await load()
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.approve_failed', 'Approve failed'))
  } finally {
    approvingId.value = null
  }
}

function prevDivision() {
  if (!meta.value || meta.value.division_page <= 1) return
  divisionPage.value = meta.value.division_page - 1
  void load()
}

function nextDivision() {
  if (!meta.value || meta.value.division_page >= meta.value.division_pages) return
  divisionPage.value = meta.value.division_page + 1
  void load()
}

async function load() {
  if (!getStoredToken()) {
    error.value = locale.t('rr.signin_required', 'Sign in via Staff Portal CBP Modules → Risk Register.')
    loading.value = false
    return
  }
  loading.value = true
  error.value = null
  try {
    const [list, lookupData] = await Promise.all([
      fetchRisks({
        year: year.value,
        quarter: quarter.value,
        division_id: divisionId.value,
        directorate_id: directorateId.value,
        enterprise_theme_id: themeId.value,
        division_page: divisionPage.value,
        show_all: showAllRisks.value,
      }),
      lookups.value ? Promise.resolve(lookups.value) : loadLookups(),
    ])
    rows.value = list.data
    meta.value = list.meta
    lookups.value = lookupData
    if (list.meta.division_page !== divisionPage.value) {
      divisionPage.value = list.meta.division_page
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_risks_error', 'Could not load risks'))
  } finally {
    loading.value = false
  }
}

function resetPageAndLoad() {
  divisionPage.value = 1
  void load()
}

function onShowAllToggle() {
  divisionPage.value = 1
  void load()
}

async function exportExcel() {
  exporting.value = true
  error.value = null
  try {
    let exportRows = rows.value
    if (!showAllRisks.value) {
      const list = await fetchRisks({
        year: year.value,
        quarter: quarter.value,
        division_id: divisionId.value,
        directorate_id: directorateId.value,
        enterprise_theme_id: themeId.value,
        show_all: true,
      })
      exportRows = list.data
    }
    const headers = [
      locale.t('rr.col_id', 'ID'),
      locale.t('rr.col_division', 'Division'),
      locale.t('rr.col_risk', 'Risk'),
      locale.t('rr.col_theme', 'Enterprise Risk Theme'),
      locale.t('rr.col_type', 'Risk Type'),
      locale.t('rr.col_likelihood', 'Likelihood (L)'),
      locale.t('rr.col_impact', 'Impact (I)'),
      locale.t('rr.col_inherent', 'Inherent Risk Score'),
      locale.t('rr.col_residual', 'Residual Risk Score'),
      locale.t('rr.col_movement', 'Movement'),
    ]
    const data = exportRows.map((r) => [
      r.counter ?? r.id,
      r.division_name || '',
      r.name,
      themeDisplayName(r.enterprise_theme_name),
      r.risk_type_name || '',
      scoreKey(r.likelihood_label, r.likelihood_score),
      scoreKey(r.impact_label, r.impact_score),
      ratingCell(r.inherent_score, r.inherent_rating),
      ratingCell(r.residual_score, r.residual_rating),
      r.risk_movement || '',
    ])
    const period =
      year.value != null && quarter.value != null ? `Q${quarter.value}-${year.value}` : 'all-periods'
    downloadClientExcel(`risk-register-${period}`, headers, data, 'Risk Register')
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.export_error', 'Could not export risks'))
  } finally {
    exporting.value = false
  }
}

watch([year, quarter], () => {
  if ((year.value == null) !== (quarter.value == null)) return
  resetPageAndLoad()
})

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.register_title', 'Risk Register') }}</h1>
        <p class="rr-page__sub">
          {{ registerSubParts.before }}
          <span class="rr-period-chip">{{ periodBadge }}</span>
          {{ registerSubParts.after }}
        </p>
      </div>
      <div class="rr-page__actions">
        <button
          type="button"
          class="rr-btn"
          :disabled="exporting || loading"
          @click="exportExcel"
        >
          {{ exporting ? locale.t('rr.exporting', 'Exporting…') : locale.t('rr.export_excel', 'Export to Excel') }}
        </button>
        <button type="button" class="rr-btn rr-btn--primary" @click="router.push({ name: 'risk-new' })">
          {{ locale.t('rr.new_risk', 'New risk') }}
        </button>
      </div>
    </header>

    <section class="rr-card rr-filters">
      <div class="rr-filters__grid">
        <UFormField :label="locale.t('rr.filter_year', 'Year')">
          <USelect v-model="year" :items="yearOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_quarter', 'Quarter')">
          <USelect v-model="quarter" :items="quarterOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_directorate', 'Directorate')">
          <USelectMenu
            v-model="directorateId"
            :items="directorateOptions"
            searchable
            hide-details
            @update:model-value="resetPageAndLoad"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_division', 'Division')">
          <USelectMenu
            v-model="divisionId"
            :items="divisionOptions"
            searchable
            hide-details
            @update:model-value="resetPageAndLoad"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_theme', 'Enterprise Risk Theme')">
          <USelectMenu
            v-model="themeId"
            :items="themeOptions"
            searchable
            hide-details
            @update:model-value="resetPageAndLoad"
          />
        </UFormField>
        <label class="rr-show-all">
          <input v-model="showAllRisks" type="checkbox" @change="onShowAllToggle" />
          <span>{{ locale.t('rr.show_all_risks', 'Show all risks in the register') }}</span>
        </label>
      </div>
      <p v-if="year != null && quarter == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_quarter', 'Select a quarter to filter by review period.') }}
      </p>
      <p v-else-if="quarter != null && year == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_year', 'Select a year to filter by review period.') }}
      </p>
    </section>

    <RrSkeleton v-if="loading" variant="table" :rows="8" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else>
      <div v-if="!showAllRisks" class="rr-pager">
        <button type="button" class="rr-btn" :disabled="!meta || meta.division_page <= 1" @click="prevDivision">
          {{ locale.t('rr.prev_division', '← Prev division') }}
        </button>
        <div class="rr-pager__status">
          <strong>{{ meta?.division_name || locale.t('rr.none_dash', '—') }}</strong>
          <span class="rr-muted">
            {{
              locale.t('rr.division_of', 'Division {page} of {pages} · {rows} risks', {
                page: meta?.division_page || 0,
                pages: meta?.division_pages || 0,
                rows: meta?.total_rows || 0,
              })
            }}
          </span>
        </div>
        <button
          type="button"
          class="rr-btn"
          :disabled="!meta || meta.division_page >= meta.division_pages"
          @click="nextDivision"
        >
          {{ locale.t('rr.next_division', 'Next division →') }}
        </button>
      </div>
      <p v-else class="rr-all-status rr-muted">
        {{
          locale.t('rr.showing_all', 'Showing all {rows} risks across {divisions} divisions', {
            rows: meta?.total_rows || 0,
            divisions: meta?.total_divisions || 0,
          })
        }}
      </p>

      <div class="rr-card rr-table-wrap">
        <table class="rr-table rr-table--register">
          <thead>
            <tr>
              <th>{{ locale.t('rr.col_id', 'ID') }}</th>
              <th v-if="showAllRisks">{{ locale.t('rr.col_division', 'Division') }}</th>
              <th>{{ locale.t('rr.col_risk', 'Risk') }}</th>
              <th>{{ locale.t('rr.col_theme', 'Enterprise Risk Theme') }}</th>
              <th>{{ locale.t('rr.col_type', 'Risk Type') }}</th>
              <th>{{ locale.t('rr.col_likelihood', 'Likelihood (L)') }}</th>
              <th>{{ locale.t('rr.col_impact', 'Impact (I)') }}</th>
              <th>{{ locale.t('rr.col_inherent', 'Inherent Risk Score') }}</th>
              <th>{{ locale.t('rr.col_residual', 'Residual Risk Score') }}</th>
              <th>{{ locale.t('rr.col_movement', 'Movement') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="r in rows" :key="r.id">
              <tr class="rr-table__row" @click="openProfile(r.id)">
                <td>{{ r.counter ?? locale.t('rr.none_dash', '—') }}</td>
                <td v-if="showAllRisks">{{ r.division_name || locale.t('rr.none_dash', '—') }}</td>
                <td class="rr-table__name">
                  <div class="rr-risk-name">
                    <span class="rr-risk-name__text" :title="r.name">{{ r.name }}</span>
                    <button
                      type="button"
                      class="rr-risk-name__edit"
                      :title="locale.t('rr.edit_focal_title', 'Division focal person: edit risk details')"
                      @click="openEdit(r.id, $event)"
                    >
                      <span class="rr-risk-name__icon" aria-hidden="true">✎</span>
                      <span>{{ locale.t('rr.edit', 'Edit') }}</span>
                    </button>
                    <button
                      type="button"
                      class="rr-risk-name__trail"
                      :title="locale.t('rr.io_review_title', 'Quarterly IO review notes')"
                      @click="openIoReview(r, $event)"
                    >
                      {{ locale.t('rr.io_review', 'IO review') }}
                    </button>
                    <button
                      type="button"
                      class="rr-risk-name__trail"
                      :class="{ 'is-open': expandedRiskId === r.id }"
                      :title="locale.t('rr.toggle_io_trail', 'Show IO / approval trail')"
                      @click="toggleTrail(r.id, $event)"
                    >
                      {{
                        expandedRiskId === r.id
                          ? locale.t('rr.hide_trail', 'Hide trail')
                          : locale.t('rr.approval_trail', 'Trail')
                      }}
                    </button>
                  </div>
                </td>
                <td>{{ themeDisplayName(r.enterprise_theme_name) }}</td>
                <td>{{ r.risk_type_name || locale.t('rr.none_dash', '—') }}</td>
                <td class="rr-table__num">{{ scoreKey(r.likelihood_label, r.likelihood_score) }}</td>
                <td class="rr-table__num">{{ scoreKey(r.impact_label, r.impact_score) }}</td>
                <td
                  class="rr-table__rating"
                  :style="ratingStyle(r.inherent_fill_color, r.inherent_text_color)"
                >
                  {{ ratingCell(r.inherent_score, r.inherent_rating) }}
                </td>
                <td
                  class="rr-table__rating"
                  :style="ratingStyle(r.residual_fill_color, r.residual_text_color)"
                >
                  {{ ratingCell(r.residual_score, r.residual_rating) }}
                </td>
                <td>{{ r.risk_movement || locale.t('rr.none_dash', '—') }}</td>
              </tr>
              <tr v-if="expandedRiskId === r.id" class="rr-table__trail-row">
                <td :colspan="colCount()" @click.stop>
                  <p v-if="trailLoadingId === r.id" class="rr-muted">
                    {{ locale.t('rr.loading_trail', 'Loading approval trail…') }}
                  </p>
                  <RiskApprovalTrail
                    v-else
                    :steps="trailByRisk[r.id] || []"
                    :workflow-state="r.workflow_state"
                    :can-approve-id="canApproveStep(trailByRisk[r.id] || [])"
                    :approving="approvingId != null"
                    @approve="approveFromTrail"
                  />
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <p v-if="rows.length === 0" class="rr-muted">
          {{ locale.t('rr.no_risks_filter', 'No risks for this division / filter set.') }}
        </p>
      </div>
    </template>

    <v-dialog :model-value="ioModalOpen" max-width="640" scrollable persistent @update:model-value="(v) => { if (!v) closeIoReview() }">
      <v-card class="rr-io-modal">
        <v-card-title class="rr-io-modal__title">
          {{ locale.t('rr.io_review_modal_title', 'IO review') }}
        </v-card-title>
        <v-card-subtitle v-if="ioRisk" class="rr-io-modal__sub">
          {{ ioRisk.name }}
        </v-card-subtitle>
        <v-card-text>
          <p v-if="ioLoading" class="rr-muted">{{ locale.t('rr.loading', 'Loading…') }}</p>
          <p v-else-if="ioError" class="rr-error">{{ ioError }}</p>
          <div v-else class="rr-io-modal__fields">
            <div class="rr-io-modal__period">
              <UFormField :label="locale.t('rr.filter_year', 'Year')">
                <USelect
                  v-model="ioForm.year"
                  :items="yearOptions"
                  hide-details
                  @update:model-value="reloadIoPeriod"
                />
              </UFormField>
              <UFormField :label="locale.t('rr.filter_quarter', 'Quarter')">
                <USelect
                  v-model="ioForm.quarter"
                  :items="quarterOptions"
                  hide-details
                  @update:model-value="reloadIoPeriod"
                />
              </UFormField>
            </div>
            <UFormField :label="locale.t('rr.timeline', 'Timeline')">
              <UInput v-model="ioForm.timeline" hide-details />
            </UFormField>
            <UFormField :label="locale.t('rr.action_update', 'Action update')">
              <UTextarea v-model="ioForm.action_update" :rows="3" hide-details />
            </UFormField>
            <UFormField :label="locale.t('rr.oio_notes', 'OIO verification notes')">
              <UTextarea v-model="ioForm.oio_verification_notes" :rows="3" hide-details />
            </UFormField>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" :disabled="ioSaving" @click="closeIoReview">
            {{ locale.t('rr.cancel', 'Cancel') }}
          </v-btn>
          <v-btn color="primary" variant="flat" :loading="ioSaving" :disabled="ioLoading" @click="saveIoReview">
            {{ ioSaving ? locale.t('rr.saving', 'Saving…') : locale.t('rr.save_review', 'Save review') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.rr-io-modal__title {
  font-size: 1.1rem;
  font-weight: 700;
  padding-bottom: 0;
}
.rr-io-modal__sub {
  opacity: 1;
  color: #475569;
  white-space: normal;
  padding-top: 0.15rem;
}
.rr-io-modal__fields {
  display: grid;
  gap: 0.85rem;
  padding-top: 0.35rem;
}
.rr-io-modal__period {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem;
}
.rr-period-chip {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #1e3a8a;
  background: #dbeafe;
  border-radius: 999px;
  vertical-align: baseline;
}
.rr-filters__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}
.rr-filters__hint { margin: 0.65rem 0 0; }
.rr-show-all {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.5rem;
  padding: 0.35rem 0.15rem;
  font-size: 0.875rem;
  font-weight: 525;
  color: #555;
  cursor: pointer;
  user-select: none;
}
.rr-show-all input {
  width: 1rem;
  height: 1rem;
  accent-color: #119a48;
  cursor: pointer;
}
.rr-all-status {
  margin: 0 0 0.85rem;
  font-size: 0.88rem;
}
.rr-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}
.rr-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin: 0 0 0.85rem;
  flex-wrap: wrap;
}
.rr-pager__status {
  display: grid;
  gap: 0.15rem;
  text-align: center;
}
.rr-table--register th {
  background: #4a5560;
  color: #fff;
  font-weight: 600;
  font-size: 0.78rem;
  line-height: 1.25;
  vertical-align: middle;
  text-align: center;
  white-space: normal;
  min-width: 5.5rem;
}
.rr-table--register td {
  font-size: 0.86rem;
  vertical-align: middle;
}
.rr-table__name {
  min-width: 16rem;
  max-width: 26rem;
  color: #1a2b3c;
}
.rr-risk-name {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  justify-content: space-between;
}
.rr-risk-name__text {
  font-weight: 600;
  line-height: 1.35;
}
.rr-risk-name__edit {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.15rem 0.45rem;
  border: 1px solid #bfdbfe;
  border-radius: 4px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  cursor: pointer;
}
.rr-risk-name__edit:hover {
  background: #dbeafe;
}
.rr-risk-name__icon {
  font-size: 0.85rem;
  line-height: 1;
}
.rr-risk-name__trail {
  flex-shrink: 0;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #334155;
  border-radius: 999px;
  padding: 0.15rem 0.55rem;
  font-size: 0.72rem;
  font-weight: 600;
  cursor: pointer;
}
.rr-risk-name__trail.is-open,
.rr-risk-name__trail:hover {
  border-color: #119a48;
  color: #0f766e;
}
.rr-table__trail-row td {
  padding: 0 !important;
  background: #f8fafc;
}
.rr-table__trail-row:hover td {
  background: #f8fafc;
}
.rr-table__num { text-align: center; font-variant-numeric: tabular-nums; }
.rr-table__rating {
  text-align: center;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
</style>
