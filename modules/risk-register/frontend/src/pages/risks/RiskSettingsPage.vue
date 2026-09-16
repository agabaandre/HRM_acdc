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
    error.value = apiErrorMessage(e, 'Could not load lookups')
  }
})
</script>

<template>
  <div class="rr-page">
    <h1>Risk settings (Sheet 3)</h1>
    <p class="rr-muted">Reference scales are seeded from the workbook. Edit via DB / future admin forms; shown read-only here.</p>
    <p v-if="error" class="rr-error">{{ error }}</p>
    <template v-if="lookups">
      <section v-for="[title, rows] in [
        ['Likelihoods', lookups.likelihoods],
        ['Impacts', lookups.impacts],
        ['Risk types', lookups.risk_types],
        ['Enterprise themes', lookups.enterprise_themes],
        ['Statuses', lookups.statuses],
        ['Mitigation effectiveness', lookups.mitigation_effectiveness],
        ['Rating bands', lookups.rating_bands],
      ]" :key="title">
        <h2>{{ title }}</h2>
        <ul>
          <li v-for="(row, idx) in rows" :key="idx">{{ JSON.stringify(row) }}</li>
        </ul>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-page { max-width: 900px; margin: 0 auto; padding: 1.5rem; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
section { margin-top: 1rem; }
ul { font-size: 0.85rem; }
</style>
