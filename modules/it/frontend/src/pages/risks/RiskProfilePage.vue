<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { fetchRisk, type RiskRow } from '@/lib/riskApi'
import { api } from '@/lib/api'
import { themeDisplayName } from '@/lib/riskLabels'
import { useRiskLookups } from '@/composables/useRiskLookups'
import { useLocaleStore } from '@/stores/locale'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type TrendQuarter = {
  year: number
  quarter: number
  likelihood?: number
  impact?: number
  inherent_score: number
  inherent_rating?: string | null
  residual_score?: number | null
  residual_rating?: string | null
}

type TrendMonth = {
  year: number
  month: number
  quarter?: number
  likelihood?: number
  impact?: number
  inherent_score: number
  inherent_rating?: string | null
  residual_score?: number | null
  residual_rating?: string | null
}

type RiskDetail = RiskRow & {
  enterprise_theme_name?: string | null
  risk_type_name?: string | null
  status_name?: string | null
  mitigation_effectiveness_name?: string | null
  inherent_fill_color?: string | null
  inherent_text_color?: string | null
  residual_fill_color?: string | null
  residual_text_color?: string | null
}

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()

const riskId = computed(() => Number(route.params.id || 0))
const loading = ref(true)
const error = ref<string | null>(null)
const risk = ref<RiskDetail | null>(null)
const owners = ref<
  Array<{ staff_id: number; staff_name?: string | null; job_title?: string | null; staff_label?: string | null }>
>([])
const quarters = ref<TrendQuarter[]>([])
const months = ref<TrendMonth[]>([])
const annual = ref<Array<{ year: number; avg_score: number; rating?: string }>>([])
const trendTab = ref<'quarterly' | 'monthly'>('quarterly')

const periodLabel = computed(() => locale.t('rr.period_q2_2026', 'Q2 2026'))
const dash = computed(() => locale.t('rr.none_dash', '—'))

const MONTH_KEYS = [
  'rr.month_jan',
  'rr.month_feb',
  'rr.month_mar',
  'rr.month_apr',
  'rr.month_may',
  'rr.month_jun',
  'rr.month_jul',
  'rr.month_aug',
  'rr.month_sep',
  'rr.month_oct',
  'rr.month_nov',
  'rr.month_dec',
] as const
const MONTH_FALLBACKS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function ratingStyle(fill?: string | null, text?: string | null): Record<string, string> | undefined {
  if (!fill) return undefined
  return {
    backgroundColor: fill,
    color: text || '#0F172A',
    fontWeight: '700',
  }
}

function scoreCell(score: number | null | undefined, rating: string | null | undefined): string {
  if (score == null && !rating) return dash.value
  if (score != null && rating) return `${score} · ${rating}`
  if (score != null) return String(score)
  return rating || dash.value
}

function ownerLabel(o: {
  staff_id: number
  staff_name?: string | null
  job_title?: string | null
  staff_label?: string | null
}): string {
  if (o.staff_label) return o.staff_label
  const name = o.staff_name || locale.t('rr.staff_number', 'Staff #{id}', { id: o.staff_id })
  if (o.job_title) return `${name} (${o.job_title})`
  return name
}

function monthLabel(month: number, year: number): string {
  const idx = Math.max(1, Math.min(12, month)) - 1
  return `${locale.t(MONTH_KEYS[idx], MONTH_FALLBACKS[idx])} ${year}`
}

const trendCategories = computed(() => {
  if (trendTab.value === 'monthly') {
    return months.value.map((m) => monthLabel(m.month, m.year))
  }
  return quarters.value.map((q) => `${locale.t(`rr.quarter_q${q.quarter}` as 'rr.quarter_q1', `Q${q.quarter}`)} ${q.year}`)
})

const trendSeries = computed(() => {
  const points = trendTab.value === 'monthly' ? months.value : quarters.value
  return [
    {
      name: locale.t('rr.series_inherent', 'Inherent score'),
      data: points.map((q) => Number(q.inherent_score || 0)),
      color: '#1d4ed8',
    },
    {
      name: locale.t('rr.series_residual', 'Residual score'),
      data: points.map((q) => Number(q.residual_score ?? q.inherent_score ?? 0)),
      color: '#0f766e',
    },
  ]
})

const hasTrend = computed(() =>
  trendTab.value === 'monthly' ? months.value.length > 0 : quarters.value.length > 0,
)

const trendNote = computed(() => {
  if (trendTab.value === 'monthly') {
    return locale.t(
      'rr.trend_note_monthly',
      'Monthly view expands each quarterly review across its three months. Current register baseline is treated as {period} where no earlier review exists.',
      { period: periodLabel.value },
    )
  }
  return locale.t(
    'rr.trend_note',
    'Quarterly inherent and residual scores. Current register baseline is treated as {period} where no earlier review exists.',
    { period: periodLabel.value },
  )
})

const narrativeBlocks = computed(() => {
  const r = risk.value
  if (!r) return []
  return [
    { title: locale.t('rr.consequence', 'Consequence'), body: r.consequence },
    { title: locale.t('rr.root_causes', 'Root causes'), body: r.root_causes },
    { title: locale.t('rr.mitigation', 'Mitigation'), body: r.mitigation },
    { title: locale.t('rr.management_response', 'Management response'), body: r.management_response },
    { title: locale.t('rr.action_update', 'Action update'), body: r.action_update },
    { title: locale.t('rr.oio_notes', 'OIO verification notes'), body: r.oio_verification_notes },
  ].filter((b) => (b.body || '').trim() !== '')
})

function goEdit() {
  void router.push({ name: 'risk-edit', params: { id: riskId.value } })
}

function seedBaselineTrend() {
  if (!risk.value || risk.value.inherent_score == null) return
  const baseline: TrendQuarter = {
    year: 2026,
    quarter: 2,
    likelihood: risk.value.inherent_likelihood ?? undefined,
    impact: risk.value.inherent_impact ?? undefined,
    inherent_score: risk.value.inherent_score,
    inherent_rating: risk.value.inherent_rating,
    residual_score: risk.value.residual_score,
    residual_rating: risk.value.residual_rating,
  }
  if (quarters.value.length === 0) {
    quarters.value = [baseline]
  }
  if (months.value.length === 0) {
    months.value = [4, 5, 6].map((month) => ({
      year: 2026,
      month,
      quarter: 2,
      likelihood: baseline.likelihood,
      impact: baseline.impact,
      inherent_score: baseline.inherent_score,
      inherent_rating: baseline.inherent_rating,
      residual_score: baseline.residual_score,
      residual_rating: baseline.residual_rating,
    }))
  }
}

onMounted(async () => {
  if (riskId.value < 1) {
    error.value = locale.t('rr.invalid_risk', 'Invalid risk.')
    loading.value = false
    return
  }
  try {
    const [detail, trends] = await Promise.all([
      fetchRisk(riskId.value),
      api.get<{
        quarters: TrendQuarter[]
        months?: TrendMonth[]
        annual: Array<{ year: number; avg_score: number; rating?: string }>
      }>(`/api/v1/risks/${riskId.value}/trends`),
      loadLookups(),
    ])
    risk.value = detail.data as RiskDetail
    owners.value = detail.owners
    quarters.value = trends.data.quarters || []
    months.value = trends.data.months || []
    annual.value = trends.data.annual || []
    seedBaselineTrend()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_profile_error', 'Could not load risk profile'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rr-profile">
    <header class="rr-profile__hero">
      <div class="rr-profile__hero-top">
        <button type="button" class="rr-link" @click="router.push({ name: 'risks' })">
          {{ locale.t('rr.back_register', '← Risk register') }}
        </button>
        <div class="rr-profile__actions">
          <span class="rr-profile__period">{{ periodLabel }}</span>
          <button type="button" class="rr-btn rr-btn--primary" @click="goEdit">
            {{ locale.t('rr.profile_edit_submit', 'Edit risk') }}
          </button>
        </div>
      </div>

      <RrSkeleton v-if="loading" variant="form" :rows="3" />
      <p v-else-if="error" class="rr-error">{{ error }}</p>
      <template v-else-if="risk">
        <p class="rr-profile__eyebrow">{{ locale.t('rr.profile_eyebrow', 'Risk profile · view only') }}</p>
        <h1 class="rr-profile__title">{{ risk.name }}</h1>
        <p class="rr-profile__meta">
          <span>{{ risk.division_name || locale.t('rr.unassigned', 'Unassigned') }}</span>
          <span v-if="risk.risk_type_name" class="rr-profile__dot">·</span>
          <span v-if="risk.risk_type_name">{{ risk.risk_type_name }}</span>
          <span v-if="risk.enterprise_theme_name" class="rr-profile__dot">·</span>
          <span v-if="risk.enterprise_theme_name">{{ themeDisplayName(risk.enterprise_theme_name) }}</span>
        </p>
      </template>
    </header>

    <template v-if="!loading && risk">
      <section class="rr-profile__scores">
        <div class="rr-profile__score-card">
          <div class="rr-profile__score-label">{{ locale.t('rr.score_likelihood', 'Likelihood (L)') }}</div>
          <div class="rr-profile__score-value">{{ risk.inherent_likelihood ?? dash }}</div>
        </div>
        <div class="rr-profile__score-card">
          <div class="rr-profile__score-label">{{ locale.t('rr.score_impact', 'Impact (I)') }}</div>
          <div class="rr-profile__score-value">{{ risk.inherent_impact ?? dash }}</div>
        </div>
        <div
          class="rr-profile__score-card rr-profile__score-card--band"
          :style="ratingStyle(risk.inherent_fill_color, risk.inherent_text_color)"
        >
          <div class="rr-profile__score-label">{{ locale.t('rr.score_inherent', 'Inherent risk') }}</div>
          <div class="rr-profile__score-value">{{ scoreCell(risk.inherent_score, risk.inherent_rating) }}</div>
        </div>
        <div
          class="rr-profile__score-card rr-profile__score-card--band"
          :style="ratingStyle(risk.residual_fill_color, risk.residual_text_color)"
        >
          <div class="rr-profile__score-label">{{ locale.t('rr.score_residual', 'Residual risk') }}</div>
          <div class="rr-profile__score-value">{{ scoreCell(risk.residual_score, risk.residual_rating) }}</div>
        </div>
      </section>

      <div class="rr-profile__grid">
        <section class="rr-card rr-profile__panel">
          <h2>{{ locale.t('rr.classification', 'Classification') }}</h2>
          <dl class="rr-profile__dl">
            <div>
              <dt>{{ locale.t('rr.enterprise_theme', 'Enterprise theme') }}</dt>
              <dd>{{ themeDisplayName(risk.enterprise_theme_name) }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.risk_type', 'Risk type') }}</dt>
              <dd>{{ risk.risk_type_name || dash }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.status', 'Status') }}</dt>
              <dd>{{ risk.status_name || dash }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.mitigation_effectiveness', 'Mitigation effectiveness') }}</dt>
              <dd>{{ risk.mitigation_effectiveness_name || dash }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.risk_movement', 'Risk movement') }}</dt>
              <dd>{{ risk.risk_movement || dash }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.timeline', 'Timeline') }}</dt>
              <dd>{{ risk.timeline || dash }}</dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.owners', 'Owners') }}</dt>
              <dd>
                <template v-if="owners.length">
                  {{ owners.map((o) => ownerLabel(o)).join(', ') }}
                </template>
                <template v-else>{{ dash }}</template>
              </dd>
            </div>
            <div>
              <dt>{{ locale.t('rr.workflow', 'Workflow') }}</dt>
              <dd>{{ risk.workflow_state || dash }}</dd>
            </div>
          </dl>
        </section>

        <section class="rr-card rr-profile__panel">
          <div class="rr-profile__trend-head">
            <h2>{{ locale.t('rr.score_trend', 'Score trend') }}</h2>
            <div class="rr-trend-tabs" role="tablist" :aria-label="locale.t('rr.score_trend', 'Score trend')">
              <button
                type="button"
                role="tab"
                class="rr-trend-tabs__btn"
                :class="{ 'is-active': trendTab === 'quarterly' }"
                :aria-selected="trendTab === 'quarterly'"
                @click="trendTab = 'quarterly'"
              >
                {{ locale.t('rr.trend_tab_quarterly', 'Quarterly') }}
              </button>
              <button
                type="button"
                role="tab"
                class="rr-trend-tabs__btn"
                :class="{ 'is-active': trendTab === 'monthly' }"
                :aria-selected="trendTab === 'monthly'"
                @click="trendTab = 'monthly'"
              >
                {{ locale.t('rr.trend_tab_monthly', 'Monthly') }}
              </button>
            </div>
          </div>
          <p class="rr-muted rr-profile__trend-note">{{ trendNote }}</p>
          <PortalHighchart
            v-if="hasTrend"
            :key="trendTab"
            title=""
            type="area"
            :categories="trendCategories"
            :series="trendSeries"
            :height="280"
            :y-axis-title="locale.t('rr.score_axis', 'Score (1–25)')"
            :y-axis-max="25"
            :show-table-toggle="false"
          />
          <p v-else class="rr-muted">{{ locale.t('rr.no_trend', 'No trend points available yet.') }}</p>
          <div v-if="annual.length" class="rr-profile__annual">
            <h3>{{ locale.t('rr.annual_average', 'Annual average') }}</h3>
            <ul>
              <li v-for="a in annual" :key="a.year">
                {{ a.year }} — {{ a.avg_score }}
                <span v-if="a.rating" class="rr-muted"> ({{ a.rating }})</span>
              </li>
            </ul>
          </div>
        </section>
      </div>

      <section v-if="narrativeBlocks.length" class="rr-card rr-profile__narrative">
        <h2>{{ locale.t('rr.risk_narrative', 'Risk narrative') }}</h2>
        <div class="rr-profile__narrative-grid">
          <article v-for="block in narrativeBlocks" :key="block.title" class="rr-profile__narrative-block">
            <h3>{{ block.title }}</h3>
            <p>{{ block.body }}</p>
          </article>
        </div>
      </section>
      <section v-else class="rr-card">
        <h2>{{ locale.t('rr.risk_narrative', 'Risk narrative') }}</h2>
        <p class="rr-muted">{{ locale.t('rr.no_narrative', 'No narrative fields have been captured for this risk yet.') }}</p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-profile {
  max-width: 72rem;
  margin: 0 auto;
}
.rr-profile__hero {
  margin-bottom: 1.25rem;
  padding: 1.25rem 1.35rem 1.4rem;
  background:
    linear-gradient(135deg, rgba(15, 23, 42, 0.04), rgba(29, 78, 216, 0.06)),
    #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.rr-profile__hero-top {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}
.rr-profile__actions {
  display: flex;
  gap: 0.65rem;
  align-items: center;
  flex-wrap: wrap;
}
.rr-profile__period {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #1e3a8a;
  background: #dbeafe;
  border-radius: 999px;
}
.rr-profile__eyebrow {
  margin: 0 0 0.35rem;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #64748b;
}
.rr-profile__title {
  margin: 0;
  font-size: 1.65rem;
  line-height: 1.25;
  color: #0f172a;
  font-weight: 750;
}
.rr-profile__meta {
  margin: 0.55rem 0 0;
  color: #475569;
  font-size: 0.95rem;
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  align-items: center;
}
.rr-profile__dot { color: #94a3b8; }
.rr-profile__scores {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}
.rr-profile__score-card {
  padding: 0.9rem 1rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}
.rr-profile__score-card--band {
  border-color: transparent;
}
.rr-profile__score-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.85;
  margin-bottom: 0.35rem;
}
.rr-profile__score-value {
  font-size: 1.25rem;
  font-weight: 750;
  font-variant-numeric: tabular-nums;
}
.rr-profile__grid {
  display: grid;
  grid-template-columns: 1fr 1.15fr;
  gap: 1rem;
  margin-bottom: 1rem;
}
.rr-profile__panel > h2,
.rr-profile__panel .rr-profile__trend-head h2,
.rr-profile__narrative > h2 {
  margin: 0 0 0.75rem;
  font-size: 1.05rem;
}
.rr-profile__trend-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
  margin-bottom: 0.15rem;
}
.rr-profile__trend-head h2 {
  margin: 0;
}
.rr-trend-tabs {
  display: inline-flex;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  overflow: hidden;
  background: #f8fafc;
}
.rr-trend-tabs__btn {
  border: 0;
  background: transparent;
  padding: 0.28rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #475569;
  cursor: pointer;
}
.rr-trend-tabs__btn.is-active {
  background: #1e3a5f;
  color: #fff;
}
.rr-profile__dl {
  display: grid;
  gap: 0.75rem;
  margin: 0;
}
.rr-profile__dl dt {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  color: #64748b;
}
.rr-profile__dl dd {
  margin: 0.15rem 0 0;
  color: #0f172a;
  font-size: 0.95rem;
  line-height: 1.4;
}
.rr-profile__trend-note {
  margin: 0 0 0.75rem;
  font-size: 0.85rem;
}
.rr-profile__annual {
  margin-top: 0.85rem;
  padding-top: 0.75rem;
  border-top: 1px solid #e2e8f0;
}
.rr-profile__annual h3 {
  margin: 0 0 0.4rem;
  font-size: 0.85rem;
}
.rr-profile__annual ul {
  margin: 0;
  padding-left: 1.1rem;
}
.rr-profile__narrative-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: 1rem;
}
.rr-profile__narrative-block h3 {
  margin: 0 0 0.35rem;
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #64748b;
}
.rr-profile__narrative-block p {
  margin: 0;
  white-space: pre-wrap;
  color: #1e293b;
  line-height: 1.5;
  font-size: 0.92rem;
}
@media (max-width: 900px) {
  .rr-profile__scores { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .rr-profile__grid { grid-template-columns: 1fr; }
}
</style>
