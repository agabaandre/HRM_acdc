<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import KbArticleEditModal, { type KbArticleEditForm } from '../components/kb/KbArticleEditModal.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import CbpRichTextEditor from '../components/common/CbpRichTextEditor.vue'
import { api } from '../lib/api'
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { hasRichTextContent } from '../lib/richText'
import { stripHtml } from '../lib/stripHtml'

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

const showCreateForm = ref(false)
const editOpen = ref(false)
const editTarget = ref<KbArticleEditForm | null>(null)
const editErr = ref<string | null>(null)

function toggleCreateForm(): void {
  showCreateForm.value = !showCreateForm.value
}

function closeCreateForm(): void {
  if (busy.value === -1) {
    return
  }
  showCreateForm.value = false
}

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
      const hay = `${r.question} ${stripHtml(r.answer, 0)} ${r.category?.name ?? ''}`.toLowerCase()
      if (!hay.includes(q)) {
        return false
      }
    }
    return true
  })
})

function formatUpdated(row: KbArticleRow): string {
  if (!row.updated_at) {
    return '—'
  }
  const who = row.updated_by?.name ?? row.created_by?.name
  const when = new Date(row.updated_at).toLocaleString()
  return who ? `${when} · ${who}` : when
}

async function createArticle(): Promise<void> {
  if (!create.question.trim() || !hasRichTextContent(create.answer) || !create.category_id) {
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
    ok.value = 'FAQ published.'
    create.question = ''
    create.answer = ''
    create.sort_order = 0
    create.is_active = true
    showCreateForm.value = false
    await loadRows()
  } catch (e: unknown) {
    err.value = apiErrorMessage(e, 'Create failed.')
  } finally {
    busy.value = null
  }
}

function openEdit(row: KbArticleRow): void {
  editErr.value = null
  editTarget.value = {
    id: row.id,
    category_id: row.category_id,
    question: row.question,
    answer: row.answer,
    sort_order: row.sort_order,
    is_active: row.is_active,
  }
  editOpen.value = true
}

function closeEdit(): void {
  if (busy.value === editTarget.value?.id) {
    return
  }
  editOpen.value = false
  editTarget.value = null
  editErr.value = null
}

async function saveEdit(payload: KbArticleEditForm): Promise<void> {
  busy.value = payload.id
  ok.value = null
  err.value = null
  editErr.value = null
  try {
    await api.put(`/api/v1/admin/kb/articles/${payload.id}`, {
      category_id: payload.category_id,
      question: payload.question,
      answer: payload.answer,
      sort_order: payload.sort_order,
      is_active: payload.is_active,
    })
    ok.value = `Saved “${payload.question}”.`
    editOpen.value = false
    editTarget.value = null
    editErr.value = null
    await loadRows()
  } catch (e: unknown) {
    editErr.value = apiErrorMessage(e, 'Save failed.')
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
    ok.value = 'Article deleted (logged in audit trail).'
    if (editTarget.value?.id === row.id) {
      closeEdit()
    }
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
        Creates, edits, and deletes are recorded in the
        <RouterLink to="/settings/logging">audit log</RouterLink>.
      </template>
    </CbpPageHeading>

    <p v-if="err" class="err">{{ err }}</p>
    <p v-if="ok" class="ok">{{ ok }}</p>

    <section class="cbp-card list-card" aria-labelledby="list-heading">
      <header class="list-head">
        <h2 id="list-heading">Articles</h2>
        <div class="list-head-actions">
          <button
            type="button"
            class="btn-add-faq"
            :aria-expanded="showCreateForm"
            aria-controls="create-faq-panel"
            @click="toggleCreateForm"
          >
            <i class="bx bx-plus" aria-hidden="true" />
            {{ showCreateForm ? 'Hide form' : 'Add FAQ' }}
          </button>
          <label>
            <span class="sr-only">Filter by category</span>
            <select v-model.number="filterCat">
              <option :value="0">All categories</option>
              <option v-for="c in cats" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label class="search-wrap">
            <span class="sr-only">Search</span>
            <input v-model="search" type="search" placeholder="Search question, answer, category…" />
          </label>
        </div>
      </header>

      <section
        v-show="showCreateForm"
        id="create-faq-panel"
        class="create-panel"
        aria-labelledby="create-heading"
      >
        <h3 id="create-heading" class="create-panel-title">New FAQ</h3>
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
          <CbpRichTextEditor
            v-model="create.answer"
            placeholder="Step-by-step instructions, links, screenshots…"
          />
        </label>
        <div class="create-panel-actions">
          <button type="button" class="btn-ghost" :disabled="busy === -1" @click="closeCreateForm">
            Cancel
          </button>
          <button type="button" class="primary" :disabled="busy === -1" @click="createArticle">
            {{ busy === -1 ? 'Publishing…' : 'Publish FAQ' }}
          </button>
        </div>
      </section>

      <div class="table-wrap">
        <p class="table-count">
          Showing <strong>{{ filtered.length }}</strong> of <strong>{{ rows.length }}</strong> articles
        </p>
        <div v-if="filtered.length === 0" class="table-empty muted">
          {{
            rows.length === 0
              ? 'No FAQs yet — click Add FAQ to publish the first one.'
              : 'No articles match the current filter.'
          }}
        </div>
        <div v-else class="table-scroll">
          <table class="ticket-table kb-table">
            <thead>
              <tr>
                <th class="col-idx">#</th>
                <th class="col-cat">Category</th>
                <th class="col-q">Question</th>
                <th class="col-preview">Answer preview</th>
                <th class="col-sort">Sort</th>
                <th class="col-status">Status</th>
                <th class="col-updated">Last updated</th>
                <th class="col-actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(r, idx) in filtered"
                :key="r.id"
                :class="{ 'row-inactive': !r.is_active }"
              >
                <td class="col-idx">{{ idx + 1 }}</td>
                <td class="col-cat">{{ r.category?.name ?? '—' }}</td>
                <td class="col-q">
                  <span class="q-text" :title="r.question">{{ r.question }}</span>
                </td>
                <td class="col-preview">
                  <span class="preview-text" :title="stripHtml(r.answer, 0)">{{ stripHtml(r.answer) }}</span>
                </td>
                <td class="col-sort">{{ r.sort_order }}</td>
                <td class="col-status">
                  <span class="status-pill" :class="r.is_active ? 'on' : 'off'">
                    {{ r.is_active ? 'Active' : 'Hidden' }}
                  </span>
                </td>
                <td class="col-updated">
                  <span class="updated-text">{{ formatUpdated(r) }}</span>
                </td>
                <td class="col-actions">
                  <div class="action-btns">
                    <button type="button" class="btn-sm" :disabled="busy === r.id" @click="openEdit(r)">
                      Edit
                    </button>
                    <button type="button" class="btn-sm danger" :disabled="busy === r.id" @click="removeRow(r)">
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <KbArticleEditModal
      :open="editOpen"
      :article="editTarget"
      :categories="cats"
      :busy="busy !== null && busy === editTarget?.id"
      :error="editErr"
      @close="closeEdit"
      @save="saveEdit"
    />
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
.list-card {
  padding: 1.1rem;
  margin-top: 1rem;
}
.list-card h2 {
  font-size: 1.05rem;
  margin: 0;
}
.create-panel {
  margin: 0.85rem 0 1rem;
  padding: 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
}
.create-panel-title {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1a1a1a;
}
.create-panel-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  justify-content: flex-end;
  margin-top: 0.75rem;
}
.btn-add-faq {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.45rem 0.85rem;
  border-radius: 8px;
  border: none;
  background: linear-gradient(135deg, #119a48 0%, #0d7a3a 100%);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.btn-add-faq i {
  font-size: 1.1rem;
  line-height: 1;
}
.btn-ghost {
  padding: 0.55rem 1rem;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #475569;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
}
.btn-ghost:disabled {
  opacity: 0.65;
  cursor: not-allowed;
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
select {
  padding: 0.5rem 0.65rem;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 0.9rem;
  background: #fff;
  font-family: inherit;
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
.list-head-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}
.search-wrap input {
  min-width: min(280px, 100%);
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
.table-empty {
  padding: 1.25rem;
  text-align: center;
}
.kb-table .col-idx {
  width: 3%;
  text-align: center;
}
.kb-table .col-cat {
  width: 14%;
}
.kb-table .col-q {
  width: 22%;
}
.kb-table .col-preview {
  width: 24%;
}
.kb-table .col-sort {
  width: 6%;
  text-align: center;
}
.kb-table .col-status {
  width: 9%;
}
.kb-table .col-updated {
  width: 16%;
}
.kb-table .col-actions {
  width: 12%;
}
.q-text,
.preview-text,
.updated-text {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.875rem;
  color: #1a1a1a;
}
.preview-text {
  color: #64748b;
  font-size: 0.8rem;
}
.updated-text {
  font-size: 0.75rem;
  color: #64748b;
  white-space: normal;
  line-height: 1.35;
}
tbody tr.row-inactive td {
  background: #f8fafc;
  opacity: 0.85;
}
.status-pill {
  display: inline-block;
  padding: 0.15rem 0.45rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
}
.status-pill.on {
  background: #dcfce7;
  color: #166534;
}
.status-pill.off {
  background: #f1f5f9;
  color: #64748b;
}
.action-btns {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}
.btn-sm {
  padding: 0.35rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #fff;
  color: #0d7a3a;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.btn-sm.danger {
  border-color: #fecaca;
  color: #b91c1c;
  background: #fff;
}
.btn-sm:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.muted {
  color: #64748b;
}
</style>
