<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { fetchLookups, type RiskLookups } from '@/lib/riskApi'

const lookups = ref<RiskLookups | null>(null)
const importEnabled = ref(true)
const canManage = ref(false)
const error = ref<string | null>(null)
const saving = ref(false)
const message = ref('')

async function load() {
  error.value = null
  try {
    const [{ data: settings }, lookupData] = await Promise.all([
      api.get<{ data: { import_enabled?: string }; can_manage: boolean }>('/api/v1/settings'),
      fetchLookups(),
    ])
    importEnabled.value = settings.data.import_enabled !== '0'
    canManage.value = settings.can_manage
    lookups.value = lookupData
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load settings')
  }
}

async function saveImportToggle() {
  if (!canManage.value) return
  saving.value = true
  message.value = ''
  try {
    await api.put('/api/v1/settings', { import_enabled: importEnabled.value })
    message.value = 'Saved.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <h1>Risk settings</h1>
    <p class="rr-muted">Admin and Internal Oversight can change non-critical lookup lists and disable one-time Excel import.</p>
    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>

    <section class="rr-card">
      <h2>Excel import</h2>
      <label class="rr-toggle">
        <input v-model="importEnabled" type="checkbox" :disabled="!canManage || saving" @change="saveImportToggle" />
        Import module enabled
      </label>
      <p class="rr-muted">When off, the Import page is hidden and uploads are blocked.</p>
    </section>

    <section v-if="lookups" class="rr-card">
      <h2>Lookup lists (Sheet 3)</h2>
      <p class="rr-muted">Read-only summary. Use API <code>POST /api/v1/settings/lookups/{table}</code> or extend this UI for field edits.</p>
      <details v-for="[title, rows] in [
        ['Likelihoods', lookups.likelihoods],
        ['Impacts', lookups.impacts],
        ['Risk types', lookups.risk_types],
        ['Themes', lookups.enterprise_themes],
        ['Statuses', lookups.statuses],
        ['Effectiveness', lookups.mitigation_effectiveness],
        ['Bands', lookups.rating_bands],
      ]" :key="title">
        <summary>{{ title }} ({{ rows.length }})</summary>
        <ul>
          <li v-for="(row, idx) in rows" :key="idx">{{ JSON.stringify(row) }}</li>
        </ul>
      </details>
    </section>
  </div>
</template>

<style scoped>
.rr-page { width: 100%; max-width: none; margin: 0; padding: 1.5rem 0; }
.rr-card { background: #fff; border: 1px solid #d8dee6; border-radius: 8px; padding: 1rem; margin: 1rem 0; }
.rr-toggle { display: flex; gap: 0.5rem; align-items: center; font-weight: 600; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
.rr-ok { color: #0b6e4f; }
</style>
