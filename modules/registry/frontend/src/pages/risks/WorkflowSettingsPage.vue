<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useStaffDirectory, type StaffDirectoryOpt } from '@/composables/useStaffDirectory'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

const locale = useLocaleStore()

type Step = {
  id?: number
  step_order: number
  role: string
  staff_id: number | null
  staff_name?: string | null
  skippable_if_empty: boolean
}

type Workflow = {
  id: number
  name: string
  division_id: number | null
  directorate_id: number | null
  is_default: boolean
  steps: Step[]
}

type StaffOpt = StaffDirectoryOpt

type DivisionRow = {
  division_id: number
  division_short_name: string
  division_name: string
  directorate_id: number | null
  risk_focal_person: number | null
  risk_focal_person_name: string | null
  division_head: number | null
  division_head_name: string | null
  director_id: number | null
  director_name: string | null
  workflow: Workflow | null
}

const loading = ref(true)
const staffLoading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref('')
const globalDefault = ref<Workflow | null>(null)
const rows = ref<DivisionRow[]>([])
const staffOptions = ref<StaffOpt[]>([])
const staffLoaded = ref(false)
const { loadStaffDirectory } = useStaffDirectory()
const query = ref('')
const editing = ref<{
  workflowId: number
  name: string
  smFocal: number | null
  extra: number | null
  label: string
  isGlobal: boolean
  divisionId: number | null
} | null>(null)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return rows.value
  return rows.value.filter((r) => {
    const hay = `${r.division_short_name} ${r.division_name} ${r.division_id}`.toLowerCase()
    return hay.includes(q)
  })
})

function stepStaff(wf: Workflow | null | undefined, role: string): number | null {
  const step = wf?.steps?.find((s) => s.role === role)
  return step?.staff_id ?? null
}

function stepStaffName(wf: Workflow | null | undefined, role: string): string {
  const step = wf?.steps?.find((s) => s.role === role)
  if (!step?.staff_id) return locale.t('rr.none_dash', '—')
  return step.staff_name || `Staff #${step.staff_id}`
}

function staffLabel(id: number | null | undefined, fallbackName?: string | null): string {
  if (id == null || id < 1) return locale.t('rr.none_dash', '—')
  if (fallbackName) return fallbackName
  const opt = staffOptions.value.find((s) => s.value === id)
  return opt?.label || locale.t('rr.staff_number', 'Staff #{id}', { id })
}

async function ensureStaffOptions() {
  if (staffLoaded.value || staffLoading.value) return
  staffLoading.value = true
  try {
    staffOptions.value = await loadStaffDirectory()
    staffLoaded.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_staff_error', 'Could not load staff directory'))
  } finally {
    staffLoading.value = false
  }
}

async function openConfigure(row: DivisionRow | 'global') {
  const wf = row === 'global' ? globalDefault.value : row.workflow
  if (!wf) return
  editing.value = {
    workflowId: wf.id,
    name: wf.name,
    smFocal: stepStaff(wf, 'sm_focal'),
    extra: stepStaff(wf, 'extra'),
    label: row === 'global' ? locale.t('rr.global_default', 'Global default') : `${row.division_short_name || row.division_name}`,
    isGlobal: row === 'global',
    divisionId: row === 'global' ? null : row.division_id,
  }
  message.value = ''
  error.value = null
  void ensureStaffOptions()
}

function closeConfigure() {
  if (saving.value) return
  editing.value = null
}

function defaultSteps(smFocal: number | null, extra: number | null): Step[] {
  return [
    { step_order: 1, role: 'risk_focal', staff_id: null, skippable_if_empty: false },
    { step_order: 2, role: 'hod', staff_id: null, skippable_if_empty: false },
    { step_order: 3, role: 'director', staff_id: null, skippable_if_empty: true },
    { step_order: 4, role: 'sm_focal', staff_id: smFocal, skippable_if_empty: false },
    { step_order: 5, role: 'extra', staff_id: extra, skippable_if_empty: false },
  ]
}

function patchLocalWorkflow(workflowId: number, name: string, smFocal: number | null, extra: number | null) {
  const smName = smFocal ? staffLabel(smFocal) : null
  const extraName = extra ? staffLabel(extra) : null
  const apply = (wf: Workflow | null): Workflow | null => {
    if (!wf || wf.id !== workflowId) return wf
    const steps = defaultSteps(smFocal, extra).map((s) => {
      if (s.role === 'sm_focal') return { ...s, staff_name: smName }
      if (s.role === 'extra') return { ...s, staff_name: extraName }
      const prev = wf.steps.find((p) => p.role === s.role)
      return { ...s, staff_id: prev?.staff_id ?? null, staff_name: prev?.staff_name ?? null }
    })
    return { ...wf, name, steps }
  }
  globalDefault.value = apply(globalDefault.value)
  rows.value = rows.value.map((r) =>
    r.workflow?.id === workflowId ? { ...r, workflow: apply(r.workflow) } : r,
  )
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get<{
      data: {
        global_default: Workflow | null
        divisions: DivisionRow[]
      }
    }>('/api/v1/workflows/by-division')
    globalDefault.value = data.data.global_default
    rows.value = data.data.divisions
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_workflows_error', 'Could not load workflows'))
  } finally {
    loading.value = false
  }
}

async function saveConfigure() {
  if (!editing.value) return
  saving.value = true
  error.value = null
  message.value = ''
  try {
    const payload = {
      name: editing.value.name,
      steps: defaultSteps(editing.value.smFocal, editing.value.extra),
    }
    await api.put(`/api/v1/workflows/${editing.value.workflowId}`, payload)
    patchLocalWorkflow(
      editing.value.workflowId,
      editing.value.name,
      editing.value.smFocal,
      editing.value.extra,
    )
    message.value = locale.t('rr.saved_workflow', 'Saved default workflow for {label}.', { label: editing.value.label })
    editing.value = null
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.save_workflow_error', 'Could not save workflow'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.workflows_title', 'Approval workflows') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.workflows_sub', 'Each division has a default workflow (Risk Focal → HOD → Director → Senior Management → Extra). Org roles come from Staff Portal; configure Senior Management and Extra per division.') }}
        </p>
      </div>
      <button
        v-if="globalDefault && !loading"
        type="button"
        class="rr-btn rr-btn--primary"
        @click="openConfigure('global')"
      >
        {{ locale.t('rr.configure_default', 'Configure default') }}
      </button>
    </header>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>

    <RrSkeleton v-if="loading" variant="table" :rows="8" />

    <template v-else>
      <section v-if="globalDefault" class="rr-card">
        <div class="rr-card-head">
          <div>
            <h2>{{ locale.t('rr.global_fallback', 'Global fallback') }}</h2>
            <p class="rr-muted">{{ locale.t('rr.global_fallback_help', 'Used when a risk has no division, or the division workflow is missing.') }}</p>
          </div>
          <button type="button" class="rr-btn" @click="openConfigure('global')">{{ locale.t('rr.configure_default', 'Configure default') }}</button>
        </div>
        <p>
          <strong>{{ globalDefault.name }}</strong>
          · {{ locale.t('rr.senior_management', 'Senior Management') }} {{ stepStaffName(globalDefault, 'sm_focal') }}
          · {{ locale.t('rr.extra', 'Extra') }} {{ stepStaffName(globalDefault, 'extra') }}
        </p>
      </section>

      <section class="rr-card">
        <div class="rr-card-head">
          <h2>{{ locale.t('rr.divisions_heading', 'Divisions ({n})', { n: filtered.length }) }}</h2>
          <input v-model="query" class="rr-search" type="search" :placeholder="locale.t('rr.filter_divisions', 'Filter divisions…')" />
        </div>

        <div class="rr-table-wrap">
          <table class="rr-table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.col_short', 'Short') }}</th>
                <th>{{ locale.t('rr.col_name', 'Name') }}</th>
                <th>{{ locale.t('rr.role_risk_focal', 'Risk Focal') }}</th>
                <th>{{ locale.t('rr.role_hod', 'HOD') }}</th>
                <th>{{ locale.t('rr.role_director', 'Director') }}</th>
                <th>{{ locale.t('rr.senior_management', 'Senior Management') }}</th>
                <th>{{ locale.t('rr.extra', 'Extra') }}</th>
                <th>{{ locale.t('rr.col_workflow', 'Workflow') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in filtered" :key="r.division_id">
                <td>
                  <strong>{{ r.division_short_name || locale.t('rr.none_dash', '—') }}</strong>
                  <div class="rr-muted">#{{ r.division_id }}</div>
                </td>
                <td>{{ r.division_name || locale.t('rr.none_dash', '—') }}</td>
                <td>{{ staffLabel(r.risk_focal_person, r.risk_focal_person_name) }}</td>
                <td>{{ staffLabel(r.division_head, r.division_head_name) }}</td>
                <td>{{ staffLabel(r.director_id, r.director_name) }}</td>
                <td>{{ stepStaffName(r.workflow, 'sm_focal') }}</td>
                <td>{{ stepStaffName(r.workflow, 'extra') }}</td>
                <td>{{ r.workflow?.name || locale.t('rr.none_dash', '—') }}</td>
                <td>
                  <button type="button" class="rr-btn rr-btn--primary" :disabled="!r.workflow" @click="openConfigure(r)">
                    {{ locale.t('rr.configure', 'Configure') }}
                  </button>
                </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td colspan="9" class="rr-muted">{{ locale.t('rr.no_divisions_found', 'No divisions found.') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <v-dialog
      :model-value="editing != null"
      max-width="560"
      persistent
      @update:model-value="(v) => { if (!v) closeConfigure() }"
    >
      <v-card v-if="editing" class="rr-modal-card hd-form">
        <v-card-title class="rr-modal-title">
          {{ locale.t('rr.configure_default_for', 'Configure default · {label}', { label: editing.label }) }}
        </v-card-title>
        <v-divider />
        <v-card-text class="rr-modal-body">
          <div class="rr-form-fields">
            <UInput
              v-model="editing.name"
              :label="locale.t('rr.workflow_name', 'Workflow name')"
              :placeholder="locale.t('rr.workflow_name_ph', 'e.g. Global default')"
              :maxlength="128"
              icon="mdi-sitemap"
            />
            <USelectMenu
              v-model="editing.smFocal"
              :items="staffOptions"
              value-key="value"
              searchable
              clearable
              :label="locale.t('rr.senior_management', 'Senior Management')"
              :placeholder="locale.t('rr.search_staff_ph', 'Search staff by name or email…')"
              icon="mdi-account-tie"
              :disabled="staffLoading && !staffLoaded"
            />
            <USelectMenu
              v-model="editing.extra"
              :items="staffOptions"
              value-key="value"
              searchable
              clearable
              :label="locale.t('rr.extra_level', 'Extra level')"
              :placeholder="locale.t('rr.search_staff_ph', 'Search staff by name or email…')"
              icon="mdi-account-plus"
              :disabled="staffLoading && !staffLoaded"
            />
            <p v-if="staffLoading" class="rr-muted">{{ locale.t('rr.loading_staff', 'Loading staff directory…') }}</p>
            <p class="rr-muted">
              {{ locale.t('rr.workflow_org_roles_note', 'Risk Focal, HOD, and Director are taken from the division record in Staff Portal. Director is skippable when empty.') }}
            </p>
          </div>
        </v-card-text>
        <v-divider />
        <v-card-actions class="rr-modal-actions">
          <v-spacer />
          <button type="button" class="rr-btn" :disabled="saving" @click="closeConfigure">
            {{ locale.t('rr.cancel', 'Cancel') }}
          </button>
          <button type="button" class="rr-btn rr-btn--primary" :disabled="saving || staffLoading" @click="saveConfigure">
            {{ saving ? locale.t('rr.saving', 'Saving…') : locale.t('rr.save', 'Save') }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.rr-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 0.85rem;
}
.rr-card-head h2 { margin: 0; }
.rr-search {
  font: inherit;
  padding: 0.45rem 0.65rem;
  border: 1px solid #c5ced8;
  border-radius: 6px;
  min-width: 14rem;
}
.rr-modal-title {
  font-size: 1.1rem;
  font-weight: 700;
  color: #1a2b3c;
  padding: 1rem 1.15rem;
}
.rr-modal-body {
  padding-top: 1.15rem !important;
}
.rr-modal-body .rr-form-fields {
  display: grid;
  gap: 1rem;
}
.rr-modal-actions {
  padding: 0.85rem 1.15rem;
  gap: 0.5rem;
}
</style>
