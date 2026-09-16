<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { fetchRisks, type RiskRow } from '@/lib/riskApi'
import { getStoredToken } from '@/lib/api'

const router = useRouter()
const rows = ref<RiskRow[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  if (!getStoredToken()) {
    error.value = 'Sign in via Staff Portal CBP Modules → Risk Register.'
    loading.value = false
    return
  }
  try {
    rows.value = await fetchRisks()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load risks')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>Risk Register</h1>
        <p class="rr-page__sub">Enterprise risks by division — imported and in-app entries</p>
      </div>
      <div class="rr-page__actions">
        <button type="button" class="rr-btn rr-btn--primary" @click="router.push({ name: 'risk-new' })">New risk</button>
      </div>
    </header>

    <p v-if="loading" class="rr-muted">Loading…</p>
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <div v-else class="rr-table-wrap">
      <table class="rr-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>BU</th>
            <th>Inherent</th>
            <th>Residual</th>
            <th>Movement</th>
            <th>State</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="r in rows"
            :key="r.id"
            class="rr-table__row"
            @click="router.push({ name: 'risk-show', params: { id: r.id } })"
          >
            <td>{{ r.id }}</td>
            <td>{{ r.name }}</td>
            <td>{{ r.source_business_unit || r.unmapped_business_unit || r.division_id || '—' }}</td>
            <td>{{ r.inherent_score }} {{ r.inherent_rating }}</td>
            <td>{{ r.residual_score }} {{ r.residual_rating }}</td>
            <td>{{ r.risk_movement || '—' }}</td>
            <td>{{ r.workflow_state }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="rows.length === 0" class="rr-muted">No risks yet. Run import or create one.</p>
    </div>
  </div>
</template>

<style scoped>
.rr-page { max-width: 1200px; margin: 0 auto; padding: 1.5rem 1.25rem 3rem; }
.rr-page__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1.25rem; }
.rr-page__header h1 { margin: 0; font-size: 1.75rem; font-weight: 700; color: #1a2b3c; }
.rr-page__sub { margin: 0.35rem 0 0; color: #5a6a7a; }
.rr-btn { border: 0; border-radius: 6px; padding: 0.55rem 1rem; font-weight: 600; cursor: pointer; }
.rr-btn--primary { background: #0b6e4f; color: #fff; }
.rr-table-wrap { overflow: auto; border: 1px solid #d8dee6; border-radius: 8px; background: #fff; }
.rr-table { width: 100%; border-collapse: collapse; font-size: 0.92rem; }
.rr-table th, .rr-table td { text-align: left; padding: 0.65rem 0.75rem; border-bottom: 1px solid #e8edf2; }
.rr-table th { background: #f4f7fa; font-weight: 600; color: #334; }
.rr-table__row { cursor: pointer; }
.rr-table__row:hover { background: #f0faf5; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
</style>
