<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '@nuxt/ui'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

interface CategoryRow {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
}

const rows = ref<CategoryRow[]>([])
const busyId = ref<number | null>(null)

const draft = reactive({
  name: '',
  slug: '',
  sort_order: 0,
  is_active: true,
})

function validateDraft(state: typeof draft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) {
    errors.push(nameErr)
  }
  return errors
}

async function load() {
  try {
    const { data } = await api.get<{ data: CategoryRow[] }>('/api/v1/admin/categories')
    rows.value = Array.isArray(data.data) ? data.data : []
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
    })
    notifySuccess('Category created.')
    draft.name = ''
    draft.slug = ''
    draft.sort_order = 0
    draft.is_active = true
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
    <p class="hint">Used on tickets and agent routing. Inactive categories stay hidden from new requests where the public list filters active only.</p>

    <UCard class="new-card">
      <template #header>
        <h3>Add category</h3>
      </template>
      <UForm
        :state="draft"
        :validate="validateDraft"
        class="hd-form hd-form--grid hd-form--grid-2"
        @submit="onCreate"
      >
        <UFormField label="Name" name="name" required>
          <UInput v-model="draft.name" maxlength="191" class="w-full" />
        </UFormField>
        <UFormField label="Slug (optional)" name="slug" description="Leave blank to auto-generate from the name">
          <UInput v-model="draft.slug" maxlength="191" placeholder="auto from name" class="w-full" />
        </UFormField>
        <UFormField label="Sort order" name="sort_order">
          <UInput v-model.number="draft.sort_order" type="number" min="0" class="w-full" />
        </UFormField>
        <UFormField name="is_active">
          <UCheckbox v-model="draft.is_active" label="Active" />
        </UFormField>
        <div class="full hd-form-actions">
          <UButton type="submit" color="primary" :loading="busyId === -1">Create category</UButton>
        </div>
      </UForm>
    </UCard>

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
            <td><UInput v-model="r.name" class="w-full" /></td>
            <td><UInput v-model="r.slug" class="w-full" /></td>
            <td><UInput v-model.number="r.sort_order" type="number" min="0" class="w-24" /></td>
            <td><UCheckbox v-model="r.is_active" /></td>
            <td class="actions">
              <UButton type="button" color="neutral" variant="outline" size="xs" :loading="busyId === r.id" @click="save(r)">
                Save
              </UButton>
              <UButton type="button" color="error" variant="soft" size="xs" :disabled="busyId === r.id" @click="remove(r)">
                Delete
              </UButton>
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
  margin: 0;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.5;
}
.new-card {
  margin-bottom: 1rem;
}
.table-wrap {
  overflow-x: auto;
  border-radius: 4px;
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
  padding: 0.55rem 0.65rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}
.actions {
  white-space: nowrap;
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.muted {
  color: #64748b;
}
</style>
