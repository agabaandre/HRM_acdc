<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { useAuthStore } from '@/stores/auth'
import AppDataTable from '@/components/shared/AppDataTable.vue'

type Indicator = {
  id: number
  code: string
  title: string
  description: string | null
  directorate: string | null
  programme: string | null
  year: number
  target: string | null
  unit: string | null
  baseline: string | null
  q1: string | null
  q2: string | null
  q3: string | null
  q4: string | null
  is_active: boolean
  projects_count: number
}

const items = ref<Indicator[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')
const dialog = ref(false)
const editing = ref<Indicator | null>(null)
const deletingId = ref<number | null>(null)
const auth = useAuthStore()
const form = ref({
  code: '',
  title: '',
  description: '',
  directorate: 'MIS / Software Development',
  programme: '',
  year: 2026,
  target: '',
  unit: '',
  baseline: '',
  q1: '',
  q2: '',
  q3: '',
  q4: '',
  is_active: true,
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await api.get('/admin/workplan-indicators')
    items.value = res.data.data
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not load indicators.')
  } finally {
    loading.value = false
  }
}

const blankForm = () => ({
  code: '',
  title: '',
  description: '',
  directorate: 'MIS / Software Development',
  programme: '',
  year: 2026,
  target: '',
  unit: '',
  baseline: '',
  q1: '',
  q2: '',
  q3: '',
  q4: '',
  is_active: true,
})

function openNew() {
  editing.value = null
  form.value = blankForm()
  dialog.value = true
}

function openEdit(item: Indicator) {
  editing.value = item
  form.value = {
    ...blankForm(),
    ...item,
    description: item.description ?? '',
    programme: item.programme ?? '',
    target: item.target ?? '',
    unit: item.unit ?? '',
    baseline: item.baseline ?? '',
    q1: item.q1 ?? '',
    q2: item.q2 ?? '',
    q3: item.q3 ?? '',
    q4: item.q4 ?? '',
    directorate: item.directorate ?? '',
  }
  dialog.value = true
}

async function save() {
  saving.value = true
  error.value = ''
  try {
    if (editing.value) {
      await api.put(`/admin/workplan-indicators/${editing.value.id}`, form.value)
      message.value = 'Indicator updated.'
    } else {
      await api.post('/admin/workplan-indicators', form.value)
      message.value = 'Indicator added.'
    }
    dialog.value = false
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not save indicator.')
  } finally {
    saving.value = false
  }
}

async function remove(item: Indicator) {
  if (!confirm(`Delete indicator ${item.code}?`)) return
  deletingId.value = item.id
  error.value = ''
  try {
    await api.delete(`/admin/workplan-indicators/${item.id}`)
    message.value = `${item.code} deleted.`
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not delete indicator.')
  } finally {
    deletingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader title="Workplan indicators">
      <template v-if="auth.isAdmin" #actions>
        <v-btn color="primary" @click="openNew">Add indicator</v-btn>
      </template>
    </PageHeader>
    <ParentCard title="Indicator catalogue">
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>
      <AppDataTable
        :items="items"
        :loading="loading"
        :headers="[
          { title: 'Code', key: 'code' },
          { title: 'Indicator', key: 'title' },
          { title: 'UOM', key: 'unit' },
          { title: 'Baseline', key: 'baseline' },
          { title: 'Target · 2026', key: 'target' },
          { title: 'Q1', key: 'q1' },
          { title: 'Q2', key: 'q2' },
          { title: 'Q3', key: 'q3' },
          { title: 'Q4', key: 'q4' },
          { title: 'Projects', key: 'projects_count' },
          ...(auth.isAdmin ? [{ title: '', key: 'actions', sortable: false }] : []),
        ]"
      >
        <template #item.unit="{ item }">{{ item.unit || '—' }}</template>
        <template #item.baseline="{ item }">{{ item.baseline || '—' }}</template>
        <template #item.target="{ item }">{{ item.target || '—' }}</template>
        <template #item.q1="{ item }">{{ item.q1 || '—' }}</template>
        <template #item.q2="{ item }">{{ item.q2 || '—' }}</template>
        <template #item.q3="{ item }">{{ item.q3 || '—' }}</template>
        <template #item.q4="{ item }">{{ item.q4 || '—' }}</template>
        <template v-if="auth.isAdmin" #item.actions="{ item }">
          <v-btn size="small" variant="text" @click="openEdit(item)">Edit</v-btn>
          <v-btn
            size="small"
            variant="text"
            color="error"
            :loading="deletingId === item.id"
            @click="remove(item)"
          >Delete</v-btn>
        </template>
      </AppDataTable>
    </ParentCard>

    <v-dialog v-model="dialog" max-width="760">
      <v-card>
        <v-card-title>{{ editing ? 'Edit indicator' : 'Add dummy indicator' }}</v-card-title>
        <v-card-text>
          <FormField label="Code" required><v-text-field v-model="form.code" /></FormField>
          <FormField label="Title" required><v-text-field v-model="form.title" /></FormField>
          <FormField label="Description"><v-textarea v-model="form.description" rows="3" /></FormField>
          <v-row>
            <v-col cols="12" md="4"><FormField label="Year"><v-text-field v-model.number="form.year" type="number" /></FormField></v-col>
            <v-col cols="12" md="8"><FormField label="UOM"><v-text-field v-model="form.unit" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Baseline"><v-text-field v-model="form.baseline" /></FormField></v-col>
            <v-col cols="12" md="6"><FormField label="Target · 2026"><v-text-field v-model="form.target" /></FormField></v-col>
            <v-col cols="6" md="3"><FormField label="Q1"><v-text-field v-model="form.q1" /></FormField></v-col>
            <v-col cols="6" md="3"><FormField label="Q2"><v-text-field v-model="form.q2" /></FormField></v-col>
            <v-col cols="6" md="3"><FormField label="Q3"><v-text-field v-model="form.q3" /></FormField></v-col>
            <v-col cols="6" md="3"><FormField label="Q4"><v-text-field v-model="form.q4" /></FormField></v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Save</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
