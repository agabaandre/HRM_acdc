<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { createRisk, fetchLookups, fetchRisk, updateRisk, type RiskLookups, type RiskRow } from '@/lib/riskApi'
import { api } from '@/lib/api'

const route = useRoute()
const router = useRouter()
const isNew = computed(() => route.name === 'risk-new')
const riskId = computed(() => Number(route.params.id || 0))

const lookups = ref<RiskLookups | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const audit = ref<Array<{ id: number; action: string; actor_staff_id: number | null; created_at: string }>>([])
const ownersText = ref('')
const trendLabel = ref('')
const review = ref({
  year: new Date().getFullYear(),
  quarter: Math.ceil((new Date().getMonth() + 1) / 3),
  likelihood: 3,
  impact: 3,
  mitigation_strategy: '',
  timeline: '',
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
  timeline: '',
  action_update: '',
  oio_verification_notes: '',
})

const preview = computed(() => {
  const l = form.value.inherent_likelihood
  const i = form.value.inherent_impact
  return { score: l * i }
})

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
    timeline: r.timeline ?? '',
    action_update: r.action_update ?? '',
    oio_verification_notes: r.oio_verification_notes ?? '',
  }
}

onMounted(async () => {
  try {
    lookups.value = await fetchLookups()
    if (!isNew.value && riskId.value > 0) {
      const detail = await fetchRisk(riskId.value)
      applyRisk(detail.data)
      ownersText.value = detail.owners.map((o) => o.staff_id).join(', ')
      audit.value = detail.audit
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load risk')
  } finally {
    loading.value = false
  }
})

async function save() {
  saving.value = true
  error.value = null
  const owner_staff_ids = ownersText.value
    .split(/[,\s]+/)
    .map((s) => Number(s.trim()))
    .filter((n) => n > 0)
  const payload = { ...form.value, owner_staff_ids }
  try {
    if (isNew.value) {
      const created = await createRisk(payload)
      await router.replace({ name: 'risk-show', params: { id: created.id } })
    } else {
      const updated = await updateRisk(riskId.value, payload)
      applyRisk(updated)
      const detail = await fetchRisk(riskId.value)
      audit.value = detail.audit
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed')
  } finally {
    saving.value = false
  }
}

async function submitWorkflow() {
  try {
    await api.post(`/api/v1/risks/${riskId.value}/submit`)
    const detail = await fetchRisk(riskId.value)
    applyRisk(detail.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Submit failed')
  }
}

async function saveReview() {
  try {
    const payload: Record<string, unknown> = {
      year: review.value.year,
      quarter: review.value.quarter,
      likelihood: review.value.likelihood,
      impact: review.value.impact,
      mitigation_strategy: review.value.mitigation_strategy,
    }
    if (review.value.timeline.trim() !== '') {
      payload.timeline = review.value.timeline
    }
    await api.post(`/api/v1/risks/${riskId.value}/reviews`, payload)
    const { data } = await api.get<{ quarters: Array<{ year: number; quarter: number; inherent_score: number }>; annual: Array<{ year: number; avg_score: number }> }>(
      `/api/v1/risks/${riskId.value}/trends`
    )
    trendLabel.value = `Quarters: ${data.quarters.map((q) => `Q${q.quarter} ${q.year}=${q.inherent_score}`).join(', ')} · Annual: ${data.annual.map((a) => `${a.year}=${a.avg_score}`).join(', ')}`
  } catch (e) {
    error.value = apiErrorMessage(e, 'Review save failed')
  }
}
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <button type="button" class="rr-link" @click="router.push({ name: 'risks' })">← Register</button>
        <h1>{{ isNew ? 'New risk' : 'Risk detail' }}</h1>
      </div>
      <button type="button" class="rr-btn rr-btn--primary" :disabled="saving || loading" @click="save">
        {{ saving ? 'Saving…' : 'Save' }}
      </button>
    </header>

    <p v-if="loading" class="rr-muted">Loading…</p>
    <p v-else-if="error" class="rr-error">{{ error }}</p>

    <form v-else class="rr-form" @submit.prevent="save">
      <label>Risk name
        <input v-model="form.name" required maxlength="512" />
      </label>
      <div class="rr-grid">
        <label>Enterprise theme
          <select v-model.number="form.enterprise_theme_id">
            <option :value="null">—</option>
            <option v-for="t in lookups?.enterprise_themes || []" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </label>
        <label>Risk type
          <select v-model.number="form.risk_type_id">
            <option :value="null">—</option>
            <option v-for="t in lookups?.risk_types || []" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </label>
        <label>Status
          <select v-model.number="form.status_id">
            <option :value="null">—</option>
            <option v-for="t in lookups?.statuses || []" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </label>
        <label>Division ID
          <input v-model.number="form.division_id" type="number" />
        </label>
      </div>
      <div class="rr-grid">
        <label>Likelihood (1–5)
          <input v-model.number="form.inherent_likelihood" type="number" min="1" max="5" />
        </label>
        <label>Impact (1–5)
          <input v-model.number="form.inherent_impact" type="number" min="1" max="5" />
        </label>
        <label>Mitigation effectiveness
          <select v-model.number="form.mitigation_effectiveness_id">
            <option :value="null">—</option>
            <option v-for="t in lookups?.mitigation_effectiveness || []" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </label>
        <div class="rr-preview">Inherent score preview: <strong>{{ preview.score }}</strong></div>
      </div>
      <label>Consequence<textarea v-model="form.consequence" rows="3" /></label>
      <label>Root causes<textarea v-model="form.root_causes" rows="3" /></label>
      <label>Mitigation<textarea v-model="form.mitigation" rows="3" /></label>
      <label>Management response<textarea v-model="form.management_response" rows="2" /></label>
      <div class="rr-grid">
        <label>Timeline<input v-model="form.timeline" /></label>
        <label>Owner staff IDs (comma-separated)<input v-model="ownersText" placeholder="169, 62" /></label>
      </div>
      <label>Action update<textarea v-model="form.action_update" rows="2" /></label>
      <label>OIO verification notes<textarea v-model="form.oio_verification_notes" rows="2" /></label>
      <div v-if="!isNew" class="rr-actions">
        <button type="button" class="rr-btn" @click="submitWorkflow">Submit for approval</button>
      </div>
    </form>

    <section v-if="!isNew" class="rr-review">
      <h2>Quarterly review</h2>
      <div class="rr-grid">
        <label>Year<input v-model.number="review.year" type="number" /></label>
        <label>Quarter<select v-model.number="review.quarter"><option :value="1">Q1</option><option :value="2">Q2</option><option :value="3">Q3</option><option :value="4">Q4</option></select></label>
        <label>Likelihood<input v-model.number="review.likelihood" type="number" min="1" max="5" /></label>
        <label>Impact<input v-model.number="review.impact" type="number" min="1" max="5" /></label>
      </div>
      <label>Mitigation strategy<textarea v-model="review.mitigation_strategy" rows="2" /></label>
      <label>Timeline (defaults to previous)<input v-model="review.timeline" /></label>
      <button type="button" class="rr-btn rr-btn--primary" @click="saveReview">Save review</button>
      <p v-if="trendLabel" class="rr-muted">{{ trendLabel }}</p>
    </section>

    <section v-if="!isNew && audit.length" class="rr-audit">
      <h2>Audit trail</h2>
      <ul>
        <li v-for="a in audit" :key="a.id">
          <strong>{{ a.action }}</strong>
          by staff {{ a.actor_staff_id ?? '—' }}
          <span class="rr-muted">{{ a.created_at }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.rr-page { width: 100%; max-width: none; margin: 0; padding: 1.5rem 0 3rem; }
.rr-page__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
.rr-page__header h1 { margin: 0.25rem 0 0; font-size: 1.6rem; }
.rr-link { background: none; border: 0; color: #0b6e4f; cursor: pointer; padding: 0; font-weight: 600; }
.rr-btn { border: 0; border-radius: 6px; padding: 0.55rem 1rem; font-weight: 600; cursor: pointer; }
.rr-btn--primary { background: #0b6e4f; color: #fff; }
.rr-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.rr-form { display: grid; gap: 0.85rem; background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem; }
.rr-form label { display: grid; gap: 0.35rem; font-weight: 600; color: #334; font-size: 0.9rem; }
.rr-form input, .rr-form select, .rr-form textarea { font: inherit; font-weight: 400; padding: 0.45rem 0.55rem; border: 1px solid #c5ced8; border-radius: 6px; }
.rr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
.rr-preview { align-self: end; padding-bottom: 0.4rem; color: #445; }
.rr-actions { margin-top: 0.5rem; }
.rr-btn { border: 1px solid #c5ced8; background: #fff; border-radius: 6px; padding: 0.45rem 0.85rem; cursor: pointer; font-weight: 600; }
.rr-audit { margin-top: 1.5rem; }
.rr-review { margin-top: 1.5rem; background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem; display: grid; gap: 0.75rem; }
.rr-review label { display: grid; gap: 0.3rem; font-weight: 600; font-size: 0.9rem; }
.rr-review input, .rr-review select, .rr-review textarea { font: inherit; font-weight: 400; padding: 0.4rem 0.5rem; border: 1px solid #c5ced8; border-radius: 6px; }
.rr-audit ul { list-style: none; padding: 0; }
.rr-audit li { padding: 0.4rem 0; border-bottom: 1px solid #e8edf2; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
</style>
