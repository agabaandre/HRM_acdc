<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import PortalHighchart from '@/components/molecules/PortalHighchart.vue'

type Dash = {
  total: number
  unmatched_bu: number
  by_rating: Record<string, number>
  by_type: Record<string, number>
  by_bu: Record<string, number>
  by_status: Record<string, number>
  by_effectiveness: Record<string, number>
  top_residual: Array<{ id: number; name: string; residual_score: number; residual_rating: string | null }>
  heat: Array<{ inherent_likelihood: number; inherent_impact: number; c: number }>
}

const data = ref<Dash | null>(null)
const error = ref<string | null>(null)
const loading = ref(true)

function seriesFromMap(map: Record<string, number>) {
  return Object.entries(map || {}).map(([name, y]) => ({ name, y: Number(y) }))
}

const ratingSeries = computed(() => [{ name: 'Risks', data: seriesFromMap(data.value?.by_rating || {}) }])
const typeSeries = computed(() => [{ name: 'Risks', data: seriesFromMap(data.value?.by_type || {}) }])
const buCategories = computed(() => Object.keys(data.value?.by_bu || {}))
const buSeries = computed(() => [
  { name: 'Risks', data: Object.values(data.value?.by_bu || {}).map((n) => Number(n)) },
])
const statusSeries = computed(() => [{ name: 'Risks', data: seriesFromMap(data.value?.by_status || {}) }])
const effSeries = computed(() => [{ name: 'Risks', data: seriesFromMap(data.value?.by_effectiveness || {}) }])

onMounted(async () => {
  try {
    const res = await api.get<Dash>('/api/v1/dashboard/summary')
    data.value = res.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load dashboard')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rr-dash">
    <header>
      <h1>Risk dashboard</h1>
      <p v-if="data" class="rr-muted">{{ data.total }} risks · {{ data.unmatched_bu }} unmatched business units</p>
    </header>
    <p v-if="loading">Loading…</p>
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else-if="data">
      <div class="rr-grid">
        <PortalHighchart title="By residual rating" type="pie" :series="ratingSeries" :height="280" />
        <PortalHighchart title="By risk type" type="pie" :series="typeSeries" :height="280" />
        <PortalHighchart title="By business unit" type="bar" :categories="buCategories" :series="buSeries" :height="320" />
        <PortalHighchart title="By status" type="column" :series="statusSeries" :height="280" />
        <PortalHighchart title="Mitigation effectiveness" type="column" :series="effSeries" :height="280" />
      </div>
      <section class="rr-top">
        <h2>Top 10 residual scores</h2>
        <ol>
          <li v-for="r in data.top_residual" :key="r.id">
            {{ r.residual_score }} {{ r.residual_rating }} — {{ r.name }}
          </li>
        </ol>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-dash { width: 100%; max-width: none; margin: 0; padding: 1.5rem 0; }
.rr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1rem; }
.rr-top { margin-top: 1.5rem; background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
</style>
