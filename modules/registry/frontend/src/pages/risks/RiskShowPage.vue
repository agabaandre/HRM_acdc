<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { createRisk, fetchRisk, updateRisk, type RiskLookups, type RiskRow } from '@/lib/riskApi'
import { api } from '@/lib/api'
import { scoreOptionLabel, useRiskLookups } from '@/composables/useRiskLookups'
import { useStaffDirectory, type StaffDirectoryOpt } from '@/composables/useStaffDirectory'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type ReviewQuarter = {
  year: number
  quarter: number
  likelihood?: number
  impact?: number
  mitigation_strategy?: string | null
  inherent_score?: number
}

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()
const { loadStaffDirectory } = useStaffDirectory()
const isNew = computed(() => route.name === 'risk-new')
const riskId = computed(() => Number(route.params.id || 0))

const lookups = ref<RiskLookups | null>(null)
const staffOptions = ref<StaffDirectoryOpt[]>([])
const ownerIds = ref<number[]>([])
const loading = ref(true)
const saving = ref(false)
const submitting = ref(false)
const savingReview = ref(false)
const error = ref<string | null>(null)
const audit = ref<Array<{ id: number; action: string; actor_staff_id: number | null; created_at: string }>>([])
const trendLabel = ref('')
const savedReviews = ref<ReviewQuarter[]>([])
const reviewPeriodSaved = ref(false)
const review = ref({
  year: new Date().getFullYear(),
  quarter: Math.ceil((new Date().getMonth() + 1) / 3),
  likelihood: 3,
  impact: 3,
  mitigation_strategy: '',
})

const form = ref({
  name: '',
  division_id: null as number | null,
  enterprise_theme_id: null as number | null,
  risk_type_id: null as number | null,
  status_id: null as number | null,
  mitigation_effectiveness_id: null as number | null,
  inherent_likelihood: 3,
  inherent_impact: 3,
  consequence: '',
  root_causes: '',
  mitigation: '',
  management_response: '',
})

const divisionName = ref('')

const preview = computed(() => {
  const l = form.value.inherent_likelihood
  const i = form.value.inherent_impact
  const score = l * i
  const band = lookups.value?.rating_bands.find((b) => score >= b.min_score && score <= b.max_score)
  return {
    score,
    rating: band?.rating || '',
    fill: band?.fill_color || '',
    text: band?.text_color || '',
  }
})

const reviewPreview = computed(() => {
  const score = review.value.likelihood * review.value.impact
  const band = lookups.value?.rating_bands.find((b) => score >= b.min_score && score <= b.max_score)
  return {
    score,
    rating: band?.rating || '',
    fill: band?.fill_color || '',
    text: band?.text_color || '',
  }
})

const noneOpt = computed(() => ({ label: locale.t('rr.none_dash', '—'), value: null as number | null }))

const themeItems = computed(() => [
  noneOpt.value,
  ...(lookups.value?.enterprise_themes || []).map((t) => ({
    label: t.name.replace(/^\s*\d+\.\s*/, ''),
    value: t.id,
  })),
])

const riskTypeItems = computed(() => [
  noneOpt.value,
  ...(lookups.value?.risk_types || []).map((t) => ({ label: t.name, value: t.id })),
])

const statusItems = computed(() => [
  noneOpt.value,
  ...(lookups.value?.statuses || []).map((t) => ({ label: t.name, value: t.id })),
])

const likelihoodItems = computed(() =>
  (lookups.value?.likelihoods || []).map((row) => ({
    label: scoreOptionLabel(row),
    value: row.score,
  })),
)

const impactItems = computed(() =>
  (lookups.value?.impacts || []).map((row) => ({
    label: scoreOptionLabel(row),
    value: row.score,
  })),
)

const mitigationItems = computed(() => [
  noneOpt.value,
  ...(lookups.value?.mitigation_effectiveness || []).map((t) => ({ label: t.name, value: t.id })),
])

const quarterItems = computed(() => [
  { label: locale.t('rr.quarter_q1', 'Q1'), value: 1 },
  { label: locale.t('rr.quarter_q2', 'Q2'), value: 2 },
  { label: locale.t('rr.quarter_q3', 'Q3'), value: 3 },
  { label: locale.t('rr.quarter_q4', 'Q4'), value: 4 },
])

function ensureStaffOption(id: number, name?: string | null) {
  if (id < 1) return
  if (staffOptions.value.some((s) => s.value === id)) return
  staffOptions.value = [...staffOptions.value, { label: name || locale.t('rr.staff_number', 'Staff #{id}', { id }), value: id }]
}

function applyRisk(r: RiskRow) {
  form.value = {
    name: r.name,
    division_id: r.division_id,
    enterprise_theme_id: r.enterprise_theme_id,
    risk_type_id: r.risk_type_id,
    status_id: r.status_id,
    mitigation_effectiveness_id: r.mitigation_effectiveness_id ?? null,
    inherent_likelihood: r.inherent_likelihood ?? 3,
    inherent_impact: r.inherent_impact ?? 3,
    consequence: r.consequence ?? '',
    root_causes: r.root_causes ?? '',
    mitigation: r.mitigation ?? '',
    management_response: r.management_response ?? '',
  }
  divisionName.value = r.division_name || divisionName.value || (r.division_id ? locale.t('rr.division_number', 'Division {id}', { id: r.division_id }) : locale.t('rr.unassigned', 'Unassigned'))
  if (r.inherent_likelihood) review.value.likelihood = r.inherent_likelihood
  if (r.inherent_impact) review.value.impact = r.inherent_impact
  syncReviewMitigationFromForm()
}

function findSavedReview(year: number, quarter: number): ReviewQuarter | undefined {
  return savedReviews.value.find((q) => Number(q.year) === year && Number(q.quarter) === quarter)
}

function syncReviewMitigationFromForm() {
  if (reviewPeriodSaved.value) return
  review.value.mitigation_strategy = form.value.mitigation || ''
}

function applyReviewPeriod() {
  const existing = findSavedReview(review.value.year, review.value.quarter)
  if (existing) {
    reviewPeriodSaved.value = true
    if (existing.likelihood) review.value.likelihood = Number(existing.likelihood)
    if (existing.impact) review.value.impact = Number(existing.impact)
    review.value.mitigation_strategy = existing.mitigation_strategy || ''
    return
  }
  reviewPeriodSaved.value = false
  review.value.likelihood = form.value.inherent_likelihood || 3
  review.value.impact = form.value.inherent_impact || 3
  review.value.mitigation_strategy = form.value.mitigation || ''
}

async function loadTrends(id: number) {
  const { data } = await api.get<{
    quarters: ReviewQuarter[]
    annual: Array<{ year: number; avg_score: number }>
  }>(`/api/v1/risks/${id}/trends`)
  savedReviews.value = data.quarters || []
  const quartersPart = (data.quarters || [])
    .map((q) => `${locale.t(`rr.quarter_q${q.quarter}` as 'rr.quarter_q1', `Q${q.quarter}`)} ${q.year}=${q.inherent_score}`)
    .join(', ')
  const annualPart = (data.annual || []).map((a) => `${a.year}=${a.avg_score}`).join(', ')
  trendLabel.value = quartersPart || annualPart
    ? locale.t('rr.trend_summary', 'Quarters: {quarters} · Annual: {annual}', {
        quarters: quartersPart || locale.t('rr.none_dash', '—'),
        annual: annualPart || locale.t('rr.none_dash', '—'),
      })
    : ''
  applyReviewPeriod()
}

watch(
  () => form.value.mitigation,
  () => {
    if (!isNew.value) syncReviewMitigationFromForm()
  },
)

watch(
  () => [review.value.year, review.value.quarter] as const,
  () => {
    if (!isNew.value) applyReviewPeriod()
  },
)

watch(
  () => riskId.value,
  async (id, prev) => {
    if (!id || id === prev || isNew.value) return
    loading.value = true
    error.value = null
    try {
      const detail = await fetchRisk(id)
      applyRisk(detail.data)
      ownerIds.value = detail.owners.map((o) => o.staff_id)
      for (const o of detail.owners) {
        ensureStaffOption(o.staff_id, (o as { staff_name?: string }).staff_name)
      }
      audit.value = detail.audit
      await loadTrends(id)
    } catch (e) {
      error.value = apiErrorMessage(e, locale.t('rr.load_risk_error', 'Could not load risk'))
    } finally {
      loading.value = false
    }
  },
)

onMounted(async () => {
  try {
    const lookupsPromise = loadLookups()
    const staffPromise = loadStaffDirectory().then((opts) => {
      staffOptions.value = opts
      return opts
    })
    if (!isNew.value && riskId.value > 0) {
      const [lookupData, detail] = await Promise.all([
        lookupsPromise,
        fetchRisk(riskId.value),
        staffPromise,
      ]).then(([l, d]) => [l, d] as const)
      lookups.value = lookupData
      applyRisk(detail.data)
      ownerIds.value = detail.owners.map((o) => o.staff_id)
      for (const o of detail.owners) {
        ensureStaffOption(o.staff_id, (o as { staff_name?: string }).staff_name)
      }
      audit.value = detail.audit
      await loadTrends(riskId.value)
    } else {
      const [lookupData] = await Promise.all([lookupsPromise, staffPromise])
      lookups.value = lookupData
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_risk_error', 'Could not load risk'))
  } finally {
    loading.value = false
  }
})

async function save(options: { submitAfterCreate?: boolean } = {}) {
  saving.value = true
  error.value = null
  const owner_staff_ids = ownerIds.value.filter((n) => n > 0)
  const payload = { ...form.value, owner_staff_ids }
  try {
    if (isNew.value) {
      const created = await createRisk(payload)
      if (options.submitAfterCreate) {
        submitting.value = true
        try {
          await api.post(`/api/v1/risks/${created.id}/submit`)
        } catch (e) {
          error.value = apiErrorMessage(e, locale.t('rr.submit_failed', 'Submit failed'))
          await router.replace({ name: 'risk-edit', params: { id: created.id } })
          return
        } finally {
          submitting.value = false
        }
      }
      await router.replace({ name: 'risk-edit', params: { id: created.id } })
    } else {
      const updated = await updateRisk(riskId.value, payload)
      applyRisk(updated)
      const detail = await fetchRisk(riskId.value)
      ownerIds.value = detail.owners.map((o) => o.staff_id)
      for (const o of detail.owners) {
        ensureStaffOption(o.staff_id, (o as { staff_name?: string }).staff_name)
      }
      audit.value = detail.audit
      syncReviewMitigationFromForm()
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.save_failed', 'Save failed'))
  } finally {
    saving.value = false
  }
}

async function submitNewRisk() {
  await save({ submitAfterCreate: true })
}

async function saveReview() {
  savingReview.value = true
  error.value = null
  try {
    await api.post(`/api/v1/risks/${riskId.value}/reviews`, {
      year: review.value.year,
      quarter: review.value.quarter,
      likelihood: review.value.likelihood,
      impact: review.value.impact,
      mitigation_strategy: reviewPeriodSaved.value
        ? review.value.mitigation_strategy
        : (review.value.mitigation_strategy || form.value.mitigation),
    })
    await loadTrends(riskId.value)
    reviewPeriodSaved.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.review_save_failed', 'Review save failed'))
  } finally {
    savingReview.value = false
  }
}
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <button
          v-if="!isNew"
          type="button"
          class="rr-link"
          @click="router.push({ name: 'risk-show', params: { id: riskId } })"
        >
          {{ locale.t('rr.back_profile', '← Risk profile') }}
        </button>
        <button v-else type="button" class="rr-link" @click="router.push({ name: 'risks' })">
          {{ locale.t('rr.back_register', '← Risk register') }}
        </button>
        <h1>{{ isNew ? locale.t('rr.new_title', 'New risk') : locale.t('rr.edit_title', 'Edit risk') }}</h1>
        <p v-if="!isNew && divisionName" class="rr-page__sub">
          {{ locale.t('rr.edit_sub', '{division} · Division focal persons update scores and quarterly reviews', { division: divisionName }) }}
        </p>
      </div>
      <div class="rr-page__actions">
        <button
          v-if="isNew"
          type="button"
          class="rr-btn rr-btn--primary"
          :disabled="saving || submitting || loading"
          @click="submitNewRisk"
        >
          {{
            submitting || saving
              ? locale.t('rr.submitting', 'Submitting…')
              : locale.t('rr.submit_review', 'Submit for review')
          }}
        </button>
        <button type="button" class="rr-btn" :disabled="saving || loading" @click="save()">
          {{ saving && !submitting ? locale.t('rr.saving', 'Saving…') : locale.t('rr.save', 'Save') }}
        </button>
      </div>
    </header>

    <RrSkeleton v-if="loading" variant="form" :rows="5" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>

    <template v-else>
      <form class="rr-card rr-form-fields" @submit.prevent="save()">
        <h2>{{ locale.t('rr.risk_details', 'Risk details') }}</h2>
        <UFormField :label="locale.t('rr.risk_name', 'Risk name')" required>
          <UInput v-model="form.name" maxlength="512" required hide-details />
        </UFormField>
        <div class="rr-grid">
          <UFormField :label="locale.t('rr.enterprise_theme', 'Enterprise theme')">
            <USelect v-model="form.enterprise_theme_id" :items="themeItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.risk_type', 'Risk type')">
            <USelect v-model="form.risk_type_id" :items="riskTypeItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.status', 'Status')">
            <USelect v-model="form.status_id" :items="statusItems" :clearable="false" hide-details />
          </UFormField>
        </div>
        <div class="rr-grid">
          <UFormField :label="locale.t('rr.likelihood', 'Likelihood')">
            <USelect v-model="form.inherent_likelihood" :items="likelihoodItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.impact', 'Impact')">
            <USelect v-model="form.inherent_impact" :items="impactItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.mitigation_effectiveness', 'Mitigation effectiveness')">
            <USelect v-model="form.mitigation_effectiveness_id" :items="mitigationItems" :clearable="false" hide-details />
          </UFormField>
          <div class="rr-preview">
            <p
              class="rr-score-preview"
              :style="
                preview.fill
                  ? {
                      background: preview.fill,
                      color: preview.text,
                      display: 'inline-block',
                      padding: '0.2rem 0.55rem',
                      fontWeight: 600,
                    }
                  : undefined
              "
            >
              {{ locale.t('rr.inherent_preview', 'Inherent:') }} <strong>{{ preview.score }}</strong>
              <span v-if="preview.rating"> {{ preview.rating }}</span>
            </p>
          </div>
        </div>
        <UFormField :label="locale.t('rr.consequence', 'Consequence')">
          <UTextarea v-model="form.consequence" :rows="3" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.root_causes', 'Root causes')">
          <UTextarea v-model="form.root_causes" :rows="3" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.mitigation', 'Mitigation')">
          <UTextarea v-model="form.mitigation" :rows="3" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.management_response', 'Management response')">
          <UTextarea v-model="form.management_response" :rows="2" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.owners', 'Owners')" class="rr-owners">
          <USelectMenu
            v-model="ownerIds"
            :items="staffOptions"
            value-key="value"
            multiple
            searchable
            clearable
            :placeholder="locale.t('rr.owners_placeholder', 'Search and select staff…')"
            icon="mdi-account-multiple"
            hide-details
          />
        </UFormField>
      </form>

      <section v-if="!isNew" class="rr-card rr-form-fields">
        <h2>{{ locale.t('rr.quarterly_review', 'Quarterly review') }}</h2>
        <p v-if="!reviewPeriodSaved" class="rr-muted rr-review-hint">
          {{
            locale.t(
              'rr.review_mitigation_mirrors',
              'Mitigation strategy mirrors the risk form until this quarterly review is saved.',
            )
          }}
        </p>
        <div class="rr-grid">
          <UFormField :label="locale.t('rr.filter_year', 'Year')">
            <UInput v-model.number="review.year" type="number" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.filter_quarter', 'Quarter')">
            <USelect v-model="review.quarter" :items="quarterItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.likelihood', 'Likelihood')">
            <USelect v-model="review.likelihood" :items="likelihoodItems" :clearable="false" hide-details />
          </UFormField>
          <UFormField :label="locale.t('rr.impact', 'Impact')">
            <USelect v-model="review.impact" :items="impactItems" :clearable="false" hide-details />
          </UFormField>
          <div class="rr-preview">
            <p
              class="rr-score-preview"
              :style="
                reviewPreview.fill
                  ? {
                      background: reviewPreview.fill,
                      color: reviewPreview.text,
                      display: 'inline-block',
                      padding: '0.2rem 0.55rem',
                      fontWeight: 600,
                    }
                  : undefined
              "
            >
              {{ locale.t('rr.review_score', 'Review score:') }} <strong>{{ reviewPreview.score }}</strong>
              <span v-if="reviewPreview.rating"> {{ reviewPreview.rating }}</span>
            </p>
          </div>
        </div>
        <UFormField :label="locale.t('rr.mitigation_strategy', 'Mitigation strategy')">
          <UTextarea
            v-model="review.mitigation_strategy"
            :rows="2"
            :readonly="!reviewPeriodSaved"
            hide-details
          />
        </UFormField>
        <button type="button" class="rr-btn rr-btn--primary" :disabled="savingReview" @click="saveReview">
          {{ savingReview ? locale.t('rr.saving', 'Saving…') : locale.t('rr.save_review', 'Save review') }}
        </button>
        <p v-if="trendLabel" class="rr-muted">{{ trendLabel }}</p>
      </section>

      <section v-if="!isNew && audit.length" class="rr-card">
        <h2>{{ locale.t('rr.audit_trail', 'Audit trail') }}</h2>
        <ul class="rr-audit">
          <li v-for="a in audit" :key="a.id">
            <strong>{{ a.action }}</strong>
            {{ locale.t('rr.by_staff', 'by staff {id}', { id: a.actor_staff_id ?? locale.t('rr.none_dash', '—') }) }}
            <span class="rr-muted">{{ a.created_at }}</span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  align-items: center;
}
.rr-review-hint { margin: 0 0 0.75rem; font-size: 0.88rem; }
.rr-audit { list-style: none; padding: 0; margin: 0; }
.rr-audit li { padding: 0.45rem 0; border-bottom: 1px solid #e8edf2; }
.rr-audit li:last-child { border-bottom: 0; }
</style>
