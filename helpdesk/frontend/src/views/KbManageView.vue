<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '@nuxt/ui'
import KbArticleEditModal, { type KbArticleEditForm } from '../components/kb/KbArticleEditModal.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import CbpRichTextEditor from '../components/common/CbpRichTextEditor.vue'
import { api } from '../lib/api'
import { notifyError, notifySuccess } from "../lib/notify"
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { fieldError, type SelectNumberItem } from '../lib/helpdeskForm'
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

const categoryItems = computed((): SelectNumberItem[] => [
  { label: 'All categories', value: 0 },
  ...cats.value.map((c) => ({ label: c.name, value: c.id })),
])

const categorySelectItems = computed((): SelectNumberItem[] =>
  cats.value.map((c) => ({ label: c.name, value: c.id })),
)

async function loadCats(): Promise<void> {
  const { data } = await api.get<{ data: Cat[] }>('/api/v1/categories')
  cats.value = Array.isArray(data.data) ? data.data : []
  if (cats.value.length && create.category_id === 0) {
    create.category_id = cats.value[0].id
  }
}

async function loadRows(): Promise<void> {
  try {
    const { data } = await api.get<{ data: KbArticleRow[] }>('/api/v1/admin/kb/articles')
    rows.value = Array.isArray(data.data) ? data.data : []
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load articles.'))
    rows.value = []
  }
}

async function loadAll(): Promise<void> {
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

function validateCreateForm(state: typeof create): FormError[] {
  const errors: FormError[] = []
  if (!state.category_id) {
    errors.push({ name: 'category_id', message: 'Choose a category' })
  }
  const qErr = fieldError('question', state.question, 'Question is required')
  if (qErr) {
    errors.push(qErr)
  }
  if (!hasRichTextContent(state.answer)) {
    errors.push({ name: 'answer', message: 'Answer is required' })
  }
  return errors
}

async function onCreateArticle(_event: FormSubmitEvent<typeof create>): Promise<void> {
  busy.value = -1
  try {
    await api.post('/api/v1/admin/kb/articles', {
      category_id: create.category_id,
      question: create.question.trim(),
      answer: create.answer,
      sort_order: create.sort_order,
      is_active: create.is_active,
    })
    notifySuccess('FAQ published.')
    create.question = ''
    create.answer = ''
    create.sort_order = 0
    create.is_active = true
    showCreateForm.value = false
    await loadRows()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Create failed.'))
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
  editErr.value = null
  try {
    await api.put(`/api/v1/admin/kb/articles/${payload.id}`, {
      category_id: payload.category_id,
      question: payload.question,
      answer: payload.answer,
      sort_order: payload.sort_order,
      is_active: payload.is_active,
    })
    notifySuccess(`Saved “${payload.question}”.`)
    editOpen.value = false
    editTarget.value = null
    editErr.value = null
    await loadRows()
  } catch (e: unknown) {
    const message = apiErrorMessage(e, 'Save failed.')
    editErr.value = message
    notifyError(message)
  } finally {
    busy.value = null
  }
}

async function removeRow(row: KbArticleRow): Promise<void> {
  if (!window.confirm(`Delete “${row.question}”? This cannot be undone.`)) {
    return
  }
  busy.value = row.id
  try {
    await api.delete(`/api/v1/admin/kb/articles/${row.id}`)
    notifySuccess('Article deleted (logged in audit trail).')
    if (editTarget.value?.id === row.id) {
      closeEdit()
    }
    await loadRows()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed.'))
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

    <section class="cbp-card list-card" aria-labelledby="list-heading">
      <header class="list-head">
        <div class="list-head-title">
          <h2 id="list-heading">Articles</h2>
          <p class="table-count" role="status">
            Showing <strong>{{ filtered.length }}</strong> of <strong>{{ rows.length }}</strong> articles
          </p>
        </div>
        <div class="list-head-actions">
          <UFormField label="Filter by category" name="filterCat" class="kb-toolbar-field kb-toolbar-field--filter">
            <USelect v-model="filterCat" :items="categoryItems" class="w-full" />
          </UFormField>
          <UFormField label="Search" name="search" class="kb-toolbar-field kb-toolbar-field--search">
            <UInput
              v-model="search"
              type="search"
              icon="i-lucide-search"
              placeholder="Question, answer, category…"
              aria-label="Search articles"
              class="w-full"
            />
          </UFormField>
          <UButton
            type="button"
            color="primary"
            class="kb-toolbar-add"
            :aria-expanded="showCreateForm"
            aria-controls="create-faq-panel"
            @click="toggleCreateForm"
          >
            <i class="bx bx-plus" aria-hidden="true" />
            {{ showCreateForm ? 'Hide form' : 'Add FAQ' }}
          </UButton>
        </div>
      </header>

      <UCard
        v-show="showCreateForm"
        id="create-faq-panel"
        class="create-panel"
        aria-labelledby="create-heading"
      >
        <template #header>
          <h3 id="create-heading" class="create-panel-title">New FAQ</h3>
        </template>
        <UForm
          :state="create"
          :validate="validateCreateForm"
          class="hd-form hd-form--grid"
          :disabled="busy === -1"
          @submit="onCreateArticle"
        >
          <UFormField label="Category" name="category_id" required>
            <USelect v-model="create.category_id" :items="categorySelectItems" class="w-full" />
          </UFormField>
          <UFormField label="Sort order" name="sort_order">
            <UInput v-model.number="create.sort_order" type="number" min="0" class="w-full" />
          </UFormField>
          <UFormField name="is_active" class="full">
            <UCheckbox v-model="create.is_active" label="Active (visible on the home knowledge base)" />
          </UFormField>
          <UFormField label="Question" name="question" required class="full">
            <UInput v-model="create.question" type="text" maxlength="255" placeholder="e.g. How do I reset my password?" class="w-full" />
          </UFormField>
          <UFormField label="Answer" name="answer" required class="full hd-rich-field">
            <CbpRichTextEditor
              v-model="create.answer"
              placeholder="Step-by-step instructions, links, screenshots…"
            />
          </UFormField>
          <div class="full hd-form-actions">
            <UButton type="button" color="neutral" variant="outline" :disabled="busy === -1" @click="closeCreateForm">
              Cancel
            </UButton>
            <UButton type="submit" color="primary" :loading="busy === -1">
              Publish FAQ
            </UButton>
          </div>
        </UForm>
      </UCard>

      <div class="table-wrap">
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
                    <UButton type="button" color="neutral" variant="outline" size="xs" :disabled="busy === r.id" @click="openEdit(r)">
                      Edit
                    </UButton>
                    <UButton type="button" color="error" variant="soft" size="xs" :disabled="busy === r.id" @click="removeRow(r)">
                      Delete
                    </UButton>
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
  border-radius: 4px;
}
.ok {
  margin: 0.75rem 0;
  padding: 0.65rem 0.85rem;
  background: #ecfdf5;
  border: 1px solid #a7f3d0;
  color: #166534;
  border-radius: 4px;
}
.list-card {
  padding: 1.1rem;
  margin-top: 1rem;
}
.list-card h2 {
  font-size: 1.05rem;
  margin: 0;
}
.list-head-title {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}
.list-head-title .table-count {
  margin: 0;
  font-size: 0.875rem;
  color: #64748b;
}
.list-head {
  display: flex;
  flex-wrap: wrap;
  gap: 0.85rem 1rem;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 0.85rem;
}
.list-head-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  align-items: flex-end;
  flex: 1;
  justify-content: flex-end;
  min-width: min(100%, 28rem);
}
.kb-toolbar-field {
  margin: 0;
  min-width: 0;
}
.kb-toolbar-field--filter {
  width: 11.5rem;
  flex-shrink: 0;
}
.kb-toolbar-field--search {
  flex: 1;
  min-width: min(16rem, 100%);
  max-width: 22rem;
}
.kb-toolbar-add {
  flex-shrink: 0;
  align-self: flex-end;
}
@media (max-width: 720px) {
  .list-head {
    align-items: stretch;
  }
  .list-head-actions {
    width: 100%;
    justify-content: stretch;
  }
  .kb-toolbar-field--filter,
  .kb-toolbar-field--search {
    width: 100%;
    max-width: none;
  }
  .kb-toolbar-add {
    width: 100%;
    justify-content: center;
  }
}
.create-panel {
  margin: 0.85rem 0 1rem;
}
.create-panel-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1a1a1a;
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
.muted {
  color: #64748b;
}
</style>
