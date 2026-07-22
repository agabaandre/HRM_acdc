<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, reactive, ref, watch } from 'vue'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../types/form'
import KbArticleEditModal, { type KbArticleEditForm } from '../components/kb/KbArticleEditModal.vue'
import CbpPageHeading from '../components/common/CbpPageHeading.vue'
import { api } from '../lib/api'
import { notifyError, notifySuccess } from "../lib/notify"
import { apiErrorMessage } from '../lib/apiErrorMessage'
import { fieldError, type SelectNumberItem } from '../lib/helpdeskForm'
import { formatTableCountLabel, rowIndex } from '../lib/ticketTableMeta'
import { hasRichTextContent } from '../lib/richText'
import { stripHtml } from '../lib/stripHtml'

const CbpRichTextEditor = defineAsyncComponent(
  () => import('../components/common/CbpRichTextEditor.vue'),
)

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
const page = ref(1)
const itemsPerPage = ref(20)
const itemsPerPageOptions = [10, 20, 50, 100] as const

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

const tableCountLabel = computed(() =>
  formatTableCountLabel(filtered.value.length, filtered.value.length, page.value, itemsPerPage.value),
)

const kbHeaders = computed((): DataTableHeader[] => [
  { title: '#', key: 'row_num', sortable: false, width: '52px', align: 'center' },
  { title: 'Category', key: 'category', sortable: false, minWidth: '120px' },
  { title: 'Question', key: 'question', sortable: false, minWidth: '180px' },
  { title: 'Answer preview', key: 'answer_preview', sortable: false, minWidth: '180px' },
  { title: 'Sort', key: 'sort_order', sortable: false, width: '72px', align: 'center' },
  { title: 'Status', key: 'status', sortable: false, width: '100px' },
  { title: 'Last updated', key: 'updated_at', sortable: false, minWidth: '140px' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'end', width: '150px' },
])

function rowNumber(index: number): number {
  return rowIndex(page.value, itemsPerPage.value, index)
}

function kbRowProps(data: { item: KbArticleRow }) {
  return {
    class: data.item.is_active ? '' : 'hd-data-table-row--inactive',
  }
}

watch([filterCat, search], () => {
  page.value = 1
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

    <v-card class="hd-data-table-card hd-page-toolbar" variant="outlined">
      <v-card-text class="hd-page-toolbar__search">
        <div class="hd-v-form__fieldset">
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
      </v-card-text>

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

      <v-card-text class="hd-data-table-card__head">
        <h2 id="list-heading" class="list-heading">Articles</h2>
        <p v-if="filtered.length" class="table-count" role="status">
          Showing <strong>{{ tableCountLabel }}</strong>
        </p>
      </v-card-text>

      <v-data-table
        v-if="filtered.length"
        v-model:page="page"
        v-model:items-per-page="itemsPerPage"
        class="hd-data-table"
        :headers="kbHeaders"
        :items="filtered"
        :items-per-page-options="[...itemsPerPageOptions]"
        density="compact"
        hover
        item-value="id"
        :row-props="kbRowProps"
      >
        <template #item.row_num="{ index }">
          <span class="hd-dt-row-num">{{ rowNumber(index) }}</span>
        </template>

        <template #item.category="{ item }">
          {{ item.category?.name ?? '—' }}
        </template>

        <template #item.question="{ item }">
          <span class="hd-dt-wrap">{{ item.question }}</span>
        </template>

        <template #item.answer_preview="{ item }">
          <span class="hd-dt-truncate hd-dt-truncate--muted" :title="stripHtml(item.answer, 0)">
            {{ stripHtml(item.answer) }}
          </span>
        </template>

        <template #item.sort_order="{ item }">
          {{ item.sort_order }}
        </template>

        <template #item.status="{ item }">
          <span
            class="hd-dt-pill"
            :style="item.is_active
              ? { background: '#dcfce7', color: '#166534' }
              : { background: '#f1f5f9', color: '#64748b' }"
          >
            {{ item.is_active ? 'Active' : 'Hidden' }}
          </span>
        </template>

        <template #item.updated_at="{ item }">
          <span class="hd-dt-truncate hd-dt-truncate--updated">{{ formatUpdated(item) }}</span>
        </template>

        <template #item.actions="{ item }">
          <div class="hd-dt-action-btns">
            <UButton type="button" color="neutral" variant="outline" size="xs" :disabled="busy === item.id" @click="openEdit(item)">
              Edit
            </UButton>
            <UButton type="button" color="error" variant="soft" size="xs" :disabled="busy === item.id" @click="removeRow(item)">
              Delete
            </UButton>
          </div>
        </template>
      </v-data-table>

      <v-card-text v-else>
        <p class="hd-dt-empty-msg">
          {{
            rows.length === 0
              ? 'No FAQs yet — click Add FAQ to publish the first one.'
              : 'No articles match the current filter.'
          }}
        </p>
      </v-card-text>
    </v-card>

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
.hd-data-table-card {
  margin-top: 1rem;
}
.list-heading {
  font-size: 1.05rem;
  margin: 0 0 0.2rem;
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
  margin: 0 1rem 0.75rem;
}
.create-panel-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: #1a1a1a;
}
</style>
