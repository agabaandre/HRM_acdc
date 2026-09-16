<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'

type Step = { step_order: number; role: string; staff_id: number | null; skippable_if_empty: boolean }
type Workflow = { id: number; name: string; division_id: number | null; is_default: boolean; steps: Step[] }

const workflows = ref<Workflow[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const name = ref('Division workflow')
const divisionId = ref<number | null>(null)
const smFocal = ref<number | null>(null)
const extra = ref<number | null>(null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get<{ data: Workflow[] }>('/api/v1/workflows')
    workflows.value = data.data
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load workflows')
  } finally {
    loading.value = false
  }
}

async function create() {
  await api.post('/api/v1/workflows', {
    name: name.value,
    division_id: divisionId.value,
    steps: [
      { step_order: 1, role: 'risk_focal', skippable_if_empty: false },
      { step_order: 2, role: 'hod', skippable_if_empty: false },
      { step_order: 3, role: 'director', skippable_if_empty: true },
      { step_order: 4, role: 'sm_focal', staff_id: smFocal.value, skippable_if_empty: false },
      { step_order: 5, role: 'extra', staff_id: extra.value, skippable_if_empty: false },
    ],
  })
  await load()
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <h1>Approval workflows</h1>
    <p class="rr-muted">Default: Risk Focal → HOD → Director (skippable) → SM Focal → one Extra level.</p>

    <p v-if="loading">Loading…</p>
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <ul v-else class="rr-list">
      <li v-for="w in workflows" :key="w.id">
        <strong>{{ w.name }}</strong>
        <span v-if="w.is_default" class="rr-badge">default</span>
        <span class="rr-muted"> division {{ w.division_id ?? 'global' }}</span>
        <ol>
          <li v-for="s in w.steps" :key="s.step_order">
            {{ s.step_order }}. {{ s.role }}
            <span v-if="s.staff_id"> (staff {{ s.staff_id }})</span>
            <span v-if="s.skippable_if_empty"> — skippable</span>
          </li>
        </ol>
      </li>
    </ul>

    <section class="rr-form">
      <h2>Add division workflow</h2>
      <label>Name<input v-model="name" /></label>
      <label>Division ID<input v-model.number="divisionId" type="number" /></label>
      <label>SM Focal staff ID<input v-model.number="smFocal" type="number" /></label>
      <label>Extra level staff ID<input v-model.number="extra" type="number" /></label>
      <button type="button" class="rr-btn" @click="create">Create</button>
    </section>
  </div>
</template>

<style scoped>
.rr-page { max-width: 800px; margin: 0 auto; padding: 1.5rem; }
.rr-list { list-style: none; padding: 0; }
.rr-list > li { padding: 0.75rem 0; border-bottom: 1px solid #e8edf2; }
.rr-badge { background: #0b6e4f; color: #fff; font-size: 0.75rem; padding: 0.1rem 0.4rem; border-radius: 4px; margin-left: 0.35rem; }
.rr-form { margin-top: 1.5rem; display: grid; gap: 0.65rem; max-width: 420px; }
.rr-form label { display: grid; gap: 0.25rem; font-weight: 600; }
.rr-btn { background: #0b6e4f; color: #fff; border: 0; border-radius: 6px; padding: 0.5rem 1rem; font-weight: 600; cursor: pointer; width: fit-content; }
.rr-muted { color: #6a7a8a; }
.rr-error { color: #b42318; }
</style>
