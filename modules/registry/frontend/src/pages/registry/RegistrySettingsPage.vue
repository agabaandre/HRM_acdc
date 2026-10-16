<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { notifyError, notifySuccess } from '@/lib/notify'

interface DocType { document_type_id: number; document_type: string; ABV: string }
interface StatusRow { status_id: number; status: string }
interface Division { division_id: number; division: string; abbrev: string }

const tab = ref<'types' | 'statuses' | 'divisions'>('types')
const types = ref<DocType[]>([])
const statuses = ref<StatusRow[]>([])
const divisions = ref<Division[]>([])
const busy = ref(false)
const forms = reactive({
  document_type: '',
  ABV: '',
  status: '',
  division: '',
  abbrev: '',
})

async function load() {
  const { data } = await api.get<{ data: { document_types: DocType[]; statuses: StatusRow[]; divisions: Division[] } }>('/api/v1/registry/settings')
  types.value = data.data.document_types
  statuses.value = data.data.statuses
  divisions.value = data.data.divisions
}

async function addType() {
  busy.value = true
  try {
    await api.post('/api/v1/registry/settings/document-types', { document_type: forms.document_type, ABV: forms.ABV })
    forms.document_type = ''
    forms.ABV = ''
    notifySuccess('Document type added.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not add the document type.'))
  } finally {
    busy.value = false
  }
}

async function addStatus() {
  busy.value = true
  try {
    await api.post('/api/v1/registry/settings/statuses', { status: forms.status })
    forms.status = ''
    notifySuccess('Status added.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not add the status.'))
  } finally {
    busy.value = false
  }
}

async function addDivision() {
  busy.value = true
  try {
    await api.post('/api/v1/registry/settings/divisions', { division: forms.division, abbrev: forms.abbrev })
    forms.division = ''
    forms.abbrev = ''
    notifySuccess('Division added.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not add the division.'))
  } finally {
    busy.value = false
  }
}

async function saveType(row: DocType) {
  try {
    await api.put(`/api/v1/registry/settings/document-types/${row.document_type_id}`, { document_type: row.document_type, ABV: row.ABV })
    notifySuccess('Document type saved.')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  }
}

async function saveStatus(row: StatusRow) {
  try {
    await api.put(`/api/v1/registry/settings/statuses/${row.status_id}`, { status: row.status })
    notifySuccess('Status saved.')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  }
}

async function saveDivision(row: Division) {
  try {
    await api.put(`/api/v1/registry/settings/divisions/${row.division_id}`, { division: row.division, abbrev: row.abbrev })
    notifySuccess('Division saved.')
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Save failed.'))
  }
}

async function remove(table: string, id: number) {
  if (!window.confirm('Remove this value?')) return
  try {
    await api.delete(`/api/v1/registry/settings/${table}/${id}`)
    notifySuccess('Removed.')
    await load()
  } catch (e) {
    notifyError(apiErrorMessage(e, 'Could not remove it. It may still be used by documents.'))
  }
}

onMounted(() => void load().catch((e) => notifyError(apiErrorMessage(e, 'Failed to load settings.'))))
</script>

<template>
  <div>
    <CbpPageHeading title="Settings" back-to="/dashboard" back-label="← Dashboard">
      <template #lede>
        Document types, dispatch statuses, and divisions used when registry documents are recorded.
      </template>
    </CbpPageHeading>

    <section class="tools-page">
      <div class="tabs" role="tablist">
        <button type="button" class="tab" :class="{ active: tab === 'types' }" @click="tab = 'types'">Document types</button>
        <button type="button" class="tab" :class="{ active: tab === 'statuses' }" @click="tab = 'statuses'">Statuses</button>
        <button type="button" class="tab" :class="{ active: tab === 'divisions' }" @click="tab = 'divisions'">Divisions</button>
      </div>

      <div v-if="tab === 'types'" class="panel">
        <form class="add-row" @submit.prevent="addType">
          <label>Document type <em class="req">*</em><input v-model="forms.document_type" required placeholder="Document type" /></label>
          <label>Code<input v-model="forms.ABV" maxlength="5" placeholder="Code" /></label>
          <button type="submit" :disabled="busy">Add</button>
        </form>
        <div v-for="row in types" :key="row.document_type_id" class="edit-row">
          <input v-model="row.document_type" />
          <input v-model="row.ABV" maxlength="5" />
          <button type="button" @click="saveType(row)">Save</button>
          <button type="button" class="danger" @click="remove('document-types', row.document_type_id)">Remove</button>
        </div>
      </div>

      <div v-else-if="tab === 'statuses'" class="panel">
        <form class="add-row" @submit.prevent="addStatus">
          <label>Status <em class="req">*</em><input v-model="forms.status" required placeholder="Status" /></label>
          <button type="submit" :disabled="busy">Add</button>
        </form>
        <div v-for="row in statuses" :key="row.status_id" class="edit-row">
          <input v-model="row.status" />
          <button type="button" @click="saveStatus(row)">Save</button>
          <button type="button" class="danger" @click="remove('statuses', row.status_id)">Remove</button>
        </div>
      </div>

      <div v-else class="panel">
        <form class="add-row" @submit.prevent="addDivision">
          <label>Division <em class="req">*</em><input v-model="forms.division" required placeholder="Division" /></label>
          <label>Abbrev<input v-model="forms.abbrev" maxlength="10" placeholder="Abbrev" /></label>
          <button type="submit" :disabled="busy">Add</button>
        </form>
        <div v-for="row in divisions" :key="row.division_id" class="edit-row">
          <input v-model="row.division" />
          <input v-model="row.abbrev" maxlength="10" />
          <button type="button" @click="saveDivision(row)">Save</button>
          <button type="button" class="danger" @click="remove('divisions', row.division_id)">Remove</button>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.tools-page { display: flex; flex-direction: column; gap: 1rem; max-width: 860px; }
.tabs { display: flex; gap: 0.35rem; border-bottom: 1px solid #e2e8f0; }
.tab { border: 0; background: transparent; padding: 0.55rem 0.9rem; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 2px solid transparent; }
.tab.active { color: #0d7a3a; border-bottom-color: #0d7a3a; }
.panel { display: flex; flex-direction: column; gap: 0.55rem; }
.add-row, .edit-row { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
.add-row label { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; min-width: 8rem; font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
.req { color: #dc2626; font-style: normal; text-transform: none; }
input { flex: 1; min-width: 8rem; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.45rem 0.65rem; font: inherit; }
button { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 0.45rem 0.7rem; font-weight: 700; color: #0d7a3a; cursor: pointer; }
button.danger { color: #b91c1c; }
</style>
