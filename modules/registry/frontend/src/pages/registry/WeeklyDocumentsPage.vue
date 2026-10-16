<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError } from '@/lib/notify'
import DocumentList from './DocumentList.vue'

interface Option { id: number; label: string }

const list = ref<{ reload: () => Promise<void> } | null>(null)
const range = ref('')
const total = ref<number | null>(null)
const formOpen = ref(false)
const busy = ref(false)
const assignedRef = ref('')
const copied = ref(false)
const divisions = ref<Option[]>([])
const types = ref<Option[]>([])
const statuses = ref<Option[]>([])

const today = new Date().toISOString().slice(0, 10)
const form = reactive({
  division_id: 0,
  document_type_id: 0,
  status_id: 0,
  addressed_to: '',
  document_date: today,
  description: '',
  signed_by: '',
  comments: '',
})

function onLoaded(meta: { total: number; week_start: string; week_end: string }) {
  total.value = meta.total
  range.value = `${meta.week_start} – ${meta.week_end}`
}

function resetForm() {
  form.addressed_to = ''
  form.document_date = today
  form.description = ''
  form.signed_by = ''
  form.comments = ''
}

async function loadLookups() {
  const { data } = await api.get<{
    data: {
      divisions: Array<{ division_id: number; division: string; abbrev: string }>
      document_types: Array<{ document_type_id: number; document_type: string }>
      statuses: Array<{ status_id: number; status: string }>
    }
  }>('/api/v1/registry/settings')
  divisions.value = data.data.divisions.map((row) => ({
    id: row.division_id,
    label: row.abbrev ? `${row.abbrev} — ${row.division}` : row.division,
  }))
  types.value = data.data.document_types.map((row) => ({ id: row.document_type_id, label: row.document_type }))
  statuses.value = data.data.statuses.map((row) => ({ id: row.status_id, label: row.status }))
  form.division_id = divisions.value[0]?.id ?? 0
  form.document_type_id = types.value[0]?.id ?? 0
  form.status_id = statuses.value[0]?.id ?? 0
}

async function save() {
  busy.value = true
  try {
    const { data } = await api.post<{ data: { ref_no: string } }>('/api/v1/registry/documents', { ...form })
    assignedRef.value = data.data.ref_no
    copied.value = false
    formOpen.value = false
    resetForm()
    await list.value?.reload()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not record the document.'))
  } finally {
    busy.value = false
  }
}

async function copyRef() {
  try {
    await navigator.clipboard.writeText(assignedRef.value)
  } catch {
    const input = document.createElement('input')
    input.value = assignedRef.value
    document.body.appendChild(input)
    input.select()
    document.execCommand('copy')
    input.remove()
  }
  copied.value = true
}

function closeRef() {
  assignedRef.value = ''
  copied.value = false
}

onMounted(() => {
  void loadLookups().catch((e) => notifyError(apiErrorMessage(e, 'Could not load the document form.')))
})
</script>

<template>
  <div>
    <CbpPageHeading title="Weekly Documents" back-to="/dashboard" back-label="← Dashboard">
      <template #lede>
        Documents recorded this week<span v-if="range"> ({{ range }})</span><span v-if="total != null"> · {{ total.toLocaleString() }}</span>.
      </template>
    </CbpPageHeading>

    <div class="toolbar">
      <button type="button" class="add-btn" @click="formOpen = !formOpen">
        {{ formOpen ? 'Close form' : 'Add document' }}
      </button>
    </div>

    <form v-if="formOpen" class="doc-form" @submit.prevent="save">
      <h2>New document</h2>
      <p class="hint">It is recorded today and appears in this week’s list. The reference number is assigned when you save.</p>
      <div class="grid">
        <label>
          <span>Division <em class="req">*</em></span>
          <select v-model.number="form.division_id" required>
            <option v-for="row in divisions" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Document type <em class="req">*</em></span>
          <select v-model.number="form.document_type_id" required>
            <option v-for="row in types" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Status <em class="req">*</em></span>
          <select v-model.number="form.status_id" required>
            <option v-for="row in statuses" :key="row.id" :value="row.id">{{ row.label }}</option>
          </select>
        </label>
        <label>
          <span>Document date <em class="req">*</em></span>
          <input v-model="form.document_date" type="date" required />
        </label>
        <label>
          <span>Addressed to <em class="req">*</em></span>
          <input v-model="form.addressed_to" required maxlength="100" />
        </label>
        <label>
          <span>Signed by <em class="req">*</em></span>
          <input v-model="form.signed_by" required maxlength="100" />
        </label>
        <label class="wide">
          <span>Description <em class="req">*</em></span>
          <textarea v-model="form.description" required rows="3" />
        </label>
        <label class="wide">
          <span>Comments</span>
          <textarea v-model="form.comments" rows="2" />
        </label>
      </div>
      <div class="actions">
        <button type="button" @click="formOpen = false">Cancel</button>
        <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Saving…' : 'Save document' }}</button>
      </div>
    </form>

    <DocumentList ref="list" scope="week" @loaded="onLoaded" />

    <div v-if="assignedRef" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="ref-title">
      <div class="modal">
        <h2 id="ref-title">Reference assigned</h2>
        <p class="ref">{{ assignedRef }}</p>
        <p class="hint">Copy it if you need to share or file the document. You can close this without copying.</p>
        <div class="actions">
          <button type="button" @click="closeRef">Close</button>
          <button type="button" class="primary" @click="copyRef">{{ copied ? 'Copied' : 'Copy reference' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.toolbar { margin: 0 0 1rem; }
.add-btn, .actions button {
  border: 1px solid #0d7a3a; border-radius: 8px; background: #0d7a3a; color: #fff;
  padding: 0.5rem 0.9rem; font: inherit; font-weight: 700; cursor: pointer;
}
.doc-form {
  background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.1rem; margin-bottom: 1rem;
}
.doc-form h2 { margin: 0; font-size: 1rem; }
.hint { margin: 0.35rem 0 0.9rem; color: #64748b; font-size: 0.85rem; }
.grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem; }
.wide { grid-column: 1 / -1; }
label { display: flex; flex-direction: column; gap: 0.3rem; font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
input, select, textarea {
  border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 0.65rem; font: inherit; font-weight: 500;
  color: #0f172a; text-transform: none; letter-spacing: 0; background: #fff;
}
.actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.9rem; }
.actions button { background: #fff; color: #0d7a3a; }
.actions .primary { background: #0d7a3a; color: #fff; }
.actions button:disabled { opacity: 0.6; cursor: default; }
.req { color: #dc2626; font-style: normal; }
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center;
  justify-content: center; z-index: 40; padding: 1rem;
}
.modal { width: min(28rem, 100%); background: #fff; border-radius: 12px; padding: 1.2rem 1.25rem; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25); }
.modal h2 { margin: 0; font-size: 1.05rem; }
.ref { margin: 0.8rem 0 0.35rem; font-size: 1.25rem; font-weight: 800; color: #0d7a3a; word-break: break-all; }
@media (max-width: 720px) { .grid { grid-template-columns: 1fr; } }
</style>
