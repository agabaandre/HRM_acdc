<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError, PRIORITY_ITEMS, type TicketPriority } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

interface CategoryRow {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
  default_priority: TicketPriority
}

const headers: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '280px' },
  { title: 'Slug', key: 'slug', sortable: false, minWidth: '160px' },
  { title: 'Default priority', key: 'default_priority', sortable: false, width: '180px' },
  { title: 'Order', key: 'sort_order', sortable: false, width: '110px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const rows = ref<CategoryRow[]>([])
const busyId = ref<number | null>(null)

const draft = reactive({
  name: '',
  slug: '',
  sort_order: 0,
  is_active: true,
  default_priority: 'medium' as TicketPriority,
})

function validateDraft(state: typeof draft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) {
    errors.push(nameErr)
  }
  return errors
}

function normalizeRow(row: CategoryRow): CategoryRow {
  const priority = row.default_priority
  return {
    ...row,
    default_priority: (['low', 'medium', 'high', 'critical'].includes(priority) ? priority : 'medium') as TicketPriority,
  }
}

async function load() {
  try {
    const { data } = await api.get<{ data: CategoryRow[] }>('/api/v1/admin/categories')
    rows.value = (Array.isArray(data.data) ? data.data : []).map(normalizeRow)
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load categories.'))
  }
}

async function save(row: CategoryRow) {
  busyId.value = row.id
  try {
    await api.put(`/api/v1/admin/categories/${row.id}`, {
      name: row.name,
      slug: row.slug,
      sort_order: row.sort_order,
      is_active: row.is_active,
      default_priority: row.default_priority,
    })
    notifySuccess(`Updated “${row.name}”.`)
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function onCreate(_event: FormSubmitEvent<typeof draft>) {
  busyId.value = -1
  try {
    await api.post('/api/v1/admin/categories', {
      name: draft.name.trim(),
      slug: draft.slug.trim() || undefined,
      sort_order: draft.sort_order,
      is_active: draft.is_active,
      default_priority: draft.default_priority,
    })
    notifySuccess('Category created.')
    draft.name = ''
    draft.slug = ''
    draft.sort_order = 0
    draft.is_active = true
    draft.default_priority = 'medium'
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Create failed'))
  } finally {
    busyId.value = null
  }
}

async function remove(row: CategoryRow) {
  if (!window.confirm(`Delete category “${row.name}”? This fails if tickets still use it.`)) {
    return
  }
  busyId.value = row.id
  try {
    await api.delete(`/api/v1/admin/categories/${row.id}`)
    notifySuccess('Category deleted.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
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
    <p class="hint">
      Used on tickets and agent routing. Each category’s <strong>default priority</strong> is applied when a ticket is created.
      Only agents with <strong>Reassign tickets</strong> permission may override priority on new or existing tickets.
    </p>

    <v-card class="new-card" variant="outlined">
      <v-card-item>
        <v-card-title class="text-subtitle-1 font-weight-bold pa-0">Add category</v-card-title>
      </v-card-item>
      <v-divider />
      <v-card-text>
        <UForm
          :state="draft"
          :validate="validateDraft"
          class="hd-form hd-form--grid"
          @submit="onCreate"
        >
          <UFormField label="Name" name="name" required class="span-2">
            <UInput v-model="draft.name" maxlength="191" icon="mdi-tag-outline" />
          </UFormField>
          <UFormField label="Slug (optional)" name="slug" stacked-label description="Leave blank to auto-generate from the name">
            <UInput v-model="draft.slug" maxlength="191" placeholder="auto from name" />
          </UFormField>
          <UFormField label="Default priority" name="default_priority">
            <USelect v-model="draft.default_priority" :items="PRIORITY_ITEMS" icon="mdi-flag-outline" />
          </UFormField>
          <UFormField label="Sort order" name="sort_order">
            <UInput v-model.number="draft.sort_order" type="number" min="0" icon="mdi-sort-numeric-ascending" />
          </UFormField>
          <UFormField name="is_active">
            <UCheckbox v-model="draft.is_active" label="Active" />
          </UFormField>
          <div class="full hd-form-actions">
            <UButton type="submit" color="primary" :loading="busyId === -1">Create category</UButton>
          </div>
        </UForm>
      </v-card-text>
    </v-card>

    <v-card v-if="rows.length" class="cat-table-card" variant="outlined">
      <v-data-table
        :headers="headers"
        :items="rows"
        item-value="id"
        density="comfortable"
        class="hd-data-table cat-table"
        hide-default-footer
      >
        <template #item.name="{ item }">
          <UInput v-model="item.name" class="cat-name-input" />
        </template>
        <template #item.slug="{ item }">
          <UInput v-model="item.slug" />
        </template>
        <template #item.default_priority="{ item }">
          <USelect v-model="item.default_priority" :items="PRIORITY_ITEMS" />
        </template>
        <template #item.sort_order="{ item }">
          <UInput v-model.number="item.sort_order" type="number" min="0" class="cat-order-input" />
        </template>
        <template #item.is_active="{ item }">
          <UCheckbox v-model="item.is_active" hide-details />
        </template>
        <template #item.actions="{ item }">
          <div class="actions">
            <UButton
              type="button"
              color="neutral"
              variant="outlined"
              size="small"
              :loading="busyId === item.id"
              @click="save(item)"
            >
              Save
            </UButton>
            <UButton
              type="button"
              color="error"
              variant="soft"
              size="small"
              :disabled="busyId === item.id"
              @click="remove(item)"
            >
              Delete
            </UButton>
          </div>
        </template>
      </v-data-table>
    </v-card>
    <p v-else class="muted">No categories loaded.</p>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
  font-weight: 700;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.55;
  max-width: 52rem;
}
.new-card {
  margin-bottom: 1rem;
}
.cat-table-card {
  overflow: hidden;
}
.cat-table :deep(.cat-name-input) {
  min-width: 260px;
}
.cat-table :deep(.cat-order-input) {
  max-width: 5.5rem;
}
.actions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.muted {
  color: #64748b;
}
</style>
