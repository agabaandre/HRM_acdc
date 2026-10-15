<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { fetchPayrollSettings } from '@/lib/payrollApi'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import {
  createStaff,
  fetchStaffFormLookups,
  type StaffCreatePayload,
  type StaffFormLookups,
  type StaffNextOfKinInput,
  type StaffSupervisorOption,
  type StaffUnitOption,
} from '@/lib/staffApi'
import { useAuthStore } from '@/stores/auth'

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

const router = useRouter()
const auth = useAuthStore()

const loading = ref(false)
const saving = ref(false)
const forbidden = ref(false)
const error = ref<string | null>(null)
const validationMessage = ref<string | null>(null)
const lookups = ref<StaffFormLookups | null>(null)
const serverErrors = ref<FieldErrors>({})
const clientErrors = ref<FieldErrors>({})
const contractFile = ref<File | File[] | null>(null)
const passportFile = ref<File | File[] | null>(null)
const nextOfKin = ref<StaffNextOfKinInput[]>([
  { name: '', relationship_id: '', phone: '', email: '' },
  { name: '', relationship_id: '', phone: '', email: '' },
])
const includePay = ref(false)
const payCurrencies = ref<string[]>(['USD'])
const payForm = reactive({
  currency: 'USD',
  basic_salary: null as number | null,
  bank_name: '',
  bank_account: '',
  bank_branch: '',
  tax_identifier: '',
  pay_status: 'active',
  notes: '',
})
const payStatusItems = [
  { title: 'Active', value: 'active' },
  { title: 'Held', value: 'held' },
  { title: 'Terminated', value: 'terminated' },
]

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

const titles = ['Dr', 'Prof', 'Rev', 'Mr', 'Mrs', 'Ms']
const genders = ['Male', 'Female', 'Other']

const kinItems = computed(() =>
  (lookups.value?.kin_relationship_types || []).map((k) => ({
    title: k.name,
    value: k.id,
  })),
)
const form = reactive<StaffCreatePayload>({
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
  comments: '',
})

const unitOptions = computed<StaffUnitOption[]>(() => {
  if (!lookups.value || form.division_id === '') return []
  return lookups.value.units.filter((unit) => Number(unit.division_id) === Number(form.division_id))
})

watch(
  () => form.division_id,
  () => {
    // Only clear unit when the new division has units and the current one is not among them.
    if (
      form.unit_id !== '' &&
      form.unit_id != null &&
      unitOptions.value.length > 0 &&
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

function fieldErrors(name: string): string[] {
  return [...(clientErrors.value[name] ?? []), ...(serverErrors.value[name] ?? [])]
}

function errorStatus(cause: unknown): number | null {
  const status = (cause as ApiFailure)?.response?.status
  return typeof status === 'number' ? status : null
}

function validationErrors(cause: unknown): FieldErrors {
  const errors = (cause as ApiFailure)?.response?.data?.errors
  return errors && typeof errors === 'object' ? errors : {}
}

function parseDate(value: string): Date | null {
  if (!value) return null
  const parsed = new Date(value)
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

function addError(target: FieldErrors, field: string, message: string) {
  if (!target[field]) target[field] = []
  target[field].push(message)
}

function validate(): boolean {
  const errors: FieldErrors = {}

  const requireText = (field: string, value: string, label: string) => {
    if (!value.trim()) addError(errors, field, `${label} is required.`)
  }
  const requireChoice = (field: string, value: number | string | '' | null, label: string) => {
    if (value === '' || value == null) addError(errors, field, `${label} is required.`)
  }

  requireText('title', form.title, 'Title')
  requireText('fname', form.fname, 'First name')
  requireText('lname', form.lname, 'Last name')
  requireText('date_of_birth', form.date_of_birth, 'Date of birth')
  requireText('gender', form.gender, 'Gender')
  requireChoice('nationality_id', form.nationality_id, 'Nationality')
  requireText('initiation_date', form.initiation_date, 'Initiation date')
  requireText('tel_1', form.tel_1, 'Telephone 1')
  requireText('work_email', form.work_email, 'Work email')
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

  const birthDate = parseDate(form.date_of_birth)
  if (birthDate) {
    const adultCutoff = new Date()
    adultCutoff.setFullYear(adultCutoff.getFullYear() - 18)
    if (birthDate > adultCutoff) {
      addError(errors, 'date_of_birth', 'Staff must be at least 18 years old.')
    }
  }

  const startDate = parseDate(form.start_date)
  const endDate = parseDate(form.end_date)
  if (startDate && endDate && endDate <= startDate) {
    addError(errors, 'end_date', 'End date must be later than start date.')
  }

  if (includePay.value && canManagePay.value) {
    if (payForm.basic_salary == null || Number.isNaN(Number(payForm.basic_salary))) {
      addError(errors, 'pay.basic_salary', 'Basic salary is required when setting up payroll.')
    }
  }

  clientErrors.value = errors
  return Object.keys(errors).length === 0
}

function markForbidden(message: string) {
  forbidden.value = true
  error.value = message
}

async function loadLookups() {
  loading.value = true
  forbidden.value = false
  error.value = null
  try {
    const tasks: Promise<unknown>[] = [fetchStaffFormLookups()]
    if (canManagePay.value) {
      tasks.push(fetchPayrollSettings())
    }
    const results = await Promise.all(tasks)
    lookups.value = results[0] as StaffFormLookups
    if (canManagePay.value && results[1]) {
      const settings = results[1] as Awaited<ReturnType<typeof fetchPayrollSettings>>
      payCurrencies.value = settings.enabled_currencies?.length
        ? settings.enabled_currencies
        : [settings.default_currency]
      payForm.currency = settings.default_currency || payCurrencies.value[0] || 'USD'
    }
  } catch (cause) {
    if (errorStatus(cause) === 403) {
      markForbidden(apiErrorMessage(cause, 'You do not have permission to create staff.'))
    } else {
      error.value = apiErrorMessage(cause, 'Could not load form lookups')
    }
  } finally {
    loading.value = false
  }
}

function nullableNumber(value: number | '' | null | undefined): number | null {
  return value === '' || value == null ? null : Number(value)
}

async function onSubmit() {
  validationMessage.value = null
  error.value = null
  serverErrors.value = {}

  if (!validate()) {
    validationMessage.value = 'Please fix the highlighted fields.'
    return
  }

  saving.value = true
  try {
    const pdf = Array.isArray(contractFile.value) ? contractFile.value[0] : contractFile.value
    const passport = Array.isArray(passportFile.value) ? passportFile.value[0] : passportFile.value
    const created = await createStaff(
      {
        SAPNO: form.SAPNO?.trim(),
        title: form.title.trim(),
        fname: form.fname.trim(),
        lname: form.lname.trim(),
        oname: form.oname?.trim(),
        date_of_birth: form.date_of_birth,
        gender: form.gender,
        nationality_id: Number(form.nationality_id),
        initiation_date: form.initiation_date,
        tel_1: form.tel_1.trim(),
        tel_2: form.tel_2?.trim(),
        whatsapp: form.whatsapp?.trim(),
        work_email: form.work_email.trim(),
        private_email: form.private_email?.trim(),
        physical_location: form.physical_location?.trim(),
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
        other_associated_divisions: (form.other_associated_divisions || []).map(Number),
        start_date: form.start_date,
        end_date: form.end_date,
        comments: form.comments?.trim(),
        next_of_kin: nextOfKin.value,
        ...(includePay.value && canManagePay.value
          ? {
              pay: {
                currency: payForm.currency,
                basic_salary: Number(payForm.basic_salary),
                bank_name: payForm.bank_name || null,
                bank_account: payForm.bank_account || null,
                bank_branch: payForm.bank_branch || null,
                tax_identifier: payForm.tax_identifier || null,
                pay_status: payForm.pay_status,
                notes: payForm.notes || null,
              },
            }
          : {}),
      },
      { contractFile: pdf ?? null, passportFile: passport ?? null },
    )
    await router.push(`/staff/${created.staff_id}`)
  } catch (cause) {
    const status = errorStatus(cause)
    if (status === 403) {
      markForbidden(apiErrorMessage(cause, 'You do not have permission to create staff.'))
      return
    }
    if (status === 422) {
      serverErrors.value = validationErrors(cause)
      validationMessage.value = 'Please fix the highlighted fields.'
      return
    }
    error.value = apiErrorMessage(cause, 'Could not create staff')
  } finally {
    saving.value = false
  }
}

onMounted(() => void loadLookups())
</script>

<template>
  <div>
    <PortalPageChrome title="New staff" lede="Create biodata and the first active contract in one step.">
      <template #tabs>
        <StaffSubnav />
      </template>
    </PortalPageChrome>

    <v-alert v-if="forbidden" type="warning" variant="tonal" class="mb-3">
      {{ error || 'You do not have permission to create staff.' }}
    </v-alert>
    <v-alert v-else-if="error" type="error" variant="tonal" class="mb-3">
      {{ error }}
    </v-alert>
    <v-alert v-if="validationMessage" type="warning" variant="tonal" class="mb-3">
      {{ validationMessage }}
    </v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <form v-else-if="lookups && !forbidden" @submit.prevent="onSubmit">
      <v-row>
        <v-col cols="12" md="6">
          <v-card variant="outlined" class="mb-4">
            <v-card-title>Personal information</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.SAPNO" label="SAP number" density="comfortable" hide-details="auto" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.title"
                    :items="titles"
                    label="Title"
                    density="comfortable"
                    :error-messages="fieldErrors('title')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.fname"
                    label="First name"
                    density="comfortable"
                    :error-messages="fieldErrors('fname')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.lname"
                    label="Last name / surname"
                    density="comfortable"
                    :error-messages="fieldErrors('lname')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.oname" label="Other name" density="comfortable" hide-details="auto" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.gender"
                    :items="genders"
                    label="Gender"
                    density="comfortable"
                    :error-messages="fieldErrors('gender')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <UDateInput
                    v-model="form.date_of_birth"
                    label="Date of birth"
                    placeholder="Select date of birth"
                    density="comfortable"
                    :error-messages="fieldErrors('date_of_birth')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-select
                    v-model="form.nationality_id"
                    :items="lookups.nationalities"
                    item-title="nationality"
                    item-value="nationality_id"
                    label="Nationality"
                    density="comfortable"
                    :error-messages="fieldErrors('nationality_id')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12">
                  <UDateInput
                    v-model="form.initiation_date"
                    label="Initiation date"
                    placeholder="Select initiation date"
                    density="comfortable"
                    :error-messages="fieldErrors('initiation_date')"
                    hide-details="auto"
                  />
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="mb-4">
            <v-card-title>Contact information</v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.tel_1"
                    label="Telephone 1"
                    density="comfortable"
                    :error-messages="fieldErrors('tel_1')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.tel_2" label="Telephone 2" density="comfortable" hide-details="auto" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field v-model="form.whatsapp" label="WhatsApp" density="comfortable" hide-details="auto" />
                </v-col>
                <v-col cols="12" sm="6">
                  <v-text-field
                    v-model="form.work_email"
                    label="Work email"
                    type="email"
                    density="comfortable"
                    :error-messages="fieldErrors('work_email')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="form.private_email"
                    label="Personal / private email"
                    type="email"
                    density="comfortable"
                    :error-messages="fieldErrors('private_email')"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12">
                  <v-textarea
                    v-model="form.physical_location"
                    label="Physical location"
                    rows="3"
                    density="comfortable"
                    hide-details="auto"
                  />
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="mb-4">
            <v-card-title>Passport biodata (optional)</v-card-title>
            <v-card-text>
              <v-file-input
                v-model="passportFile"
                label="Passport biodata page"
                accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,application/pdf"
                prepend-icon="mdi-card-account-details-outline"
                show-size
                density="comfortable"
                hint="Image or PDF, max 4MB"
                persistent-hint
                :error-messages="fieldErrors('passport')"
                hide-details="auto"
              />
            </v-card-text>
          </v-card>

          <v-card variant="outlined" class="mb-4">
            <v-card-title>Next of kin (optional)</v-card-title>
            <v-card-text>
              <div v-for="(row, idx) in nextOfKin" :key="idx" class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  {{ idx === 0 ? 'Primary' : 'Secondary' }}
                </div>
                <v-row density="compact">
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="row.name"
                      label="Full name"
                      density="comfortable"
                      :error-messages="fieldErrors(`next_of_kin.${idx}`)"
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
                      :error-messages="fieldErrors(`next_of_kin.${idx}.phone`)"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="row.email"
                      label="Email"
                      type="email"
                      density="comfortable"
                      :error-messages="fieldErrors(`next_of_kin.${idx}.email`)"
                      hide-details="auto"
                    />
                  </v-col>
                </v-row>
              </div>
            </v-card-text>
          </v-card>

          <v-card v-if="canManagePay" variant="outlined" class="mb-4">
            <v-card-title>Payroll (optional)</v-card-title>
            <v-card-text>
              <v-switch
                v-model="includePay"
                label="Set up basic pay now"
                color="primary"
                density="compact"
                hide-details
                class="mb-3"
              />
              <template v-if="includePay">
                <p class="text-caption text-medium-emphasis mb-3">
                  Linked to the new contract on save. Basic salary is required; currency defaults from payroll
                  settings.
                </p>
                <v-row dense>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="payForm.currency"
                      :items="payCurrencies"
                      label="Currency"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model.number="payForm.basic_salary"
                      label="Basic salary"
                      type="number"
                      density="comfortable"
                      :error-messages="fieldErrors('pay.basic_salary')"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-select
                      v-model="payForm.pay_status"
                      :items="payStatusItems"
                      label="Pay status"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="6">
                    <v-text-field
                      v-model="payForm.tax_identifier"
                      label="Tax ID"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="payForm.bank_name"
                      label="Bank name"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="payForm.bank_account"
                      label="Account"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12" sm="4">
                    <v-text-field
                      v-model="payForm.bank_branch"
                      label="Branch"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                  <v-col cols="12">
                    <v-textarea
                      v-model="payForm.notes"
                      label="Notes"
                      rows="2"
                      density="comfortable"
                      hide-details="auto"
                    />
                  </v-col>
                </v-row>
              </template>
            </v-card-text>
          </v-card>
        </v-col>

        <v-col cols="12" md="6">
          <v-card variant="outlined" class="mb-4">
            <v-card-title>Contract details</v-card-title>
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
                  <UDateInput
                    v-model="form.start_date"
                    label="Start date"
                    placeholder="Select start date"
                    density="comfortable"
                    :error-messages="fieldErrors('start_date')"
                    hide-details="auto"
                    :max="form.end_date || undefined"
                  />
                </v-col>
                <v-col cols="12" sm="6">
                  <UDateInput
                    v-model="form.end_date"
                    label="End date"
                    placeholder="Select end date"
                    density="comfortable"
                    :error-messages="fieldErrors('end_date')"
                    hide-details="auto"
                    :min="form.start_date || undefined"
                  />
                </v-col>
                <v-col cols="12">
                  <div class="text-caption text-medium-emphasis mb-1">First contract status</div>
                  <v-chip color="success" variant="tonal">Active</v-chip>
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
                    hint="Optional scan of the physical signed contract. Max 10MB PDF."
                    persistent-hint
                  />
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

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
      </v-row>

      <div class="d-flex flex-wrap gap-2">
        <v-btn color="primary" type="submit" :loading="saving">Create staff</v-btn>
        <RouterLink to="/staff" style="text-decoration:none">
          <v-btn variant="outlined">Cancel</v-btn>
        </RouterLink>
      </div>
    </form>
  </div>
</template>
