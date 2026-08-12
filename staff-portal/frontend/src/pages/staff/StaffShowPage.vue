<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  createContract,
  fetchStaff,
  fetchStaffFormLookups,
  updateContract,
  type StaffContractPayload,
  type StaffContractRow,
  type StaffFormLookups,
  type StaffSupervisorOption,
  type StaffUnitOption,
} from '@/lib/staffApi'

type FieldErrors = Record<string, string[]>
type ApiFailure = {
  response?: {
    status?: number
    data?: {
      errors?: FieldErrors
      message?: string
    }
  }
}

const route = useRoute()
const loading = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const staff = ref<Record<string, unknown> | null>(null)
const contracts = ref<StaffContractRow[]>([])
const canManageContracts = ref(false)
const showContractDialog = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const editingContractId = ref<number | null>(null)
const lookupsLoading = ref(false)
const lookups = ref<StaffFormLookups | null>(null)
const saving = ref(false)
const validationMessage = ref<string | null>(null)
const formError = ref<string | null>(null)
const serverErrors = ref<FieldErrors>({})
const clientErrors = ref<FieldErrors>({})

const staffId = computed(() => Number(route.params.id))
const fullName = computed(() => {
  if (!staff.value) return 'Staff'
  return [staff.value.title, staff.value.fname, staff.value.oname, staff.value.lname]
    .filter(Boolean)
    .join(' ')
})
const dialogTitle = computed(() =>
  formMode.value === 'create'
    ? 'Add / renew contract'
    : `Edit contract #${editingContractId.value ?? ''}`,
)
const saveButtonLabel = computed(() =>
  formMode.value === 'create' ? 'Save new contract' : 'Save contract',
)
const latestContract = computed(() => contracts.value[0] ?? null)
const statusOptions = computed(() => {
  const allStatuses = lookups.value?.statuses ?? []
  const allowed = new Set<number>([1, 4, 7])
  if (formMode.value === 'edit') {
    const currentStatus = Number(currentContract.value?.status_id ?? 0)
    if (currentStatus > 0) allowed.add(currentStatus)
  }

  return allStatuses.filter((item) => allowed.has(Number(item.status_id)))
})
const currentContract = computed(() =>
  contracts.value.find((item) => Number(item.staff_contract_id) === editingContractId.value) ?? null,
)

const form = reactive<StaffContractPayload>({
  job_id: '',
  job_acting_id: '',
  grade_id: '',
  contracting_institution_id: '',
  funder_id: '',
  first_supervisor: '',
  second_supervisor: '',
  contract_type_id: '',
  duty_station_id: '',
  division_id: '',
  unit_id: '',
  other_associated_divisions: [],
  start_date: '',
  end_date: '',
  status_id: 1,
  comments: '',
})

const unitOptions = computed<StaffUnitOption[]>(() => {
  if (!lookups.value || form.division_id === '') return []
  return lookups.value.units.filter((unit) => Number(unit.division_id) === Number(form.division_id))
})

watch(
  () => form.division_id,
  () => {
    if (
      form.unit_id !== '' &&
      form.unit_id != null &&
      !unitOptions.value.some((unit) => Number(unit.unit_id) === Number(form.unit_id))
    ) {
      form.unit_id = ''
    }
  },
)

function supervisorLabel(item: StaffSupervisorOption): string {
  const lname = item.lname?.trim()
  const fname = item.fname?.trim()
  if (lname && fname) return `${lname}, ${fname}`
  if (lname) return lname
  if (fname) return fname
  return `#${item.staff_id}`
}

function errorStatus(cause: unknown): number | null {
  const status = (cause as ApiFailure)?.response?.status
  return typeof status === 'number' ? status : null
}

function validationErrors(cause: unknown): FieldErrors {
  const errors = (cause as ApiFailure)?.response?.data?.errors
  return errors && typeof errors === 'object' ? errors : {}
}

function fieldErrors(name: string): string[] {
  return [...(clientErrors.value[name] ?? []), ...(serverErrors.value[name] ?? [])]
}

function addError(target: FieldErrors, field: string, message: string) {
  if (!target[field]) target[field] = []
  target[field].push(message)
}

function parseDate(value: string): Date | null {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function nullableNumber(value: number | '' | null | undefined): number | null {
  return value === '' || value == null ? null : Number(value)
}

function numberOrBlank(value: unknown): number | '' {
  const number = Number(value)
  return Number.isFinite(number) && number > 0 ? number : ''
}

function parseDivisionIds(value: unknown): number[] {
  if (Array.isArray(value)) return value.map((item) => Number(item)).filter((item) => item > 0)
  if (typeof value !== 'string' || value.trim() === '') return []

  try {
    const parsed = JSON.parse(value)
    return Array.isArray(parsed) ? parsed.map((item) => Number(item)).filter((item) => item > 0) : []
  } catch {
    return []
  }
}

function applyFormValues(values: StaffContractPayload) {
  form.job_id = values.job_id
  form.job_acting_id = values.job_acting_id ?? ''
  form.grade_id = values.grade_id
  form.contracting_institution_id = values.contracting_institution_id
  form.funder_id = values.funder_id
  form.first_supervisor = values.first_supervisor
  form.second_supervisor = values.second_supervisor ?? ''
  form.contract_type_id = values.contract_type_id
  form.duty_station_id = values.duty_station_id
  form.division_id = values.division_id
  form.unit_id = values.unit_id ?? ''
  form.other_associated_divisions = [...(values.other_associated_divisions ?? [])]
  form.start_date = values.start_date
  form.end_date = values.end_date
  form.status_id = values.status_id
  form.comments = values.comments ?? ''
}

function contractToForm(contract: StaffContractRow | null, mode: 'create' | 'edit'): StaffContractPayload {
  const defaults = {
    job_id: numberOrBlank(contract?.job_id),
    job_acting_id: numberOrBlank(contract?.job_acting_id),
    grade_id: String(contract?.grade_id ?? ''),
    contracting_institution_id: numberOrBlank(contract?.contracting_institution_id),
    funder_id: numberOrBlank(contract?.funder_id),
    first_supervisor: numberOrBlank(contract?.first_supervisor),
    second_supervisor: numberOrBlank(contract?.second_supervisor),
    contract_type_id: numberOrBlank(contract?.contract_type_id),
    duty_station_id: numberOrBlank(contract?.duty_station_id),
    division_id: numberOrBlank(contract?.division_id),
    unit_id: numberOrBlank(contract?.unit_id),
    other_associated_divisions: parseDivisionIds(contract?.other_associated_divisions),
    start_date: String(contract?.start_date ?? ''),
    end_date: String(contract?.end_date ?? ''),
    status_id: numberOrBlank(contract?.status_id),
    comments: String(contract?.comments ?? ''),
  } satisfies StaffContractPayload

  if (mode === 'create') {
    return {
      ...defaults,
      start_date: '',
      end_date: '',
      status_id: 1,
      comments: '',
    }
  }

  return defaults
}

function validateForm(): boolean {
  const errors: FieldErrors = {}
  const requireText = (field: string, value: string, label: string) => {
    if (!value.trim()) addError(errors, field, `${label} is required.`)
  }
  const requireChoice = (field: string, value: number | string | '' | null, label: string) => {
    if (value === '' || value == null) addError(errors, field, `${label} is required.`)
  }

  requireChoice('job_id', form.job_id, 'Job')
  requireText('grade_id', form.grade_id, 'Grade')
  requireChoice('contracting_institution_id', form.contracting_institution_id, 'Contracting institution')
  requireChoice('funder_id', form.funder_id, 'Funder')
  requireChoice('first_supervisor', form.first_supervisor, 'First supervisor')
  requireChoice('contract_type_id', form.contract_type_id, 'Contract type')
  requireChoice('duty_station_id', form.duty_station_id, 'Duty station')
  requireChoice('division_id', form.division_id, 'Division')
  requireText('start_date', form.start_date, 'Start date')
  requireText('end_date', form.end_date, 'End date')
  requireChoice('status_id', form.status_id, 'Contract status')

  const startDate = parseDate(form.start_date)
  const endDate = parseDate(form.end_date)
  if (startDate && endDate && endDate <= startDate) {
    addError(errors, 'end_date', 'End date must be later than start date.')
  }

  clientErrors.value = errors
  return Object.keys(errors).length === 0
}

async function ensureLookups() {
  if (lookups.value || lookupsLoading.value) return
  lookupsLoading.value = true
  try {
    lookups.value = await fetchStaffFormLookups()
  } catch (cause) {
    formError.value = apiErrorMessage(cause, 'Could not load contract form lookups')
  } finally {
    lookupsLoading.value = false
  }
}

async function openCreateDialog() {
  formMode.value = 'create'
  editingContractId.value = null
  validationMessage.value = null
  formError.value = null
  serverErrors.value = {}
  clientErrors.value = {}
  await ensureLookups()
  if (!lookups.value) return
  applyFormValues(contractToForm(latestContract.value, 'create'))
  showContractDialog.value = true
}

async function openEditDialog(contract: StaffContractRow) {
  formMode.value = 'edit'
  editingContractId.value = Number(contract.staff_contract_id)
  validationMessage.value = null
  formError.value = null
  serverErrors.value = {}
  clientErrors.value = {}
  await ensureLookups()
  if (!lookups.value) return
  applyFormValues(contractToForm(contract, 'edit'))
  showContractDialog.value = true
}

function closeContractDialog() {
  showContractDialog.value = false
  validationMessage.value = null
  formError.value = null
  serverErrors.value = {}
  clientErrors.value = {}
}

async function load() {
  if (!staffId.value) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchStaff(staffId.value)
    staff.value = res.staff
    contracts.value = res.contracts
    canManageContracts.value = Boolean(res.can_manage_contracts)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff profile')
    staff.value = null
    contracts.value = []
    canManageContracts.value = false
  } finally {
    loading.value = false
  }
}

async function submitContract() {
  if (!staffId.value) return

  successMessage.value = null
  formError.value = null
  validationMessage.value = null
  serverErrors.value = {}

  if (!validateForm()) {
    validationMessage.value = 'Please fix the highlighted fields.'
    return
  }

  saving.value = true
  try {
    const payload: StaffContractPayload = {
      job_id: Number(form.job_id),
      job_acting_id: nullableNumber(form.job_acting_id),
      grade_id: form.grade_id,
      contracting_institution_id: Number(form.contracting_institution_id),
      funder_id: Number(form.funder_id),
      first_supervisor: Number(form.first_supervisor),
      second_supervisor: nullableNumber(form.second_supervisor),
      contract_type_id: Number(form.contract_type_id),
      duty_station_id: Number(form.duty_station_id),
      division_id: Number(form.division_id),
      unit_id: nullableNumber(form.unit_id),
      other_associated_divisions: (form.other_associated_divisions ?? []).map(Number),
      start_date: form.start_date,
      end_date: form.end_date,
      status_id: Number(form.status_id),
      comments: form.comments?.trim() ?? '',
    }

    if (formMode.value === 'create') {
      await createContract(staffId.value, payload)
      successMessage.value = 'Contract created successfully.'
    } else if (editingContractId.value) {
      await updateContract(staffId.value, editingContractId.value, payload)
      successMessage.value = 'Contract updated successfully.'
    }

    closeContractDialog()
    await load()
  } catch (cause) {
    if (errorStatus(cause) === 422) {
      serverErrors.value = validationErrors(cause)
      validationMessage.value = 'Please fix the highlighted fields.'
      return
    }

    formError.value = apiErrorMessage(
      cause,
      formMode.value === 'create' ? 'Could not create contract' : 'Could not update contract',
    )
  } finally {
    saving.value = false
  }
}

watch(staffId, () => void load())
onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome :title="fullName" lede="Profile and contracts.">
      <template #actions>
        <div class="d-flex ga-2">
          <v-btn
            v-if="staff && canManageContracts"
            size="small"
            color="primary"
            :loading="lookupsLoading"
            @click="openCreateDialog"
          >
            Add / renew contract
          </v-btn>
          <RouterLink to="/staff" style="text-decoration:none">
            <v-btn size="small" variant="outlined">Back to directory</v-btn>
          </RouterLink>
        </div>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-3" density="compact">
      {{ successMessage }}
    </v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="staff">
      <v-row dense class="mb-4">
        <v-col cols="12" md="6">
          <v-sheet border rounded class="pa-3">
            <div class="text-subtitle-2 mb-2">Profile</div>
            <dl class="staff-dl">
              <div><dt>Email</dt><dd>{{ staff.work_email || '—' }}</dd></div>
              <div><dt>Gender</dt><dd>{{ staff.gender || '—' }}</dd></div>
              <div><dt>DOB</dt><dd>{{ staff.date_of_birth || '—' }}</dd></div>
              <div><dt>Nationality</dt><dd>{{ staff.nationality || '—' }}</dd></div>
              <div><dt>Phone</dt><dd>{{ staff.tel_1 || staff.tel_2 || '—' }}</dd></div>
              <div><dt>SAP</dt><dd>{{ staff.SAPNO || staff.sap_number || '—' }}</dd></div>
            </dl>
          </v-sheet>
        </v-col>
      </v-row>

      <v-card variant="outlined">
        <v-card-title class="d-flex justify-space-between align-center">
          <span>Contract history</span>
          <v-chip size="small" variant="tonal">{{ contracts.length }} contract(s)</v-chip>
        </v-card-title>
        <v-card-text class="pa-0">
          <v-table density="compact">
            <thead>
              <tr>
                <th>Division</th>
                <th>Job</th>
                <th>Type</th>
                <th>Status</th>
                <th>Start</th>
                <th>End</th>
                <th>Supervisor</th>
                <th>Comments</th>
                <th v-if="canManageContracts" class="text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in contracts" :key="String(c.staff_contract_id)">
                <td>{{ c.division_name || '—' }}</td>
                <td>{{ c.job_name || '—' }}</td>
                <td>{{ c.contract_type || '—' }}</td>
                <td>{{ c.status_label || '—' }}</td>
                <td>{{ c.start_date || '—' }}</td>
                <td>{{ c.end_date || '—' }}</td>
                <td>{{ c.first_supervisor_name || '—' }}</td>
                <td>{{ c.comments || '—' }}</td>
                <td v-if="canManageContracts" class="text-right">
                  <v-btn size="x-small" variant="text" @click="openEditDialog(c)">Edit</v-btn>
                </td>
              </tr>
              <tr v-if="!contracts.length">
                <td :colspan="canManageContracts ? 9 : 8" class="text-medium-emphasis">No contracts.</td>
              </tr>
            </tbody>
          </v-table>
        </v-card-text>
      </v-card>
    </template>

    <v-dialog v-model="showContractDialog" max-width="980">
      <v-card>
        <v-card-title>{{ dialogTitle }}</v-card-title>
        <v-card-text>
          <v-alert v-if="formError" type="error" variant="tonal" class="mb-3" density="compact">
            {{ formError }}
          </v-alert>
          <v-alert v-if="validationMessage" type="warning" variant="tonal" class="mb-3" density="compact">
            {{ validationMessage }}
          </v-alert>
          <div v-if="lookupsLoading" class="text-medium-emphasis">Loading…</div>
          <form v-else-if="lookups" @submit.prevent="submitContract">
            <v-row>
              <v-col cols="12" md="6">
                <v-card variant="outlined" class="mb-4">
                  <v-card-title>Contract information</v-card-title>
                  <v-card-text>
                    <v-row>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.job_id"
                          :items="lookups.jobs"
                          item-title="job_name"
                          item-value="job_id"
                          label="Job"
                          density="comfortable"
                          :error-messages="fieldErrors('job_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.job_acting_id"
                          :items="lookups.jobsActing"
                          item-title="job_acting"
                          item-value="job_acting_id"
                          label="Job acting"
                          density="comfortable"
                          clearable
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.grade_id"
                          :items="lookups.grades"
                          item-title="grade"
                          item-value="grade_id"
                          label="Grade"
                          density="comfortable"
                          :error-messages="fieldErrors('grade_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.contract_type_id"
                          :items="lookups.contractTypes"
                          item-title="contract_type"
                          item-value="contract_type_id"
                          label="Contract type"
                          density="comfortable"
                          :error-messages="fieldErrors('contract_type_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.contracting_institution_id"
                          :items="lookups.institutions"
                          item-title="contracting_institution"
                          item-value="contracting_institution_id"
                          label="Contracting institution"
                          density="comfortable"
                          :error-messages="fieldErrors('contracting_institution_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.funder_id"
                          :items="lookups.funders"
                          item-title="funder"
                          item-value="funder_id"
                          label="Funder"
                          density="comfortable"
                          :error-messages="fieldErrors('funder_id')"
                          hide-details="auto"
                        />
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" md="6">
                <v-card variant="outlined" class="mb-4">
                  <v-card-title>Assignment and supervisors</v-card-title>
                  <v-card-text>
                    <v-row>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.duty_station_id"
                          :items="lookups.dutyStations"
                          item-title="duty_station_name"
                          item-value="duty_station_id"
                          label="Duty station"
                          density="comfortable"
                          :error-messages="fieldErrors('duty_station_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.division_id"
                          :items="lookups.divisions"
                          item-title="division_name"
                          item-value="division_id"
                          label="Division"
                          density="comfortable"
                          :error-messages="fieldErrors('division_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.unit_id"
                          :items="unitOptions"
                          item-title="unit_name"
                          item-value="unit_id"
                          label="Unit"
                          density="comfortable"
                          clearable
                          :disabled="form.division_id === ''"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.other_associated_divisions"
                          :items="lookups.divisions"
                          item-title="division_name"
                          item-value="division_id"
                          label="Other associated divisions"
                          density="comfortable"
                          multiple
                          chips
                          clearable
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.first_supervisor"
                          :items="lookups.supervisors"
                          :item-title="supervisorLabel"
                          item-value="staff_id"
                          label="First supervisor"
                          density="comfortable"
                          :error-messages="fieldErrors('first_supervisor')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="6">
                        <v-select
                          v-model="form.second_supervisor"
                          :items="lookups.supervisors"
                          :item-title="supervisorLabel"
                          item-value="staff_id"
                          label="Second supervisor"
                          density="comfortable"
                          clearable
                          hide-details="auto"
                        />
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12">
                <v-card variant="outlined">
                  <v-card-title>Dates and status</v-card-title>
                  <v-card-text>
                    <v-row>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model="form.start_date"
                          type="date"
                          label="Start date"
                          density="comfortable"
                          :error-messages="fieldErrors('start_date')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-text-field
                          v-model="form.end_date"
                          type="date"
                          label="End date"
                          density="comfortable"
                          :error-messages="fieldErrors('end_date')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="4">
                        <v-select
                          v-model="form.status_id"
                          :items="statusOptions"
                          item-title="status"
                          item-value="status_id"
                          label="Contract status"
                          density="comfortable"
                          :error-messages="fieldErrors('status_id')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12">
                        <v-textarea
                          v-model="form.comments"
                          label="Comments"
                          rows="3"
                          density="comfortable"
                          hide-details="auto"
                        />
                      </v-col>
                    </v-row>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeContractDialog">Cancel</v-btn>
          <v-btn color="primary" :loading="saving" @click="submitContract">{{ saveButtonLabel }}</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.staff-dl {
  display: grid;
  gap: 0.35rem 1rem;
  margin: 0;
}
.staff-dl > div {
  display: grid;
  grid-template-columns: 7rem 1fr;
  gap: 0.5rem;
}
.staff-dl dt {
  color: rgba(var(--v-theme-on-surface), 0.6);
  font-size: 0.8rem;
}
.staff-dl dd {
  margin: 0;
  font-size: 0.9rem;
}
</style>
