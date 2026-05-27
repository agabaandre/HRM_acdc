<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'

interface Cat {
  id: number
  name: string
}

interface KbArticleRow {
  id: number
  category: { id: number; name: string; slug?: string } | null
  category_id: number
  question: string
  answer: string
  sort_order: number
  is_active: boolean
  created_by: { id: number; name: string } | null
  updated_by: { id: number; name: string } | null
  updated_at?: string | null
}

const cats = ref<Cat[]>([])
const rows = ref<KbArticleRow[]>([])
const busy = ref<number | null>(null)
const err = ref<string | null>(null)
const ok = ref<string | null>(null)
const filterCat = ref<number | 0>(0)
const search = ref('')

const create = reactive({
  category_id: 0,
  question: '',
  answer: '',
  sort_order: 0,
  is_active: true,
})

async function loadCats(): Promise<void> {
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
  if (cats.value.length && create.category_id === 0) {
    create.category_id = cats.value[0].id
  }
}

async function loadRows(): Promise<void> {
  err.value = null
  try {
    const { data } = await api.get<{ data: KbArticleRow[] }>('/api/v1/admin/kb/articles')
    rows.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Failed to load articles.')
    rows.value = []
  }
}

async function loadAll(): Promise<void> {
  ok.value = null
  try {
    await loadCats()
  } catch {
    cats.value = []
  }
  await loadRows()
}

const filtered = computed<KbArticleRow[]>(() => {
  const q = search.value.trim().toLowerCase()
  return rows.value.filter((r) => {
    if (filterCat.value && r.category_id !== filterCat.value) {
      return false
    }
    if (q !== '') {
      const hay = `${r.question} ${r.answer}`.toLowerCase()
      if (!hay.includes(q)) {
        return false
      }
    }
    return true
  })
})

async function createArticle(): Promise<void> {
  if (!create.question.trim() || !create.answer.trim() || !create.category_id) {
    err.value = 'Category, question, and answer are required.'
    return
  }
  busy.value = -1
  ok.value = null
  err.value = null
  try {
    await api.post('/api/v1/admin/kb/articles', {
      category_id: create.category_id,
      question: create.question.trim(),
      answer: create.answer,
      sort_order: create.sort_order,
      is_active: create.is_active,
    })
    ok.value = 'Article created.'
    create.question = ''
    create.answer = ''
    create.sort_order = 0
    create.is_active = true
    await loadRows()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Create failed.')
  } finally {
    busy.value = null
  }
}

async function saveRow(row: KbArticleRow): Promise<void> {
  busy.value = row.id
  ok.value = null
  err.value = null
  try {
    await api.put(`/api/v1/admin/kb/articles/${row.id}`, {
      category_id: row.category_id,
      question: row.question,
      answer: row.answer,
      sort_order: row.sort_order,
      is_active: row.is_active,
    })
    ok.value = `Saved “${row.question}”.`
    await loadRows()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    busy.value = null
  }
}

async function removeRow(row: KbArticleRow): Promise<void> {
  if (!window.confirm(`Delete “${row.question}”? This cannot be undone.`)) {
    return
  }
  busy.value = row.id
  ok.value = null
  err.value = null
  try {
    await api.delete(`/api/v1/admin/kb/articles/${row.id}`)
    ok.value = 'Article deleted.'
    await loadRows()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Delete failed.')
  } finally {
    busy.value = null
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <div>
    <CbpPageHeading title="Manage knowledge base" back-to="/" back-label="← Overview">
      <template #lede>
        Publish frequently asked questions for staff to self-serve. Articles are grouped by the
        same categories as <RouterLink to="/settings/categories">issue categories</RouterLink>.
      </template>
    </CbpPageHeading>

    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>

    <section class="cbp-card create-card" aria-labelledby="create-heading">
      <h2 id="create-heading">Add an article</h2>
      <div class="grid">
        <label>
          Category
          <select v-model.number="create.category_id">
            <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </label>
        <label>
          Sort order
          <input v-model.number="create.sort_order" type="number" min="0" />
        </label>
        <label class="row-check">
          <input v-model="create.is_active" type="checkbox" />
          Active (visible on the home knowledge base)
        </label>
      </div>
      <label class="full">
        Question
        <input v-model="create.question" type="text" maxlength="255" placeholder="e.g. How do I reset my password?" />
      </label>
      <label class="full">
        Answer
        <textarea v-model="create.answer" rows="5" placeholder="Plain text or HTML — paste step-by-step instructions, links, etc."></textarea>
      </label>
      <button type="button" class="primary" :disabled="busy === -1" @click="createArticle">
        {{ busy === -1 ? 'Saving…' : 'Add article' }}
      </button>
    </section>

    <section class="cbp-card list-card" aria-labelledby="list-heading">
      <header class="list-head">
        <h2 id="list-heading">All articles</h2>
        <div class="filters">
          <label>
            <span class="sr-only">Filter by category</span>
            <select v-model.number="filterCat">
              <option :value="0">All categories</option>
              <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label>
            <span class="sr-only">Search</span>
            <input v-model="search" type="search" placeholder="Search questions and answers…" />
          </label>
        </div>
      </header>

      <p v-if="filtered.length === 0" class="muted">
        {{ rows.length === 0 ? 'No articles yet — use the form above to publish the first FAQ.' : 'No articles match the current filter.' }}
      </p>
      <ul v-else class="rows">
        <li v-for="r in filtered" :key="r.id" class="row" :class="{ inactive: !r.is_active }">
          <div class="row-grid">
            <label>
              Category
              <select v-model.number="r.category_id">
                <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>
            <label>
              Sort
              <input v-model.number="r.sort_order" type="number" min="0" class="narrow" />
            </label>
            <label class="row-check">
              <input v-model="r.is_active" type="checkbox" />
              Active
            </label>
          </div>
          <label class="full">
            Question
            <input v-model="r.question" type="text" maxlength="255" />
          </label>
          <label class="full">
            Answer
            <textarea v-model="r.answer" rows="4"></textarea>
          </label>
          <div class="row-meta">
            <span v-if="r.updated_by">Updated by {{ r.updated_by.name }}</span>
            <span v-else-if="r.created_by">Created by {{ r.created_by.name }}</span>
            <span v-if="r.updated_at"> · {{ new Date(r.updated_at).toLocaleString() }}</span>
          </div>
          <div class="row-actions">
            <button type="button" class="btn" :disabled="busy === r.id" @click="saveRow(r)">
              {{ busy === r.id ? 'Saving…' : 'Save' }}
            </button>
            <button type="button" class="btn danger" :disabled="busy === r.id" @click="removeRow(r)">
              Delete
            </button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.err {
  margin: 0.75rem 0;
  padding: 0.65rem 0.85rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #991b1b;
  border-radius: 8px;
}
.ok {
  margin: 0.75rem 0;
  padding: 0.65rem 0.85rem;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #166534;
  border-radius: 8px;
}
.create-card,
.list-card {
  padding: 1.1rem;
  margin-top: 1rem;
}
.create-card h2,
.list-card h2 {
  font-size: 1.05rem;
  margin: 0 0 0.85rem;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
label {
  display: flex;
  flex-direction: column;
  font-size: 0.85rem;
  color: #3a4452;
  font-weight: 600;
  gap: 0.3rem;
}
input,
select,
textarea {
  padding: 0.5rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  background: #fff;
  font-family: inherit;
}
textarea {
  resize: vertical;
  min-height: 96px;
}
input:focus,
select:focus,
textarea:focus {
  outline: none;
  border-color: #119a48;
  box-shadow: 0 0 0 3px rgba(17, 154, 72, 0.18);
}
.row-check {
  flex-direction: row;
  align-items: center;
  gap: 0.45rem;
  font-weight: 500;
}
.full {
  margin-top: 0.5rem;
}
.primary {
  margin-top: 0.75rem;
  padding: 0.55rem 1.1rem;
  border-radius: 8px;
  border: 0;
  background: linear-gradient(135deg, #119a48 0%, #0d7a3a 100%);
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.primary:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.list-head {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.85rem;
}
.filters {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}
.row {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.85rem;
  background: #fff;
}
.row.inactive {
  background: #f8fafc;
  border-style: dashed;
}
.row-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.65rem;
  align-items: end;
}
.narrow {
  max-width: 96px;
}
.row-meta {
  margin-top: 0.5rem;
  font-size: 0.78rem;
  color: #6b7280;
}
.row-actions {
  margin-top: 0.65rem;
  display: flex;
  gap: 0.5rem;
}
.btn {
  padding: 0.45rem 0.85rem;
  border-radius: 8px;
  border: 0;
  background: #119a48;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}
.btn.danger {
  background: #b91c1c;
}
.btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.muted {
  color: #64748b;
}
</style>
