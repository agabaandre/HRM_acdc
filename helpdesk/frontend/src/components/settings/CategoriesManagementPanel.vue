<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'

interface CategoryRow {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
}

const rows = ref<CategoryRow[]>([])
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const busyId = ref<number | null>(null)

const draft = reactive({
  name: '',
  slug: '',
  sort_order: 0,
  is_active: true,
})

async function load() {
  err.value = null
  try {
    const { data } = await api.get<{ data: CategoryRow[] }>('/api/v1/admin/categories')
    rows.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load categories.')
  }
}

async function save(row: CategoryRow) {
  busyId.value = row.id
  err.value = null
  ok.value = null
  try {
    await api.put(`/api/v1/admin/categories/${row.id}`, {
      name: row.name,
      slug: row.slug,
      sort_order: row.sort_order,
      is_active: row.is_active,
    })
    ok.value = `Updated “${row.name}”.`
    await load()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed')
  } finally {
    busyId.value = null
  }
}

async function createCategory() {
  if (!draft.name.trim()) {
    err.value = 'Name is required.'
    return
  }
  busyId.value = -1
  err.value = null
  ok.value = null
  try {
    await api.post('/api/v1/admin/categories', {
      name: draft.name.trim(),
      slug: draft.slug.trim() || undefined,
      sort_order: draft.sort_order,
      is_active: draft.is_active,
    })
    ok.value = 'Category created.'
    draft.name = ''
    draft.slug = ''
    draft.sort_order = 0
    draft.is_active = true
    await load()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Create failed')
  } finally {
    busyId.value = null
  }
}

async function remove(row: CategoryRow) {
  if (!window.confirm(`Delete category “${row.name}”? This fails if tickets still use it.`)) {
    return
  }
  busyId.value = row.id
  err.value = null
  ok.value = null
  try {
    await api.delete(`/api/v1/admin/categories/${row.id}`)
    ok.value = 'Category deleted.'
    await load()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Delete failed')
  } finally {
    busyId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel" aria-labelledby="cat-heading">
    <h2 id="cat-heading">Issue categories</h2>
    <p class="hint">Used on tickets and agent routing. Inactive categories stay hidden from new requests where the public list filters active only.</p>
    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>

    <div class="card new-card">
      <h3>Add category</h3>
      <div class="grid">
        <label>Name
          <input v-model="draft.name" type="text" maxlength="191" />
        </label>
        <label>Slug (optional)
          <input v-model="draft.slug" type="text" maxlength="191" placeholder="auto from name" />
        </label>
        <label>Sort order
          <input v-model.number="draft.sort_order" type="number" min="0" />
        </label>
        <label class="row">
          <input v-model="draft.is_active" type="checkbox" />
          Active
        </label>
      </div>
      <button type="button" class="primary" :disabled="busyId === -1" @click="createCategory()">Create</button>
    </div>

    <div v-if="rows.length" class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Order</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.id">
            <td><input v-model="r.name" class="cell" type="text" /></td>
            <td><input v-model="r.slug" class="cell" type="text" /></td>
            <td><input v-model.number="r.sort_order" class="cell narrow" type="number" min="0" /></td>
            <td><input v-model="r.is_active" type="checkbox" /></td>
            <td class="actions">
              <button type="button" class="btn" :disabled="busyId === r.id" @click="save(r)">Save</button>
              <button type="button" class="btn danger" :disabled="busyId === r.id" @click="remove(r)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-else class="muted">No categories loaded.</p>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
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
  grid-template-columns: 1fr 1fr;
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
input.cell {
  width: 100%;
  min-width: 0;
}
input.narrow {
  width: 5rem;
}
input[type='text'],
input[type='number'] {
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
.actions {
  white-space: nowrap;
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
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
.btn.danger {
  border-color: #fecaca;
  color: #991b1b;
  background: #fef2f2;
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
