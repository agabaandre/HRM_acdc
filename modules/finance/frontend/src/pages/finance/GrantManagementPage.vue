<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { FINANCE_PERMS } from '@/lib/financePermissions'
import { useAuthStore } from '@/stores/auth'

type Grant = {
  id: number
  name: string
  short_name?: string | null
  funder_id?: number | null
  funder_name?: string | null
  end_date?: string | null
  total_budget?: number | null
  direct_cost_usd?: number | null
  indirect_cost?: number | null
  indirect_pct?: number | null
  budget_code?: string | null
  status?: string
  is_active?: boolean
  notes?: string | null
}

type Funder = { id: number; name: string; is_active?: boolean }

const auth = useAuthStore()
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const grants = ref<Grant[]>([])
const funders = ref<Funder[]>([])
const canEditApi = ref(false)
const search = ref('')
const showForm = ref(false)
const editingId = ref<number | null>(null)
const form = ref<Partial<Grant>>({})

const canEdit = computed(
  () =>
    canEditApi.value ||
    auth.hasPermission(FINANCE_PERMS.MANAGE_FUNDING_PORTFOLIO) ||
    auth.hasPermission(FINANCE_PERMS.SETTINGS),
)

const funderItems = computed(() =>
  funders.value.map((f) => ({ title: f.name, value: f.id })),
)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return grants.value
  return grants.value.filter((g) =>
    [g.name, g.short_name, g.funder_name, g.budget_code]
      .map((x) => String(x || '').toLowerCase())
      .some((s) => s.includes(q)),
  )
})

const formTitle = computed(() => (editingId.value ? 'Edit grant' : 'Add grant'))

function usd(n: number | null | undefined): string {
  if (n == null || Number.isNaN(Number(n))) return '—'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 0,
  }).format(Number(n))
}

function emptyForm(): Partial<Grant> {
  return {
    name: '',
    short_name: '',
    funder_id: null,
    funder_name: '',
    end_date: '',
    total_budget: 0,
    direct_cost_usd: 0,
    indirect_cost: 0,
    indirect_pct: 0,
    budget_code: '',
    status: 'active',
    is_active: true,
    notes: '',
  }
}

function onFunderPick(id: number | null) {
  form.value.funder_id = id
  const match = funders.value.find((f) => f.id === id)
  form.value.funder_name = match?.name || ''
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/grants')
    grants.value = data.data.grants || []
    funders.value = data.data.funders || []
    canEditApi.value = !!data.data.can_edit
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load grants.')
  } finally {
    loading.value = false
  }
}

function openNew() {
  editingId.value = null
  form.value = emptyForm()
  showForm.value = true
}

function openEdit(g: Grant) {
  editingId.value = g.id
  form.value = {
    ...emptyForm(),
    ...g,
    end_date: g.end_date ? String(g.end_date).slice(0, 10) : '',
  }
  showForm.value = true
}

function cancelForm() {
  showForm.value = false
  editingId.value = null
  form.value = emptyForm()
}

async function save() {
  if (!canEdit.value) return
  if (!String(form.value.name || '').trim()) {
    error.value = 'Grant name is required.'
    return
  }
  saving.value = true
  error.value = null
  message.value = null
  try {
    const payload = { ...form.value }
    if (editingId.value) {
      await api.put(`/api/v1/grants/${editingId.value}`, payload)
      message.value = 'Grant updated.'
    } else {
      await api.post('/api/v1/grants', payload)
      message.value = 'Grant created.'
    }
    cancelForm()
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    saving.value = false
  }
}

async function remove(g: Grant) {
  if (!canEdit.value) return
  if (!window.confirm(`Delete grant “${g.name}”?`)) return
  saving.value = true
  error.value = null
  try {
    await api.delete(`/api/v1/grants/${g.id}`)
    message.value = 'Grant deleted.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Delete failed.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="gm">
    <header class="gm__header">
      <div>
        <div class="gm__title-row">
          <h1>Grant management</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="gm__sub">
          Catalogue of Funding Portfolio grants used by Extramural and Indirect Cost entry.
          Searchable in data entry forms.
        </p>
      </div>
      <div class="gm__actions">
        <RouterLink class="gm__link" :to="{ name: 'portfolio-entry' }">Data entry</RouterLink>
        <button v-if="canEdit" type="button" class="btn btn-primary" @click="openNew">Add grant</button>
      </div>
    </header>

    <p v-if="!canEdit" class="gm__banner">
      View only — need <code>manage_funding_portfolio</code> ({{ FINANCE_PERMS.MANAGE_FUNDING_PORTFOLIO }}) to edit.
    </p>
    <p v-if="message" class="gm__ok">{{ message }}</p>
    <p v-if="error" class="gm__error">{{ error }}</p>
    <FinSkeleton v-if="loading" variant="table" :rows="8" />

    <template v-else>
      <section v-if="canEdit && showForm" class="gm__panel gm__panel--form">
        <header class="gm__panel-head">
          <h2>{{ formTitle }}</h2>
          <button type="button" class="btn" @click="cancelForm">Cancel</button>
        </header>
        <div class="gm__grid">
          <label class="gm__field gm__field--wide">
            <span>Grant name</span>
            <input v-model="form.name" type="text" required />
          </label>
          <label class="gm__field">
            <span>Short name</span>
            <input v-model="form.short_name" type="text" />
          </label>
          <label class="gm__field">
            <span>APM funder</span>
            <v-autocomplete
              :model-value="form.funder_id ?? null"
              :items="funderItems"
              item-title="title"
              item-value="value"
              density="compact"
              variant="outlined"
              hide-details
              clearable
              auto-select-first
              placeholder="Search funder…"
              @update:model-value="onFunderPick"
            />
          </label>
          <label class="gm__field">
            <span>End date</span>
            <input v-model="form.end_date" type="date" />
          </label>
          <label class="gm__field">
            <span>Total budget (USD)</span>
            <input v-model.number="form.total_budget" type="number" step="any" />
          </label>
          <label class="gm__field">
            <span>Direct cost (USD)</span>
            <input v-model.number="form.direct_cost_usd" type="number" step="any" />
          </label>
          <label class="gm__field">
            <span>Indirect cost</span>
            <input v-model.number="form.indirect_cost" type="number" step="any" />
          </label>
          <label class="gm__field">
            <span>Indirect %</span>
            <input v-model.number="form.indirect_pct" type="number" step="any" />
          </label>
          <label class="gm__field">
            <span>Budget code</span>
            <input v-model="form.budget_code" type="text" />
          </label>
          <label class="gm__field">
            <span>Status</span>
            <select v-model="form.status">
              <option value="active">active</option>
              <option value="closed">closed</option>
              <option value="pipeline">pipeline</option>
            </select>
          </label>
          <label class="gm__field gm__field--check">
            <input v-model="form.is_active" type="checkbox" />
            <span>Active (shown in entry dropdowns)</span>
          </label>
          <label class="gm__field gm__field--wide">
            <span>Notes</span>
            <textarea v-model="form.notes" rows="2" />
          </label>
        </div>
        <footer class="gm__panel-foot">
          <button type="button" class="btn btn-primary" :disabled="saving" @click="save">
            {{ saving ? 'Saving…' : 'Save grant' }}
          </button>
        </footer>
      </section>

      <section class="gm__panel">
        <header class="gm__panel-head">
          <div>
            <h2>Grants</h2>
            <p class="gm__muted">{{ filtered.length }} of {{ grants.length }}</p>
          </div>
          <v-text-field
            v-model="search"
            density="compact"
            variant="outlined"
            hide-details
            clearable
            prepend-inner-icon="mdi-magnify"
            placeholder="Search grants…"
            class="gm__search"
          />
        </header>

        <div v-if="!filtered.length" class="gm__empty">No grants match.</div>
        <div v-else class="gm__list">
          <article v-for="g in filtered" :key="g.id" class="gm__item">
            <div>
              <h3>{{ g.name }}</h3>
              <p class="gm__muted">
                {{ g.funder_name || '—' }}
                <template v-if="g.short_name"> · {{ g.short_name }}</template>
                <template v-if="g.budget_code"> · {{ g.budget_code }}</template>
                <template v-if="g.end_date"> · ends {{ String(g.end_date).slice(0, 10) }}</template>
              </p>
              <p class="gm__money">{{ usd(g.total_budget) }}</p>
            </div>
            <div v-if="canEdit" class="gm__item-actions">
              <button type="button" class="btn" :disabled="saving" @click="openEdit(g)">Edit</button>
              <button type="button" class="btn btn-danger" :disabled="saving" @click="remove(g)">Delete</button>
            </div>
          </article>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.gm { width: 100%; max-width: none; margin: 0; padding-bottom: 2.5rem; }
.gm__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.gm__title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem; }
.gm__header h1 { margin: 0; font-size: 1.5rem; }
.gm__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 48rem; line-height: 1.45; }
.gm__actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
.gm__link { color: #0b3b5c; font-weight: 600; text-decoration: none; }
.gm__banner {
  background: #fff7ed; border: 1px solid #fdba74; color: #9a3412;
  border-radius: 10px; padding: 0.7rem 0.9rem; margin: 0 0 0.85rem;
}
.gm__panel {
  background: #fff; border: 1px solid rgba(11,59,92,0.12); border-radius: 14px;
  padding: 1rem 1.1rem; margin-bottom: 1rem;
}
.gm__panel--form { border-color: rgba(11,59,92,0.28); box-shadow: 0 8px 24px rgba(11,59,92,0.06); }
.gm__panel-head {
  display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start;
  margin-bottom: 0.85rem; padding-bottom: 0.7rem; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.gm__panel-head h2 { margin: 0; font-size: 1.05rem; }
.gm__panel-foot { margin-top: 1rem; display: flex; justify-content: flex-end; }
.gm__search { max-width: 280px; min-width: 200px; }
.gm__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem 0.9rem; }
.gm__field { display: grid; gap: 0.3rem; font-size: 0.82rem; font-weight: 600; }
.gm__field--wide { grid-column: 1 / -1; }
.gm__field--check { display: flex; align-items: center; gap: 0.45rem; font-weight: 500; }
.gm__field input, .gm__field textarea, .gm__field select {
  border: 1px solid rgba(0,0,0,0.16); border-radius: 8px; padding: 0.5rem 0.6rem;
  font: inherit; font-weight: 400;
}
.gm__list { display: grid; gap: 0.55rem; }
.gm__item {
  display: flex; justify-content: space-between; gap: 0.75rem; align-items: flex-start;
  border: 1px solid rgba(0,0,0,0.08); border-radius: 12px; padding: 0.85rem 1rem; background: #f8fafc;
}
.gm__item h3 { margin: 0; font-size: 0.95rem; }
.gm__money { margin: 0.35rem 0 0; font-weight: 700; color: #0b3b5c; }
.gm__item-actions { display: flex; gap: 0.4rem; flex-shrink: 0; }
.gm__muted { color: #64748b; margin: 0.25rem 0 0; }
.gm__empty {
  background: #f8fafc; border: 1px dashed rgba(0,0,0,0.15); border-radius: 12px;
  padding: 1.25rem; color: #64748b;
}
.gm__ok { color: #027a48; }
.gm__error { color: #b42318; }
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: 8px;
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
.btn-danger { color: #b42318; }
@media (max-width: 720px) {
  .gm__header, .gm__item { flex-direction: column; }
}
</style>
