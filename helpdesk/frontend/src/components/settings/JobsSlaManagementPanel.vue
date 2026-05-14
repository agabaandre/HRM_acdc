<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import DirectorySyncCard from './DirectorySyncCard.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface CatOpt {
  id: number
  name: string
}

interface SlaRow {
  id: number
  name: string
  category_id: number
  category: { id: number; name: string; slug: string } | null
  response_minutes: number
  resolution_minutes: number
  is_active: boolean
}

const rules = ref<SlaRow[]>([])
const categories = ref<CatOpt[]>([])
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const busyId = ref<number | null>(null)

const draft = reactive({
  name: '',
  category_id: 0,
  response_minutes: 240,
  resolution_minutes: 2880,
  is_active: true,
})

async function loadCategories() {
  try {
    const { data } = await api.get<{ data: CatOpt[] }>('/api/v1/categories')
    categories.value = Array.isArray(data.data) ? data.data : []
  } catch {
    categories.value = []
  }
}

async function loadRules() {
  err.value = null
  try {
    const { data } = await api.get<{ data: SlaRow[] }>('/api/v1/admin/sla-rules')
    const list = Array.isArray(data.data) ? data.data : []
    rules.value = list.map((r) => ({
      ...r,
      category_id: r.category_id ?? 0,
    }))
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load SLA rules.')
  }
}

async function save(row: SlaRow) {
  busyId.value = row.id
  err.value = null
  ok.value = null
  try {
    await api.put(`/api/v1/admin/sla-rules/${row.id}`, {
      name: row.name,
      category_id: row.category_id && row.category_id > 0 ? row.category_id : null,
      response_minutes: row.response_minutes,
      resolution_minutes: row.resolution_minutes,
      is_active: row.is_active,
    })
    ok.value = `Updated “${row.name}”.`
    await loadRules()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed')
  } finally {
    busyId.value = null
  }
}

async function createRule() {
  if (!draft.name.trim()) {
    err.value = 'Name is required.'
    return
  }
  busyId.value = -1
  err.value = null
  ok.value = null
  try {
    await api.post('/api/v1/admin/sla-rules', {
      name: draft.name.trim(),
      category_id: draft.category_id > 0 ? draft.category_id : null,
      response_minutes: draft.response_minutes,
      resolution_minutes: draft.resolution_minutes,
      is_active: draft.is_active,
    })
    ok.value = 'SLA rule created.'
    draft.name = ''
    draft.category_id = 0
    draft.response_minutes = 240
    draft.resolution_minutes = 2880
    draft.is_active = true
    await loadRules()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Create failed')
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  void loadCategories()
  void loadRules()
})
</script>

<template>
  <section class="panel" aria-labelledby="jobs-heading">
    <h2 id="jobs-heading">Jobs</h2>
    <p class="hint">
      <strong>Directory jobs</strong> refresh Staff-linked reference data. <strong>SLA jobs</strong> define response/resolution targets (optional per category).
    </p>

    <DirectorySyncCard />

    <h3 class="subhead">SLA rules &amp; targets</h3>
    <p class="hint narrow">
      Named SLA targets (response and resolution minutes) optionally scoped to a category. Ticket due dates can use these rules as the product evolves.
    </p>
    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>

    <div class="card new-card">
      <h3>Add SLA rule</h3>
      <div class="grid">
        <label>Name
          <input v-model="draft.name" type="text" maxlength="191" placeholder="e.g. Email — standard" />
        </label>
        <label>Category (optional)
          <select v-model.number="draft.category_id">
            <option :value="0">All categories</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label>Response (minutes)
          <input v-model.number="draft.response_minutes" type="number" min="1" />
        </label>
        <label>Resolution (minutes)
          <input v-model.number="draft.resolution_minutes" type="number" min="1" />
        </label>
        <label class="row">
          <input v-model="draft.is_active" type="checkbox" />
          Active
        </label>
      </div>
      <button type="button" class="primary" :disabled="busyId === -1" @click="createRule()">Create rule</button>
    </div>

    <div v-if="rules.length" class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Name</th>
            <th>Category</th>
            <th>Response (m)</th>
            <th>Resolution (m)</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rules" :key="r.id">
            <td><input v-model="r.name" class="cell" type="text" /></td>
            <td>
              <select v-model.number="r.category_id" class="cell">
                <option :value="0">All</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </td>
            <td><input v-model.number="r.response_minutes" class="cell narrow" type="number" min="1" /></td>
            <td><input v-model.number="r.resolution_minutes" class="cell narrow" type="number" min="1" /></td>
            <td><input v-model="r.is_active" type="checkbox" /></td>
            <td>
              <button type="button" class="btn" :disabled="busyId === r.id" @click="save(r)">Save</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="muted">No SLA rules yet — create one above.</p>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
}
.subhead {
  font-size: 1rem;
  margin: 0.5rem 0 0.35rem;
  color: #2c3e50;
}
.hint.narrow {
  margin-top: 0;
}
.panel h3 {
  font-size: 0.95rem;
  margin: 0 0 0.5rem;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.card {
  padding: 1rem 1.15rem;
  border-radius: 14px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-white, #fff);
  margin-bottom: 1rem;
}
.new-card .grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
  margin-bottom: 0.75rem;
}
@media (max-width: 720px) {
  .new-card .grid {
    grid-template-columns: 1fr;
  }
}
label {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: #334155;
}
.row {
  flex-direction: row;
  align-items: center;
  gap: 0.5rem;
}
input.cell,
select.cell {
  width: 100%;
  min-width: 0;
}
input.narrow {
  width: 5.5rem;
}
input[type='text'],
input[type='number'],
select {
  padding: 0.35rem 0.45rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.88rem;
}
.table-wrap {
  overflow-x: auto;
  border-radius: 12px;
  border: 1px solid var(--cdc-line, rgba(12, 26, 18, 0.08));
  background: var(--cdc-white, #fff);
}
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.tbl th,
.tbl td {
  text-align: left;
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}
.btn {
  padding: 0.3rem 0.55rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #f8fafc;
  font-weight: 600;
  cursor: pointer;
  font-size: 0.8rem;
}
.primary {
  padding: 0.45rem 0.9rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, var(--cdc-green, #0d7a3a), var(--cdc-green-deep, #065f2c));
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.err {
  color: #b91c1c;
}
.ok {
  color: #166534;
  font-weight: 600;
}
.muted {
  color: #64748b;
}
</style>
