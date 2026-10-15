<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  createStaffWageItem,
  deleteStaffWageItem,
  fetchPayrollSettings,
  fetchStaffPay,
  fetchWageTypes,
  saveStaffPay,
  type StaffPay,
  type StaffWageItem,
  type WageType,
} from '@/lib/payrollApi'

const props = defineProps<{
  staffId: number
}>()

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const pay = ref<StaffPay | null>(null)
const wageItems = ref<StaffWageItem[]>([])
const wageTypes = ref<WageType[]>([])
const currencies = ref<string[]>(['USD'])
const editing = ref(false)
const showGuide = ref(false)
const staffContractId = ref<number | null>(null)
const needsVerification = ref(false)
const inheritedFromContractId = ref<number | null>(null)

const form = reactive({
  currency: 'USD',
  basic_salary: 0,
  bank_name: '',
  bank_account: '',
  bank_branch: '',
  tax_identifier: '',
  pay_status: 'active',
  notes: '',
})

const newItem = reactive({
  wage_type_id: null as number | null,
  amount: null as number | null,
  percent: null as number | null,
})

const statusItems = [
  { title: 'Active', value: 'active' },
  { title: 'Held', value: 'held' },
  { title: 'Terminated', value: 'terminated' },
]

const SYSTEM_CODES = new Set(['BASIC', 'TAX', 'LOAN_DED'])

const assignableTypes = computed(() =>
  wageTypes.value
    .filter((w) => w.is_active && !SYSTEM_CODES.has(w.code))
    .slice()
    .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0) || a.name.localeCompare(b.name)),
)

const selectedType = computed(() =>
  assignableTypes.value.find((w) => w.id === newItem.wage_type_id) || null,
)

const usesPercent = computed(() => {
  const m = selectedType.value?.calc_method
  return m === 'percent_of_base' || m === 'percent_of_gross'
})

const usesAmount = computed(() => {
  const m = selectedType.value?.calc_method
  return !m || m === 'fixed' || m === 'manual' || m === 'formula'
})

const typeSelectItems = computed(() =>
  assignableTypes.value.map((w) => ({
    title: `${categoryLabel(w.category)} · ${w.name} (${methodShort(w.calc_method)})`,
    value: w.id,
    raw: w,
  })),
)

const assignedTypeIds = computed(() => new Set(wageItems.value.map((i) => i.wage_type_id)))

const earningsItems = computed(() =>
  wageItems.value.filter((i) => (i.wage_type?.category || '') === 'earning'),
)
const benefitItems = computed(() =>
  wageItems.value.filter((i) => (i.wage_type?.category || '') === 'benefit'),
)
const deductionItems = computed(() =>
  wageItems.value.filter((i) => (i.wage_type?.category || '') === 'deduction'),
)
const employerItems = computed(() =>
  wageItems.value.filter((i) => (i.wage_type?.category || '') === 'employer_contrib'),
)

const unassignedBenefits = computed(() =>
  assignableTypes.value.filter((w) => w.category === 'benefit' && !assignedTypeIds.value.has(w.id)),
)
const unassignedDeductions = computed(() =>
  assignableTypes.value.filter((w) => w.category === 'deduction' && !assignedTypeIds.value.has(w.id)),
)
const unassignedEarnings = computed(() =>
  assignableTypes.value.filter((w) => w.category === 'earning' && !assignedTypeIds.value.has(w.id)),
)

function categoryLabel(category: string): string {
  switch (category) {
    case 'earning':
      return 'Earning'
    case 'benefit':
      return 'Benefit'
    case 'deduction':
      return 'Deduction'
    case 'employer_contrib':
      return 'Employer'
    case 'tax':
      return 'Tax'
    default:
      return category
  }
}

function methodShort(method: string): string {
  switch (method) {
    case 'percent_of_base':
      return '% of basic'
    case 'percent_of_gross':
      return '% of gross'
    case 'fixed':
      return 'fixed amount'
    case 'manual':
      return 'manual amount'
    default:
      return method
  }
}

function methodHelp(type: WageType | null): string {
  if (!type) return 'Select a wage type to see whether to enter Amount or Percent.'
  if (type.calc_method === 'percent_of_base') {
    return `Percent of basic salary. Example: 10 means 10% of basic (${form.basic_salary || 0} → ${(Number(form.basic_salary || 0) * 0.1).toFixed(2)}). Leave Amount empty.`
  }
  if (type.calc_method === 'percent_of_gross') {
    return 'Percent of gross pay for the period (basic + earnings before this item). Leave Amount empty.'
  }
  return 'Enter a fixed Amount in the staff pay currency. Leave Percent empty.'
}

function valueHint(item: StaffWageItem): string {
  const method = item.wage_type?.calc_method
  if (method === 'percent_of_base' || method === 'percent_of_gross') {
    const p = item.percent != null ? Number(item.percent) : null
    if (p == null) return '—'
    if (method === 'percent_of_base' && pay.value) {
      const amt = (Number(pay.value.basic_salary) * p) / 100
      return `${p}% of basic ≈ ${amt.toFixed(2)} ${pay.value.currency}`
    }
    return `${p}% of gross`
  }
  return item.amount != null ? Number(item.amount).toFixed(2) : '—'
}

function applyForm(row: StaffPay | null) {
  form.currency = row?.currency || currencies.value[0] || 'USD'
  form.basic_salary = Number(row?.basic_salary || 0)
  form.bank_name = String(row?.bank_name || '')
  form.bank_account = String(row?.bank_account || '')
  form.bank_branch = String(row?.bank_branch || '')
  form.tax_identifier = String(row?.tax_identifier || '')
  form.pay_status = String(row?.pay_status || 'active')
  form.notes = String(row?.notes || '')
}

function resetNewItem() {
  newItem.wage_type_id = null
  newItem.amount = null
  newItem.percent = null
}

watch(
  () => newItem.wage_type_id,
  () => {
    newItem.amount = null
    newItem.percent = null
  },
)

async function load() {
  if (!props.staffId) return
  loading.value = true
  error.value = null
  try {
    const [bundle, settings, types] = await Promise.all([
      fetchStaffPay(props.staffId),
      fetchPayrollSettings(),
      fetchWageTypes(),
    ])
    pay.value = bundle.pay
    wageItems.value = bundle.wage_items || []
    staffContractId.value =
      bundle.staff_contract_id ?? bundle.pay?.staff_contract_id ?? null
    needsVerification.value = !!bundle.needs_verification
    inheritedFromContractId.value = bundle.inherited_from_contract_id ?? null
    currencies.value = settings.enabled_currencies?.length
      ? settings.enabled_currencies
      : [settings.default_currency]
    wageTypes.value = types
    applyForm(bundle.pay)
    if (!bundle.pay || needsVerification.value) editing.value = true
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load payroll for this staff member')
    pay.value = null
    wageItems.value = []
    staffContractId.value = null
    needsVerification.value = false
    inheritedFromContractId.value = null
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    pay.value = await saveStaffPay(props.staffId, {
      currency: form.currency,
      basic_salary: Number(form.basic_salary),
      bank_name: form.bank_name || null,
      bank_account: form.bank_account || null,
      bank_branch: form.bank_branch || null,
      tax_identifier: form.tax_identifier || null,
      pay_status: form.pay_status,
      notes: form.notes || null,
    })
    applyForm(pay.value)
    needsVerification.value = false
    editing.value = false
    success.value = 'Staff pay saved. Next: add benefits and deductions below.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save staff pay')
  } finally {
    saving.value = false
  }
}

async function addWageItem() {
  if (!newItem.wage_type_id || !pay.value) {
    error.value = 'Save basic pay first, then add wage items.'
    return
  }
  if (usesPercent.value && (newItem.percent == null || Number.isNaN(Number(newItem.percent)))) {
    error.value = 'This wage type uses Percent (not Amount). Enter a percent value such as 10 for 10%.'
    return
  }
  if (usesAmount.value && (newItem.amount == null || Number.isNaN(Number(newItem.amount)))) {
    error.value = 'This wage type uses a fixed Amount. Enter the amount in the staff currency.'
    return
  }

  saving.value = true
  error.value = null
  try {
    await createStaffWageItem(props.staffId, {
      wage_type_id: newItem.wage_type_id,
      amount: usesAmount.value ? newItem.amount : null,
      percent: usesPercent.value ? newItem.percent : null,
      is_active: true,
    })
    resetNewItem()
    success.value = 'Wage item added.'
    const bundle = await fetchStaffPay(props.staffId)
    wageItems.value = bundle.wage_items || []
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not add wage item')
  } finally {
    saving.value = false
  }
}

async function quickAdd(type: WageType) {
  newItem.wage_type_id = type.id
  newItem.amount = null
  newItem.percent = null
  if (type.calc_method === 'percent_of_base' || type.calc_method === 'percent_of_gross') {
    // leave percent for user to fill — focus UX via selection
    return
  }
}

async function removeWageItem(id: number) {
  saving.value = true
  error.value = null
  try {
    await deleteStaffWageItem(props.staffId, id)
    wageItems.value = wageItems.value.filter((w) => w.id !== id)
    success.value = 'Wage item removed.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not remove wage item')
  } finally {
    saving.value = false
  }
}

watch(
  () => props.staffId,
  () => void load(),
)
onMounted(() => void load())

defineExpose({ reload: load })
</script>

<template>
  <section class="staff-pay mb-4">
    <div class="staff-pay__toolbar">
      <div>
        <h2 class="staff-pay__title">
          <i class="fa-solid fa-money-check-dollar section-heading__icon" aria-hidden="true" />
          Payroll
        </h2>
        <p class="staff-pay__lede">
          <template v-if="staffContractId">
            Payroll for current contract #{{ staffContractId }}. Capture basic salary, bank details,
            benefits, and deductions.
          </template>
          <template v-else>
            Capture basic salary, bank details, benefits, and deductions for this employee.
          </template>
        </p>
      </div>
      <div class="d-flex ga-2 flex-wrap">
        <v-btn size="small" variant="text" @click="showGuide = !showGuide">
          {{ showGuide ? 'Hide guide' : 'How to fill' }}
        </v-btn>
        <v-btn
          v-if="!editing && pay"
          variant="outlined"
          color="primary"
          size="small"
          @click="editing = true"
        >
          Edit pay
        </v-btn>
        <v-btn v-if="editing" color="primary" size="small" :loading="saving" @click="save">
          Save pay
        </v-btn>
        <v-btn
          v-if="editing && pay"
          variant="text"
          size="small"
          :disabled="saving"
          @click="
            editing = false;
            applyForm(pay)
          "
        >
          Cancel
        </v-btn>
        <v-btn size="small" variant="text" :to="{ name: 'payroll' }">Payroll hub</v-btn>
        <v-btn
          size="small"
          variant="text"
          :to="{ name: 'payroll-payslips', query: { staff_id: String(staffId) } }"
        >
          Payslips
        </v-btn>
      </div>
    </div>

    <v-alert
      v-if="needsVerification"
      type="warning"
      variant="tonal"
      class="mb-3"
      density="compact"
    >
      Copied from previous contract
      <template v-if="inheritedFromContractId"> #{{ inheritedFromContractId }}</template>.
      Verify all entries before saving.
    </v-alert>

    <v-alert
      v-if="showGuide"
      type="info"
      variant="tonal"
      density="compact"
      class="mb-3"
      title="How to fill payroll for this employee"
    >
      <ol class="staff-pay__guide">
        <li>
          <strong>Basic pay</strong> — set currency, basic salary, pay status, tax ID, and bank details, then Save.
        </li>
        <li>
          <strong>Benefits</strong> — add housing, transport, medical, meal, etc. Most use a fixed
          <em>Amount</em> in the staff currency.
        </li>
        <li>
          <strong>Deductions</strong> — add pension, social security, health, union, etc. Many use
          <em>Percent</em> of basic or gross.
        </li>
        <li>
          <strong>What does Percent mean?</strong>
          <ul>
            <li>
              <code>% of basic</code> — percent × basic salary (e.g. 7% of 5,000 = 350).
            </li>
            <li>
              <code>% of gross</code> — percent × period gross (basic + earnings/benefits already included).
            </li>
          </ul>
          Enter only Percent for those types; leave Amount blank. For fixed types, enter only Amount.
        </li>
        <li>
          Tax withholding and loan installments are calculated automatically on the payroll run — do not add them here.
        </li>
      </ol>
    </v-alert>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading pay master…</div>

    <template v-else>
      <v-sheet v-if="!pay && !editing" border rounded class="pa-4 mb-3">
        <div class="text-body-2 mb-2">No pay master yet for this staff member.</div>
        <v-btn color="primary" size="small" @click="editing = true">Set up pay</v-btn>
      </v-sheet>

      <v-sheet v-if="editing" border rounded class="pa-4 mb-3">
        <div class="text-subtitle-2 mb-2">1. Basic pay &amp; bank</div>
        <v-row dense>
          <v-col cols="12" sm="4" md="3">
            <v-select
              v-model="form.currency"
              :items="currencies"
              label="Currency"
              density="compact"
              hide-details="auto"
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-text-field
              v-model.number="form.basic_salary"
              label="Basic salary"
              type="number"
              density="compact"
              hide-details="auto"
              hint="Monthly basic before benefits"
              persistent-hint
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-select
              v-model="form.pay_status"
              :items="statusItems"
              label="Pay status"
              density="compact"
              hide-details="auto"
            />
          </v-col>
          <v-col cols="12" sm="4" md="3">
            <v-text-field v-model="form.tax_identifier" label="Tax ID" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="4" md="4">
            <v-text-field v-model="form.bank_name" label="Bank name" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="4" md="4">
            <v-text-field v-model="form.bank_account" label="Account" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12" sm="4" md="4">
            <v-text-field v-model="form.bank_branch" label="Branch" density="compact" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="form.notes" label="Notes" rows="2" density="compact" hide-details="auto" />
          </v-col>
        </v-row>
      </v-sheet>

      <v-sheet v-else-if="pay" border rounded class="pa-4 mb-3">
        <div class="text-subtitle-2 mb-2">1. Basic pay &amp; bank</div>
        <table class="detail-table">
          <tbody>
            <tr>
              <th>Currency</th>
              <td>{{ pay.currency }}</td>
              <th>Basic salary</th>
              <td>{{ Number(pay.basic_salary).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}</td>
            </tr>
            <tr>
              <th>Pay status</th>
              <td>
                <v-chip size="small" variant="tonal" :color="pay.pay_status === 'active' ? 'success' : 'warning'">
                  {{ pay.pay_status }}
                </v-chip>
              </td>
              <th>Tax ID</th>
              <td>{{ pay.tax_identifier || '—' }}</td>
            </tr>
            <tr>
              <th>Bank</th>
              <td>{{ pay.bank_name || '—' }}</td>
              <th>Account / branch</th>
              <td>{{ [pay.bank_account, pay.bank_branch].filter(Boolean).join(' · ') || '—' }}</td>
            </tr>
            <tr v-if="pay.notes">
              <th>Notes</th>
              <td colspan="3">{{ pay.notes }}</td>
            </tr>
          </tbody>
        </table>
      </v-sheet>

      <div class="text-subtitle-2 mb-1">2. Benefits, earnings &amp; deductions</div>
      <p class="text-caption text-medium-emphasis mb-2">
        Assigned items appear in the tables below. Use chips to pick a missing benefit/deduction, then enter
        Amount or Percent as prompted.
      </p>

      <v-sheet border rounded class="pa-3 mb-3">
        <div class="d-flex ga-2 flex-wrap align-start mb-2">
          <v-select
            v-model="newItem.wage_type_id"
            :items="typeSelectItems"
            item-title="title"
            item-value="value"
            label="Wage type"
            density="compact"
            hide-details
            style="min-width: 280px; flex: 1"
            :disabled="!pay"
          />
          <v-text-field
            v-if="!selectedType || usesAmount"
            v-model.number="newItem.amount"
            label="Amount"
            type="number"
            density="compact"
            hide-details
            style="max-width: 140px"
            :disabled="!pay || (selectedType ? !usesAmount : false)"
          />
          <v-text-field
            v-if="!selectedType || usesPercent"
            v-model.number="newItem.percent"
            label="Percent"
            type="number"
            density="compact"
            hide-details
            style="max-width: 120px"
            :disabled="!pay || (selectedType ? !usesPercent : false)"
            hint="e.g. 10 = 10%"
          />
          <v-btn
            size="small"
            color="primary"
            variant="tonal"
            :loading="saving"
            :disabled="!pay || !newItem.wage_type_id"
            @click="addWageItem"
          >
            Add item
          </v-btn>
        </div>
        <div class="text-caption text-medium-emphasis mb-2">{{ methodHelp(selectedType) }}</div>

        <div v-if="pay" class="mb-2">
          <div v-if="unassignedBenefits.length" class="mb-2">
            <div class="text-caption font-weight-medium mb-1">Available benefits</div>
            <div class="d-flex flex-wrap ga-1">
              <v-chip
                v-for="t in unassignedBenefits"
                :key="t.id"
                size="small"
                variant="outlined"
                color="primary"
                class="cursor-pointer"
                @click="quickAdd(t)"
              >
                {{ t.name }}
                <span class="text-medium-emphasis ms-1">({{ methodShort(t.calc_method) }})</span>
              </v-chip>
            </div>
          </div>
          <div v-if="unassignedDeductions.length" class="mb-2">
            <div class="text-caption font-weight-medium mb-1">Available deductions</div>
            <div class="d-flex flex-wrap ga-1">
              <v-chip
                v-for="t in unassignedDeductions"
                :key="t.id"
                size="small"
                variant="outlined"
                color="warning"
                class="cursor-pointer"
                @click="quickAdd(t)"
              >
                {{ t.name }}
                <span class="text-medium-emphasis ms-1">({{ methodShort(t.calc_method) }})</span>
              </v-chip>
            </div>
          </div>
          <div v-if="unassignedEarnings.length" class="mb-1">
            <div class="text-caption font-weight-medium mb-1">Other earnings</div>
            <div class="d-flex flex-wrap ga-1">
              <v-chip
                v-for="t in unassignedEarnings"
                :key="t.id"
                size="small"
                variant="outlined"
                class="cursor-pointer"
                @click="quickAdd(t)"
              >
                {{ t.name }}
                <span class="text-medium-emphasis ms-1">({{ methodShort(t.calc_method) }})</span>
              </v-chip>
            </div>
          </div>
        </div>
        <div v-else class="text-caption text-medium-emphasis">Save basic pay first to add wage items.</div>
      </v-sheet>

      <template v-for="group in [
        { key: 'earnings', title: 'Earnings', rows: earningsItems },
        { key: 'benefits', title: 'Benefits', rows: benefitItems },
        { key: 'deductions', title: 'Deductions', rows: deductionItems },
        { key: 'employer', title: 'Employer contributions', rows: employerItems },
      ]" :key="group.key">
        <div class="text-subtitle-2 mt-3 mb-1">{{ group.title }}</div>
        <v-table v-if="group.rows.length" density="compact" class="mb-2">
          <thead>
            <tr>
              <th>Type</th>
              <th>Method</th>
              <th>Value</th>
              <th>Active</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in group.rows" :key="item.id">
              <td>{{ item.wage_type?.name || item.wage_type_id }}</td>
              <td>{{ methodShort(item.wage_type?.calc_method || '') }}</td>
              <td>{{ valueHint(item) }}</td>
              <td>{{ item.is_active ? 'Yes' : 'No' }}</td>
              <td>
                <v-btn size="x-small" variant="text" color="error" :loading="saving" @click="removeWageItem(item.id)">
                  Remove
                </v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
        <div v-else class="text-caption text-medium-emphasis mb-2">None assigned yet.</div>
      </template>

      <div class="text-caption text-medium-emphasis mt-3">
        Org-wide wage type catalog:
        <RouterLink :to="{ name: 'payroll-setup' }">Payroll setup</RouterLink>.
      </div>
    </template>
  </section>
</template>

<style scoped>
.staff-pay__toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
}
.staff-pay__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.45rem;
}
.staff-pay__lede {
  margin: 0.2rem 0 0;
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-size: 0.875rem;
}
.staff-pay__guide {
  margin: 0.35rem 0 0;
  padding-left: 1.15rem;
  font-size: 0.875rem;
}
.staff-pay__guide li {
  margin-bottom: 0.35rem;
}
.staff-pay__guide ul {
  margin: 0.25rem 0 0;
  padding-left: 1.1rem;
}
.section-heading__icon {
  color: #0d7a3a;
}
.cursor-pointer {
  cursor: pointer;
}
.detail-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.detail-table th,
.detail-table td {
  padding: 0.4rem 0.55rem;
  border-bottom: 1px solid rgba(58, 71, 82, 0.1);
  vertical-align: top;
}
.detail-table th {
  width: 18%;
  color: rgba(58, 71, 82, 0.72);
  font-weight: 600;
  text-align: left;
}
</style>
