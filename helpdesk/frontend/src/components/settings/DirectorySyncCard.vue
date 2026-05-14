<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

const busy = ref(false)
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const last = ref<{ divisions: number; directorates: number; staff_rows: number; cache_ttl_seconds: number } | null>(null)
/** null = health check failed or unknown */
const staffShareConfigured = ref<boolean | null>(null)

onMounted(async () => {
  try {
    const { data } = await api.get<{ staff_share_api?: { configured: boolean } }>('/api/v1/health')
    staffShareConfigured.value = data.staff_share_api?.configured ?? null
  } catch {
    staffShareConfigured.value = null
  }
})

async function syncNow() {
  busy.value = true
  err.value = null
  ok.value = null
  last.value = null
  try {
    const { data } = await api.post<{ data: { divisions: number; directorates: number; staff_rows: number; cache_ttl_seconds: number } }>(
      '/api/v1/admin/reference-sync',
    )
    last.value = data.data
    ok.value = `Synced: ${data.data.divisions} divisions, ${data.data.directorates} directorates, ${data.data.staff_rows} staff rows cached (TTL ${data.data.cache_ttl_seconds}s).`
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Sync failed.')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section class="sync" aria-labelledby="sync-heading">
    <h3 id="sync-heading">Staff directory sync</h3>
    <p class="hint">
      Pulls <strong>divisions</strong>, <strong>directorates</strong>, and <strong>staff</strong> from the Staff Share API (same contract as APM) into the helpdesk server
      cache so agents can pick requesters. Env: <code>BASE_URL</code>, <code>STAFF_API_USERNAME</code> (Staff <strong>user login email</strong> authorised for Share API — same as APM), <code>STAFF_API_PASSWORD</code> (that user’s password), optional <code>STAFF_API_TOKEN</code>, or <code>HELPDESK_STAFF_API_*</code> overrides. See <code>documentation/INTEGRATION.md</code> § Staff / Directorate / Division APIs.
    </p>
    <p class="hint subtle">
      This job clears the short-lived cache then refetches; it does not write to the Staff database.
    </p>
    <p v-if="staffShareConfigured === false" class="warn" role="status">
      Staff Share API is <strong>not configured</strong> on the Helpdesk API. Add to <code>helpdesk/backend/.env</code> the same
      <code>BASE_URL</code>, <code>STAFF_API_USERNAME</code>, and <code>STAFF_API_PASSWORD</code> as in <code>apm/.env</code> (or set
      <code>HELPDESK_STAFF_API_*</code> overrides), then run <code>php artisan config:clear</code> if you use config caching, and restart
      <code>php artisan serve</code>.
    </p>
    <button type="button" class="primary" :disabled="busy || staffShareConfigured === false" @click="syncNow()">{{ busy ? 'Syncing…' : 'Run directory sync now' }}</button>
    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>
  </section>
</template>

<style scoped>
.sync {
  margin-bottom: 1.75rem;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid #e2e8f0;
}
h3 {
  margin: 0 0 0.5rem;
  font-size: 1rem;
  color: #2c3e50;
}
.hint {
  font-size: 0.86rem;
  color: #475569;
  line-height: 1.55;
  margin: 0 0 0.65rem;
}
.hint a {
  color: #119a48;
  font-weight: 600;
}
.subtle {
  font-size: 0.8rem;
  color: #64748b;
}
.warn {
  font-size: 0.86rem;
  line-height: 1.55;
  color: #92400e;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: 8px;
  padding: 0.65rem 0.75rem;
  margin: 0 0 0.75rem;
}
.warn code {
  font-size: 0.82em;
}
.primary {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, #119a48, #0d7a3a);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.err {
  color: #b91c1c;
  font-weight: 600;
  margin-top: 0.65rem;
}
.ok {
  color: #166534;
  font-weight: 600;
  margin-top: 0.65rem;
}
code {
  font-size: 0.82em;
}
</style>
