<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'

type ApprovalRow = {
  id: number
  risk_id: number
  risk_name: string
  role: string
  step_order: number
  status: string
}

const rows = ref<ApprovalRow[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const feedbackFor = ref<number | null>(null)
const feedbackMsg = ref('')
const recipients = ref<Array<{ staff_id: number; role: string; level: number }>>([])
const selectedRecipients = ref<number[]>([])

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{ data: ApprovalRow[] }>('/api/v1/my-approvals')
    rows.value = data.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load approvals')
  } finally {
    loading.value = false
  }
}

async function approve(id: number) {
  await api.post(`/api/v1/approvals/${id}/approve`)
  await load()
}

async function openFeedback(id: number) {
  feedbackFor.value = id
  feedbackMsg.value = ''
  selectedRecipients.value = []
  const { data } = await api.get<{ data: Array<{ staff_id: number; role: string; level: number }> }>(
    `/api/v1/approvals/${id}/eligible-recipients`
  )
  recipients.value = data.data
}

async function sendFeedback() {
  if (feedbackFor.value == null) return
  await api.post(`/api/v1/approvals/${feedbackFor.value}/request-feedback`, {
    recipient_staff_ids: selectedRecipients.value,
    message: feedbackMsg.value,
  })
  feedbackFor.value = null
  await load()
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <h1>My approvals</h1>
    <p v-if="loading" class="rr-muted">Loading…</p>
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <ul v-else class="rr-list">
      <li v-for="r in rows" :key="r.id">
        <div>
          <strong>{{ r.risk_name }}</strong>
          <span class="rr-muted"> · {{ r.role }} (step {{ r.step_order }})</span>
        </div>
        <div class="rr-actions">
          <button type="button" class="rr-btn rr-btn--primary" @click="approve(r.id)">Approve</button>
          <button type="button" class="rr-btn" @click="openFeedback(r.id)">Request feedback</button>
        </div>
      </li>
      <li v-if="rows.length === 0" class="rr-muted">No pending approvals.</li>
    </ul>

    <div v-if="feedbackFor != null" class="rr-modal">
      <h2>Request feedback</h2>
      <label>Recipients (lower levels only)
        <select v-model="selectedRecipients" multiple size="4">
          <option v-for="p in recipients" :key="p.staff_id" :value="p.staff_id">
            {{ p.role }} — staff {{ p.staff_id }}
          </option>
        </select>
      </label>
      <label>Message<textarea v-model="feedbackMsg" rows="3" /></label>
      <div class="rr-actions">
        <button type="button" class="rr-btn rr-btn--primary" @click="sendFeedback">Send</button>
        <button type="button" class="rr-btn" @click="feedbackFor = null">Cancel</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.rr-page { max-width: 800px; margin: 0 auto; padding: 1.5rem; }
.rr-list { list-style: none; padding: 0; }
.rr-list li { display: flex; justify-content: space-between; gap: 1rem; padding: 0.85rem 0; border-bottom: 1px solid #e8edf2; }
.rr-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.rr-btn { border: 1px solid #c5ced8; background: #fff; border-radius: 6px; padding: 0.4rem 0.75rem; cursor: pointer; font-weight: 600; }
.rr-btn--primary { background: #0b6e4f; color: #fff; border-color: #0b6e4f; }
.rr-modal { margin-top: 1.5rem; padding: 1rem; border: 1px solid #d8dee6; border-radius: 8px; background: #fff; display: grid; gap: 0.75rem; }
.rr-modal label { display: grid; gap: 0.35rem; font-weight: 600; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
</style>
