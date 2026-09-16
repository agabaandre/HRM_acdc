<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { fetchLookups, type RiskLookups } from '@/lib/riskApi'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const lookups = ref<RiskLookups | null>(null)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    lookups.value = await fetchLookups()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load reference data')
  }
})
</script>

<template>
  <div class="rr-page">
    <h1>Reference &amp; methodology</h1>
    <p class="rr-lead">
      How Africa CDC scores inherent and residual risk. Use these scales when capturing or reviewing register entries.
    </p>

    <section class="rr-card">
      <h2>Residual risk formula</h2>
      <ul>
        <li>Inherent score = Likelihood × Impact (1–25)</li>
        <li>When mitigation effectiveness is assessed: Residual L = max(1, L − reduction); Residual I = max(1, I − ⌊reduction / 2⌋)</li>
        <li>Until effectiveness is assessed (Not Assessed), residual equals inherent</li>
        <li>Bands: Low 1–4 · Medium 5–9 · High 10–15 · Critical 16–25</li>
      </ul>
    </section>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <template v-if="lookups">
      <section class="rr-card">
        <h2>Likelihood</h2>
        <table><thead><tr><th>Label</th><th>Score</th></tr></thead>
          <tbody><tr v-for="r in lookups.likelihoods" :key="r.id"><td>{{ r.label }}</td><td>{{ r.score }}</td></tr></tbody>
        </table>
      </section>
      <section class="rr-card">
        <h2>Impact</h2>
        <table><thead><tr><th>Label</th><th>Score</th></tr></thead>
          <tbody><tr v-for="r in lookups.impacts" :key="r.id"><td>{{ r.label }}</td><td>{{ r.score }}</td></tr></tbody>
        </table>
      </section>
      <section class="rr-card">
        <h2>Mitigation effectiveness</h2>
        <table><thead><tr><th>Name</th><th>L reduction</th><th>Assessed</th></tr></thead>
          <tbody>
            <tr v-for="r in lookups.mitigation_effectiveness" :key="r.id">
              <td>{{ r.name }}</td><td>{{ r.likelihood_reduction }}</td><td>{{ r.is_assessed ? 'Yes' : 'No' }}</td>
            </tr>
          </tbody>
        </table>
      </section>
      <section class="rr-card">
        <h2>Rating bands</h2>
        <table><thead><tr><th>Rating</th><th>Min</th><th>Max</th></tr></thead>
          <tbody><tr v-for="r in lookups.rating_bands" :key="r.id"><td>{{ r.rating }}</td><td>{{ r.min_score }}</td><td>{{ r.max_score }}</td></tr></tbody>
        </table>
      </section>
      <section class="rr-card">
        <h2>Enterprise themes</h2>
        <ol><li v-for="r in lookups.enterprise_themes" :key="r.id">{{ r.name }}</li></ol>
      </section>
      <section class="rr-card">
        <h2>Risk types</h2>
        <ul><li v-for="r in lookups.risk_types" :key="r.id">{{ r.name }}</li></ul>
      </section>
      <section class="rr-card">
        <h2>Statuses</h2>
        <ul><li v-for="r in lookups.statuses" :key="r.id">{{ r.name }}</li></ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
.rr-lead { color: #445; max-width: 40rem; }
.rr-card { background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem 1.1rem; margin: 1rem 0; }
.rr-card table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
.rr-card th, .rr-card td { text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid #eef2f6; }
.rr-error { color: #b42318; }
</style>
