<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import type { FormError, FormSubmitEvent } from '../../types/form'
import DirectorySyncCard from './DirectorySyncCard.vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError, type SelectNumberItem } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

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
const busyId = ref<number | null>(null)

const draft = reactive({
  name: '',
  category_id: 0,
  response_minutes: 240,
  resolution_minutes: 2880,
  is_active: true,
})

const categoryItems = computed((): SelectNumberItem[] => [
  { label: 'All categories', value: 0 },
  ...categories.value.map((c) => ({ label: c.name, value: c.id })),
])

function validateDraft(state: typeof draft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) {
    errors.push(nameErr)
  }
  return errors
}

async function loadCategories() {
  try {
    const { data } = await api.get<{ data: CatOpt[] }>('/api/v1/categories')
    categories.value = Array.isArray(data.data) ? data.data : []
  } catch {
    categories.value = []
  }
}

async function loadRules() {
  try {
    const { data } = await api.get<{ data: SlaRow[] }>('/api/v1/admin/sla-rules')
    const list = Array.isArray(data.data) ? data.data : []
    rules.value = list.map((r) => ({
      ...r,
      category_id: r.category_id ?? 0,
    }))
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load SLA rules.'))
  }
}

async function save(row: SlaRow) {
  busyId.value = row.id
  try {
    await api.put(`/api/v1/admin/sla-rules/${row.id}`, {
      name: row.name,
      category_id: row.category_id && row.category_id > 0 ? row.category_id : null,
      response_minutes: row.response_minutes,
      resolution_minutes: row.resolution_minutes,
      is_active: row.is_active,
    })
    notifySuccess(`Updated “${row.name}”.`)
    await loadRules()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function onCreate(_event: FormSubmitEvent<typeof draft>) {
  busyId.value = -1
  try {
    await api.post('/api/v1/admin/sla-rules', {
      name: draft.name.trim(),
      category_id: draft.category_id > 0 ? draft.category_id : null,
      response_minutes: draft.response_minutes,
      resolution_minutes: draft.resolution_minutes,
      is_active: draft.is_active,
    })
    notifySuccess('SLA rule created.')
    draft.name = ''
    draft.category_id = 0
    draft.response_minutes = 240
    draft.resolution_minutes = 2880
    draft.is_active = true
    await loadRules()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Create failed'))
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

    <UCard class="new-card">
      <template #header>
        <h3>Add SLA rule</h3>
      </template>
      <UForm
        :state="draft"
        :validate="validateDraft"
        class="hd-form hd-form--grid"
        @submit="onCreate"
      >
        <UFormField label="Name" name="name" required>
          <UInput v-model="draft.name" maxlength="191" placeholder="e.g. Email — standard" class="w-full" />
        </UFormField>
        <UFormField label="Category (optional)" name="category_id">
          <USelect v-model="draft.category_id" :items="categoryItems" class="w-full" />
        </UFormField>
        <UFormField label="Response (minutes)" name="response_minutes">
          <UInput v-model.number="draft.response_minutes" type="number" min="1" class="w-full" />
        </UFormField>
        <UFormField label="Resolution (minutes)" name="resolution_minutes">
          <UInput v-model.number="draft.resolution_minutes" type="number" min="1" class="w-full" />
        </UFormField>
        <UFormField name="is_active">
          <UCheckbox v-model="draft.is_active" label="Active" />
        </UFormField>
        <div class="full hd-form-actions">
          <UButton type="submit" color="primary" :loading="busyId === -1">Create rule</UButton>
        </div>
      </UForm>
    </UCard>

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
            <td><UInput v-model="r.name" class="w-full" /></td>
            <td>
              <USelect v-model="r.category_id" :items="categoryItems" class="w-full" />
            </td>
            <td><UInput v-model.number="r.response_minutes" type="number" min="1" class="w-24" /></td>
            <td><UInput v-model.number="r.resolution_minutes" type="number" min="1" class="w-24" /></td>
            <td><UCheckbox v-model="r.is_active" /></td>
            <td class="actions">
              <UButton type="button" color="neutral" variant="outline" size="xs" :loading="busyId === r.id" @click="save(r)">
                Save
              </UButton>
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
  padding: 0.45rem 0.5rem;
  border-bottom: 1px solid #e2e8f0;
  vertical-align: middle;
}
.actions {
  white-space: nowrap;
}
.muted {
  color: #64748b;
}
</style>
