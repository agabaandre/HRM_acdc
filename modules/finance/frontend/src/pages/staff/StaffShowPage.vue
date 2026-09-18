<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { resolveAvatarUrl } from '@/lib/api'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import {
  createContract,
  fetchStaff,
  fetchStaffAuditTrail,
  fetchStaffFormLookups,
  updateContract,
  updateStaffBiodata,
  uploadStaffPassport,
  type StaffAuditTrailRow,
  type StaffBiodataPayload,
  type StaffContractPayload,
  type StaffContractRow,
  type StaffFormLookups,
  type StaffNextOfKinInput,
  type StaffSupervisorOption,
  type StaffUnitOption,
} from '@/lib/staffApi'
import { useAuthStore } from '@/stores/auth'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import StaffPayPanel from '@/components/staff/StaffPayPanel.vue'

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
const auth = useAuthStore()
const payPanelRef = ref<{ reload?: () => Promise<void> } | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const staff = ref<Record<string, unknown> | null>(null)
const contracts = ref<StaffContractRow[]>([])
const canManage = ref(false)
const canManageContracts = ref(false)
const auditTrail = ref<StaffAuditTrailRow[]>([])
const auditTrailLoading = ref(false)
const auditTrailError = ref<string | null>(null)
const auditStructured = ref(true)
const expandedAuditIds = ref<number[]>([])
const showContractDialog = ref(false)
const showBiodataDialog = ref(false)
const biodataSaving = ref(false)
const biodataError = ref<string | null>(null)
const biodataValidation = ref<string | null>(null)
const biodataServerErrors = ref<FieldErrors>({})
const biodataClientErrors = ref<FieldErrors>({})
const biodataLookupsLoading = ref(false)
const biodataPassportFile = ref<File | File[] | null>(null)
const biodataNextOfKin = ref<StaffNextOfKinInput[]>([
  { name: '', relationship_id: '', phone: '', email: '' },
  { name: '', relationship_id: '', phone: '', email: '' },
])

const titles = ['Dr', 'Prof', 'Rev', 'Mr', 'Mrs', 'Ms']
const genders = ['Male', 'Female', 'Other']

const biodataForm = reactive<StaffBiodataPayload>({
  SAPNO: '',
  title: '',
  fname: '',
  lname: '',
  oname: '',
  date_of_birth: '',
  gender: 'Male',
  nationality_id: '',
  initiation_date: '',
  tel_1: '',
  tel_2: '',
  whatsapp: '',
  work_email: '',
  private_email: '',
  physical_location: '',
})
const formMode = ref<'create' | 'edit'>('create')
const editingContractId = ref<number | null>(null)
const lookupsLoading = ref(false)
const lookups = ref<StaffFormLookups | null>(null)
const kinItems = computed(() =>
  (lookups.value?.kin_relationship_types || []).map((k) => ({
    title: k.name,
    value: k.id,
  })),
)
const passportUrl = computed(() => resolveAvatarUrl(String(staff.value?.passport_url || '')) || null)
const passportIsPdf = computed(() => !!staff.value?.passport_is_pdf)
const saving = ref(false)
const validationMessage = ref<string | null>(null)
const formError = ref<string | null>(null)
const serverErrors = ref<FieldErrors>({})
const clientErrors = ref<FieldErrors>({})
const contractFile = ref<File | File[] | null>(null)
const existingContractFileUrl = ref<string | null>(null)

function selectedContractFile(): File | null {
  const value = contractFile.value
  if (Array.isArray(value)) return value[0] ?? null
  return value
}

const staffId = computed(() => Number(route.params.id))
const canManagePay = computed(() => {
  if (!auth.isModuleEnabled('payroll')) return false
  const roleId = Number(auth.me?.profile?.role_id || 0)
  const isHr = !!auth.me?.profile?.is_hr || roleId === 20 || roleId === 22
  return (
    isHr ||
    !!auth.me?.profile?.is_system_admin ||
    roleId === 10 ||
    auth.hasPermission(PAYROLL_PERMS.MANAGE_STAFF_PAY) ||
    auth.hasPermission(17)
  )
})
const fullName = computed(() => {
  if (!staff.value) return 'Staff'
  return [staff.value.title, staff.value.fname, staff.value.oname, staff.value.lname]
    .filter(Boolean)
    .join(' ')
})
const photoUrl = computed(() => resolveAvatarUrl(String(staff.value?.photo_url || '')) || null)
const nextOfKin = computed(() => {
  const raw = staff.value?.next_of_kin
  return Array.isArray(raw) ? (raw as Array<Record<string, unknown>>) : []
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
  if (!lookups.value) return []
  const forDivision =
    form.division_id === ''
      ? []
      : lookups.value.units.filter((unit) => Number(unit.division_id) === Number(form.division_id))

  // Keep the current unit visible even when it belongs to another division
  // (legacy data often has mismatched division_id / unit_id pairs).
  const currentId = form.unit_id
  if (currentId !== '' && currentId != null) {
    const current = lookups.value.units.find((unit) => Number(unit.unit_id) === Number(currentId))
    if (current && !forDivision.some((unit) => Number(unit.unit_id) === Number(current.unit_id))) {
      return [current, ...forDivision]
    }
  }

  return forDivision
})

function supervisorLabel(item: StaffSupervisorOption): string {
  const lname = item.lname?.trim()
  const fname = item.fname?.trim()
  if (lname && fname) return `${lname}, ${fname}`
  if (lname) return lname
  if (fname) return fname
  return `#${item.staff_id}`
}

function statusTone(statusId: unknown): string {
  const id = Number(statusId)
  if (id === 1) return 'success'
  if (id === 2 || id === 7) return 'warning'
  if (id === 3 || id === 4) return 'error'
  return 'default'
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

function biodataFieldErrors(name: string): string[] {
  return [...(biodataClientErrors.value[name] ?? []), ...(biodataServerErrors.value[name] ?? [])]
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
  contractFile.value = null
  existingContractFileUrl.value = null
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
  contractFile.value = null
  existingContractFileUrl.value = contract.contract_file_url
    ? resolveAvatarUrl(contract.contract_file_url) || contract.contract_file_url
    : null
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
  contractFile.value = null
  existingContractFileUrl.value = null
}

async function loadAuditTrail() {
  if (!staffId.value) return
  auditTrailLoading.value = true
  auditTrailError.value = null
  try {
    const res = await fetchStaffAuditTrail(staffId.value, 100)
    auditTrail.value = res.data
    auditStructured.value = Boolean(res.meta?.structured_columns)
  } catch (e) {
    auditTrail.value = []
    auditTrailError.value = apiErrorMessage(e, 'Could not load audit trail')
  } finally {
    auditTrailLoading.value = false
  }
}

function toggleAuditDetails(id: number) {
  const idx = expandedAuditIds.value.indexOf(id)
  if (idx >= 0) {
    expandedAuditIds.value = expandedAuditIds.value.filter((x) => x !== id)
  } else {
    expandedAuditIds.value = [...expandedAuditIds.value, id]
  }
}

function eventLabel(eventType: string | null | undefined): string {
  const raw = String(eventType || '').replace(/^record_/, '')
  const map: Record<string, string> = {
    staff_create: 'Staff created',
    staff_biodata: 'Biodata updated',
    staff_email_enable: 'Email enabled',
    contract_create: 'Contract created',
    contract_update: 'Contract updated',
    ppa_supervisors: 'PPA supervisors sync',
  }
  return map[raw] || raw || 'Change'
}

function truncateUri(uri: string | null | undefined, max = 48): string {
  const value = String(uri || '')
  if (value.length <= max) return value || '—'
  return `${value.slice(0, max)}…`
}

async function load() {
  if (!staffId.value) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchStaff(staffId.value)
    staff.value = res.staff
    contracts.value = res.contracts
    canManage.value = Boolean(res.can_manage)
    canManageContracts.value = Boolean(res.can_manage_contracts)
    void loadAuditTrail()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load staff profile')
    staff.value = null
    contracts.value = []
    canManage.value = false
    canManageContracts.value = false
    auditTrail.value = []
  } finally {
    loading.value = false
  }
}

function emptyNokRows(): StaffNextOfKinInput[] {
  return [
    { name: '', relationship_id: '', phone: '', email: '' },
    { name: '', relationship_id: '', phone: '', email: '' },
  ]
}

function applyBiodataFromStaff() {
  const s = staff.value || {}
  biodataForm.SAPNO = String(s.SAPNO ?? s.sap_number ?? '')
  biodataForm.title = String(s.title ?? '')
  biodataForm.fname = String(s.fname ?? '')
  biodataForm.lname = String(s.lname ?? '')
  biodataForm.oname = String(s.oname ?? '')
  biodataForm.date_of_birth = String(s.date_of_birth ?? '').slice(0, 10)
  biodataForm.gender = String(s.gender ?? 'Male')
  biodataForm.nationality_id = numberOrBlank(s.nationality_id)
  biodataForm.initiation_date = String(s.initiation_date ?? '').slice(0, 10)
  biodataForm.tel_1 = String(s.tel_1 ?? '')
  biodataForm.tel_2 = String(s.tel_2 ?? '')
  biodataForm.whatsapp = String(s.whatsapp ?? '')
  biodataForm.work_email = String(s.work_email ?? '')
  biodataForm.private_email = String(s.private_email ?? '')
  biodataForm.physical_location = String(s.physical_location ?? '')
  biodataPassportFile.value = null
  const rows = emptyNokRows()
  const existing = Array.isArray(s.next_of_kin) ? (s.next_of_kin as Array<Record<string, unknown>>) : []
  existing.slice(0, 2).forEach((row, i) => {
    rows[i] = {
      name: String(row.name ?? ''),
      relationship_id: numberOrBlank(row.relationship_id),
      phone: String(row.phone ?? ''),
      email: String(row.email ?? ''),
    }
  })
  biodataNextOfKin.value = rows
}

async function openBiodataDialog() {
  biodataError.value = null
  biodataValidation.value = null
  biodataServerErrors.value = {}
  biodataClientErrors.value = {}
  applyBiodataFromStaff()
  showBiodataDialog.value = true
  if (!lookups.value) {
    biodataLookupsLoading.value = true
    try {
      lookups.value = await fetchStaffFormLookups()
    } catch (e) {
      biodataError.value = apiErrorMessage(e, 'Could not load form lookups')
    } finally {
      biodataLookupsLoading.value = false
    }
  }
}

function closeBiodataDialog() {
  showBiodataDialog.value = false
  biodataError.value = null
  biodataValidation.value = null
  biodataServerErrors.value = {}
  biodataClientErrors.value = {}
  biodataPassportFile.value = null
}

function validateBiodata(): boolean {
  const errors: FieldErrors = {}
  const requireText = (field: keyof StaffBiodataPayload, label: string) => {
    if (!String(biodataForm[field] ?? '').trim()) addError(errors, field, `${label} is required.`)
  }
  requireText('title', 'Title')
  requireText('fname', 'First name')
  requireText('lname', 'Last name')
  requireText('date_of_birth', 'Date of birth')
  requireText('gender', 'Gender')
  requireText('initiation_date', 'Initiation date')
  requireText('tel_1', 'Telephone 1')
  requireText('work_email', 'Work email')
  if (biodataForm.nationality_id === '' || Number(biodataForm.nationality_id) < 1) {
    addError(errors, 'nationality_id', 'Nationality is required.')
  }
  biodataClientErrors.value = errors
  return Object.keys(errors).length === 0
}

async function submitBiodata() {
  if (!staffId.value) return
  biodataError.value = null
  biodataValidation.value = null
  biodataServerErrors.value = {}
  successMessage.value = null

  if (!validateBiodata()) {
    biodataValidation.value = 'Please fix the highlighted fields.'
    return
  }

  biodataSaving.value = true
  try {
    const payload: StaffBiodataPayload = {
      ...biodataForm,
      nationality_id: Number(biodataForm.nationality_id),
      next_of_kin: biodataNextOfKin.value,
    }
    const updated = await updateStaffBiodata(staffId.value, payload)
    const passport = Array.isArray(biodataPassportFile.value)
      ? biodataPassportFile.value[0]
      : biodataPassportFile.value
    if (passport) {
      const media = await uploadStaffPassport(staffId.value, passport)
      updated.passport_url = media.passport_url
      updated.passport_is_pdf = media.passport_is_pdf
      updated.passport_biodata_page = media.filename
    }
    staff.value = updated
    successMessage.value = 'Biodata updated successfully.'
    closeBiodataDialog()
    void loadAuditTrail()
  } catch (e) {
    biodataServerErrors.value = validationErrors(e)
    biodataError.value = apiErrorMessage(e, 'Could not update biodata')
    if (errorStatus(e) === 422) {
      biodataValidation.value = 'Please fix the highlighted fields.'
    }
  } finally {
    biodataSaving.value = false
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

    const pdf = selectedContractFile()
    if (formMode.value === 'create') {
      await createContract(staffId.value, payload, pdf)
      successMessage.value =
        'Contract created. If payroll was copied from the previous contract, verify it before saving.'
    } else if (editingContractId.value) {
      await updateContract(staffId.value, editingContractId.value, payload, pdf)
      successMessage.value = 'Contract updated successfully.'
    }

    closeContractDialog()
    await load()
    await payPanelRef.value?.reload?.()
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
    <PortalPageChrome :title="fullName" lede="Profile, payroll, and contracts.">
      <template #tabs>
        <StaffSubnav />
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="successMessage" type="success" variant="tonal" class="mb-3" density="compact">
      {{ successMessage }}
    </v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="staff">
      <v-sheet border rounded class="pa-4 mb-4 biodata-card">
        <div class="d-flex flex-wrap align-start justify-space-between ga-3 mb-3">
          <div class="d-flex align-start ga-3">
            <v-avatar size="108" rounded="lg" class="biodata-avatar">
              <v-img v-if="photoUrl" :src="photoUrl" alt="" cover />
              <span v-else class="text-h6">{{ String(staff.fname || fullName || '?').slice(0, 1) }}</span>
            </v-avatar>
            <div>
              <div class="text-subtitle-1 font-weight-medium">{{ fullName }}</div>
              <div class="text-caption text-medium-emphasis">
                SAP {{ staff.SAPNO || staff.sap_number || '—' }}
                <span v-if="latestContract">
                  · {{ latestContract.job_name || '—' }} · {{ latestContract.division_name || '—' }}
                </span>
              </div>
            </div>
          </div>
          <v-chip
            v-if="latestContract"
            size="small"
            variant="tonal"
            :color="statusTone(latestContract.status_id)"
          >
            {{ latestContract.status_label || 'Contract' }}
          </v-chip>
        </div>

        <div class="d-flex align-center justify-space-between ga-2 mb-2">
          <div class="section-heading mb-0">
            <i class="fa-solid fa-user section-heading__icon" aria-hidden="true" />
            <span>Biodata</span>
          </div>
          <PortalBtn
            v-if="canManage"
            size="small"
            prepend-icon="mdi-account-edit-outline"
            @click="openBiodataDialog"
          >
            Edit biodata
          </PortalBtn>
        </div>
        <table class="detail-table">
          <tbody>
            <tr>
              <th>Gender</th>
              <td>{{ staff.gender || '—' }}</td>
              <th>Date of birth</th>
              <td>
                {{ staff.date_of_birth || '—' }}
                <span v-if="staff.age != null" class="text-medium-emphasis"> ({{ staff.age }} yrs)</span>
              </td>
            </tr>
            <tr>
              <th>Nationality</th>
              <td>{{ staff.nationality || '—' }}</td>
              <th>Region</th>
              <td>{{ staff.region_name || '—' }}</td>
            </tr>
            <tr>
              <th>Work email</th>
              <td>{{ staff.work_email || '—' }}</td>
              <th>Private email</th>
              <td>{{ staff.private_email || '—' }}</td>
            </tr>
            <tr>
              <th>Telephone</th>
              <td>{{ staff.tel_1 || '—' }}</td>
              <th>Alt / WhatsApp</th>
              <td>{{ staff.whatsapp || staff.tel_2 || '—' }}</td>
            </tr>
            <tr>
              <th>Initiation date</th>
              <td>{{ staff.initiation_date || '—' }}</td>
              <th>Duty station</th>
              <td>{{ latestContract?.duty_station_name || '—' }}</td>
            </tr>
            <tr>
              <th>Physical location</th>
              <td>{{ staff.physical_location || '—' }}</td>
              <th>Residential address</th>
              <td>{{ staff.residential_address_duty_station || '—' }}</td>
            </tr>
          </tbody>
        </table>

        <div class="section-heading mt-4 mb-2">
          <i class="fa-solid fa-people-roof section-heading__icon" aria-hidden="true" />
          <span>Next of kin</span>
        </div>
        <table v-if="nextOfKin.length" class="detail-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Relationship</th>
              <th>Phone</th>
              <th>Email</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(nok, idx) in nextOfKin" :key="idx">
              <td>{{ nok.name || '—' }}</td>
              <td>{{ nok.relationship_name || nok.relationship_id || '—' }}</td>
              <td>{{ nok.phone || '—' }}</td>
              <td>{{ nok.email || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div v-else class="text-caption text-medium-emphasis">Not recorded</div>
      </v-sheet>

      <StaffPayPanel
        v-if="canManagePay && staffId"
        ref="payPanelRef"
        :staff-id="staffId"
      />

      <section class="contract-mgmt mb-4">
        <div class="contract-mgmt__toolbar">
          <div>
            <h2 class="contract-mgmt__title">
              <i class="fa-solid fa-file-contract section-heading__icon" aria-hidden="true" />
              Contracts
            </h2>
            <p class="contract-mgmt__lede">
              Manage the current contract or renew with a new record. Previous contracts stay in history below.
            </p>
          </div>
          <div v-if="canManageContracts" class="contract-mgmt__actions">
            <v-btn
              color="primary"
              :loading="lookupsLoading"
              @click="openCreateDialog"
            >
              <i class="fa-solid fa-file-circle-plus me-2" aria-hidden="true" />
              Add / renew contract
            </v-btn>
            <v-btn
              v-if="latestContract"
              variant="outlined"
              color="primary"
              :loading="lookupsLoading"
              @click="openEditDialog(latestContract)"
            >
              <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true" />
              Edit current contract
            </v-btn>
          </div>
        </div>

        <v-sheet v-if="latestContract" border rounded class="contract-current pa-0 mb-4">
          <div class="contract-current__header">
            <div class="d-flex flex-wrap align-center ga-2">
              <span class="text-subtitle-1 font-weight-medium">Current contract</span>
              <v-chip
                size="small"
                variant="tonal"
                :color="statusTone(latestContract.status_id)"
              >
                {{ latestContract.status_label || 'Status unknown' }}
              </v-chip>
              <span class="text-caption text-medium-emphasis">
                #{{ latestContract.staff_contract_id }}
              </span>
            </div>
            <v-btn
              v-if="canManageContracts"
              size="small"
              color="primary"
              variant="tonal"
              :loading="lookupsLoading"
              @click="openEditDialog(latestContract)"
            >
              <i class="fa-solid fa-pen-to-square me-2" aria-hidden="true" />
              Edit
            </v-btn>
          </div>

          <table class="detail-table detail-table--flush">
            <tbody>
              <tr>
                <th>Division</th>
                <td>{{ latestContract.division_name || '—' }}</td>
                <th>Job</th>
                <td>{{ latestContract.job_name || '—' }}</td>
              </tr>
              <tr>
                <th>Acting job</th>
                <td>{{ latestContract.job_acting || '—' }}</td>
                <th>Grade</th>
                <td>{{ latestContract.grade || '—' }}</td>
              </tr>
              <tr>
                <th>Contract type</th>
                <td>{{ latestContract.contract_type || '—' }}</td>
                <th>Duty station</th>
                <td>{{ latestContract.duty_station_name || '—' }}</td>
              </tr>
              <tr>
                <th>Start date</th>
                <td>{{ latestContract.start_date || '—' }}</td>
                <th>End date</th>
                <td>{{ latestContract.end_date || '—' }}</td>
              </tr>
              <tr>
                <th>First supervisor</th>
                <td>{{ latestContract.first_supervisor_name || '—' }}</td>
                <th>Second supervisor</th>
                <td>{{ latestContract.second_supervisor_name || '—' }}</td>
              </tr>
              <tr>
                <th>Contracting institution</th>
                <td>{{ latestContract.contracting_institution || '—' }}</td>
                <th>Funder</th>
                <td>{{ latestContract.funder || '—' }}</td>
              </tr>
              <tr>
                <th>Signed contract</th>
                <td>
                  <a
                    v-if="latestContract.contract_file_url"
                    :href="resolveAvatarUrl(latestContract.contract_file_url) || latestContract.contract_file_url"
                    target="_blank"
                    rel="noopener"
                  >
                    View PDF
                  </a>
                  <span v-else class="text-medium-emphasis">Not uploaded</span>
                </td>
                <th>Comments</th>
                <td>{{ latestContract.comments || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </v-sheet>

        <v-alert
          v-else-if="canManageContracts"
          type="info"
          variant="tonal"
          density="compact"
          class="mb-4"
        >
          No contracts yet. Use <strong>Add / renew contract</strong> above to create the first record.
        </v-alert>

        <v-card variant="outlined">
          <v-card-title class="d-flex flex-wrap justify-space-between align-center ga-2">
            <span>Contract history</span>
            <v-chip size="small" variant="tonal" color="primary">
              {{ contracts.length }} contract(s)
            </v-chip>
          </v-card-title>
          <v-card-text class="pa-0">
            <v-table density="compact" class="contract-history-table">
              <thead>
                <tr>
                  <th>Division</th>
                  <th>Job</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Start</th>
                  <th>End</th>
                  <th>Supervisor</th>
                  <th>Signed PDF</th>
                  <th>Comments</th>
                  <th v-if="canManageContracts" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in contracts" :key="String(c.staff_contract_id)">
                  <td>
                    <span v-if="c === latestContract" class="contract-current-badge">Current</span>
                    {{ c.division_name || '—' }}
                  </td>
                  <td>{{ c.job_name || '—' }}</td>
                  <td>{{ c.contract_type || '—' }}</td>
                  <td>
                    <v-chip size="x-small" variant="tonal" :color="statusTone(c.status_id)">
                      {{ c.status_label || '—' }}
                    </v-chip>
                  </td>
                  <td>{{ c.start_date || '—' }}</td>
                  <td>{{ c.end_date || '—' }}</td>
                  <td>{{ c.first_supervisor_name || '—' }}</td>
                  <td>
                    <a
                      v-if="c.contract_file_url"
                      :href="resolveAvatarUrl(c.contract_file_url) || c.contract_file_url"
                      target="_blank"
                      rel="noopener"
                    >
                      PDF
                    </a>
                    <span v-else class="text-medium-emphasis">—</span>
                  </td>
                  <td>{{ c.comments || '—' }}</td>
                  <td v-if="canManageContracts" class="text-end">
                    <v-btn
                      size="small"
                      variant="outlined"
                      color="primary"
                      class="contract-edit-btn"
                      :loading="lookupsLoading && editingContractId === Number(c.staff_contract_id)"
                      @click="openEditDialog(c)"
                    >
                      <i class="fa-solid fa-pen-to-square me-1" aria-hidden="true" />
                      Edit
                    </v-btn>
                  </td>
                </tr>
                <tr v-if="!contracts.length">
                  <td :colspan="canManageContracts ? 10 : 9" class="text-medium-emphasis text-center py-6">
                    No contracts on file.
                  </td>
                </tr>
              </tbody>
            </v-table>
          </v-card-text>
        </v-card>

        <v-card variant="outlined" class="mt-4 staff-audit-trail">
          <v-card-title class="d-flex flex-wrap justify-space-between align-center ga-2">
            <div class="d-flex align-center ga-2">
              <i class="fa-solid fa-user-shield section-heading__icon" aria-hidden="true" />
              <span>Profile &amp; contract change trail</span>
            </div>
            <v-chip size="small" variant="tonal" color="secondary">
              {{ auditTrail.length }} record(s)
            </v-chip>
          </v-card-title>
          <v-card-subtitle class="pb-2">
            Compliance monitor for critical biodata, contract, and linked PPA supervisor changes on this employee file.
          </v-card-subtitle>
          <v-card-text class="pa-0">
            <v-alert
              v-if="auditTrailError"
              type="error"
              variant="tonal"
              density="compact"
              class="ma-3"
            >
              {{ auditTrailError }}
            </v-alert>
            <div v-else-if="auditTrailLoading" class="text-medium-emphasis pa-4">Loading audit trail…</div>
            <div v-else-if="!auditStructured" class="text-medium-emphasis pa-4">
              Structured audit columns are not available on <code>user_logs</code>, so change snapshots cannot be shown.
            </div>
            <div v-else-if="!auditTrail.length" class="text-medium-emphasis pa-4">
              No structured change records yet. Edits to biodata, contracts, or linked PPA supervisors will appear here.
            </div>
            <v-table v-else density="compact" class="staff-audit-table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Actor</th>
                  <th>Event</th>
                  <th>Target</th>
                  <th>HTTP</th>
                  <th>Route</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                <template v-for="entry in auditTrail" :key="String(entry.id)">
                  <tr>
                    <td class="text-no-wrap">{{ entry.created_at || '—' }}</td>
                    <td>
                      <div>{{ entry.actor_name || '—' }}</div>
                      <div v-if="entry.actor_email" class="text-caption text-medium-emphasis">
                        {{ entry.actor_email }}
                      </div>
                    </td>
                    <td>
                      <code class="staff-audit-code">{{ entry.event_type || '—' }}</code>
                      <div class="text-caption">{{ eventLabel(entry.event_type) }}</div>
                    </td>
                    <td>{{ entry.target_label || '—' }}</td>
                    <td>
                      <v-chip v-if="entry.http_method" size="x-small" variant="tonal">
                        {{ entry.http_method }}
                      </v-chip>
                      <span v-else>—</span>
                    </td>
                    <td>
                      <code class="staff-audit-code" :title="entry.request_uri || ''">
                        {{ truncateUri(entry.request_uri) }}
                      </code>
                    </td>
                    <td>
                      <v-btn
                        size="small"
                        variant="text"
                        color="primary"
                        @click="toggleAuditDetails(entry.id)"
                      >
                        {{ expandedAuditIds.includes(entry.id) ? 'Hide changes' : 'What changed' }}
                        <span v-if="entry.changes?.length" class="ms-1">({{ entry.changes.length }})</span>
                      </v-btn>
                    </td>
                  </tr>
                  <tr v-if="expandedAuditIds.includes(entry.id)">
                    <td colspan="7" class="staff-audit-details">
                      <table v-if="entry.changes?.length" class="staff-audit-diff">
                        <thead>
                          <tr>
                            <th>Field</th>
                            <th class="staff-audit-diff__before">Before</th>
                            <th class="staff-audit-diff__after">After</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="change in entry.changes" :key="`${entry.id}-${change.field}`">
                            <td>
                              <code>{{ change.field }}</code>
                              <v-chip
                                v-if="change.type !== 'changed'"
                                size="x-small"
                                class="ms-1"
                                :color="change.type === 'added' ? 'success' : 'error'"
                                variant="tonal"
                              >
                                {{ change.type }}
                              </v-chip>
                            </td>
                            <td class="staff-audit-diff__before">
                              <pre>{{ change.old || '—' }}</pre>
                            </td>
                            <td class="staff-audit-diff__after">
                              <pre>{{ change.new || '—' }}</pre>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <div v-else class="text-caption text-medium-emphasis">
                        No field-level diff for this event (create snapshot or identical values).
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </v-table>
          </v-card-text>
        </v-card>
      </section>
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
                          :item-title="(item) => String(item.label || item.job_name || '')"
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
                          :item-title="(item) => String(item.label || item.job_acting || '')"
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
                          :item-title="(item) => String(item.label || item.contract_type || '')"
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
                        <UDateInput
                          v-model="form.start_date"
                          label="Start date"
                          density="comfortable"
                          :error-messages="fieldErrors('start_date')"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" sm="4">
                        <UDateInput
                          v-model="form.end_date"
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
                      <v-col cols="12">
                        <v-file-input
                          v-model="contractFile"
                          label="Signed contract (PDF, optional)"
                          accept="application/pdf,.pdf"
                          prepend-icon=""
                          prepend-inner-icon="mdi-file-pdf-box"
                          show-size
                          clearable
                          density="comfortable"
                          :error-messages="fieldErrors('contract_file')"
                          hint="Upload the physical signed contract scan. Max 10MB PDF."
                          persistent-hint
                        />
                        <div v-if="existingContractFileUrl && !selectedContractFile()" class="text-caption mt-1">
                          Current file:
                          <a :href="existingContractFileUrl" target="_blank" rel="noopener">View signed contract</a>
                        </div>
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
          <PortalBtn :loading="saving" @click="submitContract">{{ saveButtonLabel }}</PortalBtn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="showBiodataDialog" max-width="920" scrollable persistent>
      <v-card>
        <v-card-title class="d-flex align-center justify-space-between">
          <span>Update biodata</span>
          <v-btn icon="mdi-close" variant="text" @click="closeBiodataDialog" />
        </v-card-title>
        <v-card-text>
          <v-alert v-if="biodataError" type="error" variant="tonal" class="mb-3" density="compact">
            {{ biodataError }}
          </v-alert>
          <v-alert v-if="biodataValidation" type="warning" variant="tonal" class="mb-3" density="compact">
            {{ biodataValidation }}
          </v-alert>
          <div v-if="biodataLookupsLoading" class="text-medium-emphasis">Loading…</div>
          <form v-else @submit.prevent="submitBiodata">
            <v-row>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.SAPNO"
                  label="SAP number"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('SAPNO')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="biodataForm.title"
                  :items="titles"
                  label="Title"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('title')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="biodataForm.gender"
                  :items="genders"
                  label="Gender"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('gender')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.fname"
                  label="First name"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('fname')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.oname"
                  label="Other name"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.lname"
                  label="Last name"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('lname')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <UDateInput
                  v-model="biodataForm.date_of_birth"
                  label="Date of birth"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('date_of_birth')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <UDateInput
                  v-model="biodataForm.initiation_date"
                  label="Initiation date"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('initiation_date')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-select
                  v-model="biodataForm.nationality_id"
                  :items="lookups?.nationalities ?? []"
                  item-title="nationality"
                  item-value="nationality_id"
                  label="Nationality"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('nationality_id')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.tel_1"
                  label="Telephone 1"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('tel_1')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.tel_2"
                  label="Telephone 2"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="4">
                <v-text-field
                  v-model="biodataForm.whatsapp"
                  label="WhatsApp"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="biodataForm.work_email"
                  label="Work email"
                  type="email"
                  density="comfortable"
                  :error-messages="biodataFieldErrors('work_email')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="biodataForm.private_email"
                  label="Private email"
                  type="email"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="biodataForm.physical_location"
                  label="Physical location"
                  density="comfortable"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12">
                <div class="text-subtitle-2 mb-2">Passport biodata (optional)</div>
                <div v-if="passportUrl" class="mb-2 text-body-2">
                  Current:
                  <a :href="passportUrl" target="_blank" rel="noopener">
                    {{ passportIsPdf ? 'View PDF' : 'View image' }}
                  </a>
                </div>
                <v-file-input
                  v-model="biodataPassportFile"
                  label="Replace / upload passport biodata page"
                  accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,application/pdf"
                  prepend-icon="mdi-card-account-details-outline"
                  show-size
                  density="comfortable"
                  hint="Image or PDF, max 4MB"
                  persistent-hint
                  :error-messages="biodataFieldErrors('passport')"
                  hide-details="auto"
                />
              </v-col>
              <v-col cols="12">
                <div class="text-subtitle-2 mb-2">Next of kin (optional)</div>
                <div v-for="(row, idx) in biodataNextOfKin" :key="idx" class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">
                    {{ idx === 0 ? 'Primary' : 'Secondary' }}
                  </div>
                  <v-row density="compact">
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="row.name"
                        label="Full name"
                        density="comfortable"
                        :error-messages="biodataFieldErrors(`next_of_kin.${idx}`)"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-select
                        v-model="row.relationship_id"
                        :items="kinItems"
                        label="Relationship"
                        clearable
                        density="comfortable"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="row.phone"
                        label="Phone"
                        density="comfortable"
                        :error-messages="biodataFieldErrors(`next_of_kin.${idx}.phone`)"
                        hide-details="auto"
                      />
                    </v-col>
                    <v-col cols="12" sm="6">
                      <v-text-field
                        v-model="row.email"
                        label="Email"
                        type="email"
                        density="comfortable"
                        :error-messages="biodataFieldErrors(`next_of_kin.${idx}.email`)"
                        hide-details="auto"
                      />
                    </v-col>
                  </v-row>
                </div>
              </v-col>
            </v-row>
          </form>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeBiodataDialog">Cancel</v-btn>
          <PortalBtn :loading="biodataSaving" @click="submitBiodata">Save biodata</PortalBtn>
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

.contract-mgmt__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 1rem 1.1rem;
  border: 1px solid rgba(58, 71, 82, 0.14);
  border-radius: 0.5rem;
  background: linear-gradient(180deg, #f7fafc 0%, #ffffff 100%);
}
.contract-mgmt__title {
  margin: 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 1.15rem;
  font-weight: 600;
  color: #3a4752;
}
.contract-mgmt__lede {
  margin: 0.25rem 0 0;
  max-width: 36rem;
  font-size: 0.875rem;
  color: rgba(58, 71, 82, 0.72);
}
.contract-mgmt__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.contract-current {
  background: #fff;
  overflow: hidden;
}
.contract-current__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.85rem 1rem;
  border-bottom: 1px solid rgba(58, 71, 82, 0.1);
  background: #f7faf8;
}
.contract-current-badge {
  display: inline-block;
  margin-right: 0.4rem;
  padding: 0.15rem 0.45rem;
  border-radius: 0.3rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #065f2c;
  background: #bbf7d0;
  border: 1px solid rgba(6, 95, 44, 0.28);
}
.contract-history-table :deep(.v-chip) {
  font-weight: 650;
}
.contract-edit-btn {
  min-width: 5.5rem;
}
.contract-history-table :deep(th) {
  white-space: nowrap;
}
.biodata-card {
  width: 100%;
  background: #fff;
}
.biodata-avatar {
  border: 1px solid rgba(58, 71, 82, 0.14);
  background: #f1f5f9;
}
.biodata-avatar :deep(.v-img__img) {
  object-fit: cover;
  object-position: center 26%;
}
.section-heading {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.95rem;
  font-weight: 600;
  color: #3a4752;
}
.section-heading__icon {
  color: #119a48;
  font-size: 0.95rem;
}
.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.detail-table th,
.detail-table td {
  padding: 0.55rem 0.75rem;
  border: 1px solid rgba(58, 71, 82, 0.12);
  text-align: left;
  vertical-align: top;
}
.detail-table th {
  width: 12rem;
  font-weight: 600;
  color: #3a4752;
  background: #f3f7f4;
  white-space: nowrap;
}
.detail-table thead th {
  width: auto;
  background: #eef5f0;
}
.detail-table td {
  color: #3a4752;
  font-weight: 500;
}
.detail-table--flush {
  border-left: 0;
  border-right: 0;
}
.detail-table--flush th:first-child,
.detail-table--flush td:first-child {
  border-left: 0;
}
.detail-table--flush th:last-child,
.detail-table--flush td:last-child {
  border-right: 0;
}
.detail-table--flush tr:last-child th,
.detail-table--flush tr:last-child td {
  border-bottom: 0;
}
@media (max-width: 800px) {
  .detail-table,
  .detail-table tbody,
  .detail-table tr,
  .detail-table th,
  .detail-table td {
    display: block;
    width: 100%;
  }
  .detail-table tr {
    border-bottom: 1px solid rgba(58, 71, 82, 0.12);
    margin-bottom: 0.35rem;
  }
  .detail-table th {
    border-bottom: 0;
    padding-bottom: 0.15rem;
  }
  .detail-table td {
    border-top: 0;
    padding-top: 0.15rem;
  }
}
.staff-audit-table :deep(th) {
  white-space: nowrap;
}
.staff-audit-code {
  font-size: 0.72rem;
  word-break: break-all;
}
.staff-audit-details {
  background: #f8fafc;
  padding: 0.75rem 1rem !important;
}
.staff-audit-diff {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8rem;
}
.staff-audit-diff th,
.staff-audit-diff td {
  border: 1px solid rgba(58, 71, 82, 0.14);
  padding: 0.4rem 0.55rem;
  vertical-align: top;
}
.staff-audit-diff__before {
  background: rgba(220, 53, 69, 0.07);
}
.staff-audit-diff__after {
  background: rgba(25, 135, 84, 0.08);
}
.staff-audit-diff pre {
  margin: 0;
  white-space: pre-wrap;
  word-break: break-word;
  font-size: 0.72rem;
  max-height: 10rem;
  overflow: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
</style>
