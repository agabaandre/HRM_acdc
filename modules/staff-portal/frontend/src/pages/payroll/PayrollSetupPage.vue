<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import {
  createWageType,
  fetchPayrollSettings,
  fetchStaffPayDirectory,
  fetchTaxRules,
  fetchWageTypes,
  savePayrollSettings,
  saveStaffPay,
  createTaxRule,
  type PayrollSettings,
  type StaffPay,
  type TaxRule,
  type WageType,
} from '@/lib/payrollApi'

const auth = useAuthStore()
const tab = ref<'settings' | 'wages' | 'tax' | 'staff'>('settings')
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const settings = ref<PayrollSettings | null>(null)
const wages = ref<WageType[]>([])
const taxes = ref<TaxRule[]>([])
const staffPay = ref<StaffPay[]>([])

const newWage = ref({ code: '', name: '', category: 'earning', calc_method: 'fixed', taxable: true, pre_tax: false })
const staffId = ref<number | null>(null)
const staffForm = ref({ currency: 'USD', basic_salary: 0, pay_status: 'active' })
const taxForm = ref({
  code: '',
  name: '',
  effective_from: new Date().toISOString().slice(0, 10),
  applies_to: 'employee',
  bands: [{ from_amount: 0, to_amount: null as number | null, rate_percent: 0, fixed_amount: 0 }],
})

const isHr = computed(() => !!auth.me?.profile?.is_hr || auth.me?.profile?.role_id === 20)
const canSetup = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.MANAGE_SETUP) || auth.hasPermission(17))
const canStaffPay = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.MANAGE_STAFF_PAY) || auth.hasPermission(17))

async function load() {
  loading.value = true
  error.value = null
  try {
    settings.value = await fetchPayrollSettings()
    wages.value = await fetchWageTypes()
    taxes.value = await fetchTaxRules()
    if (canStaffPay.value) staffPay.value = await fetchStaffPayDirectory()
    if (settings.value) staffForm.value.currency = settings.value.default_currency
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function saveSettings() {
  if (!settings.value || !canSetup.value) return
  busy.value = true
  try {
    const enabled =
      typeof settings.value.enabled_currencies === 'string'
        ? String(settings.value.enabled_currencies)
            .split(',')
            .map((s) => s.trim().toUpperCase())
            .filter(Boolean)
        : settings.value.enabled_currencies
    settings.value = await savePayrollSettings({
      ...settings.value,
      enabled_currencies: enabled,
    })
    success.value = 'Settings saved.'
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function addWage() {
  if (!canSetup.value) return
  busy.value = true
  try {
    await createWageType(newWage.value)
    success.value = 'Wage type created.'
    wages.value = await fetchWageTypes()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function addTax() {
  if (!canSetup.value) return
  busy.value = true
  try {
    await createTaxRule(taxForm.value)
    success.value = 'Tax rule created.'
    taxes.value = await fetchTaxRules()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function savePay() {
  if (!staffId.value || !canStaffPay.value) return
  busy.value = true
  try {
    await saveStaffPay(staffId.value, staffForm.value)
    success.value = 'Staff pay saved.'
    staffPay.value = await fetchStaffPayDirectory()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

const enabledCurrenciesText = computed({
  get: () => (settings.value?.enabled_currencies || []).join(', '),
  set: (v: string) => {
    if (!settings.value) return
    ;(settings.value as { enabled_currencies: string[] }).enabled_currencies = v
      .split(',')
      .map((s) => s.trim().toUpperCase())
      .filter(Boolean) as unknown as string[]
  },
})

onMounted(load)
</script>

<template>
  <PayrollPageShell title="Payroll setup" lede="Currency, wage types, tax rules, and staff pay directory.">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else>
      <div class="payroll-panel pa-2 mb-3">
        <v-tabs v-model="tab" color="primary" density="comfortable">
          <v-tab value="settings">
            <i class="fa-solid fa-coins me-2" aria-hidden="true" />
            Settings
          </v-tab>
          <v-tab value="wages">
            <i class="fa-solid fa-tags me-2" aria-hidden="true" />
            Wage types
          </v-tab>
          <v-tab value="tax">
            <i class="fa-solid fa-percent me-2" aria-hidden="true" />
            Tax rules
          </v-tab>
          <v-tab v-if="canStaffPay" value="staff">
            <i class="fa-solid fa-users me-2" aria-hidden="true" />
            Staff pay
          </v-tab>
        </v-tabs>
      </div>

      <div v-if="tab === 'settings' && settings" class="payroll-panel" style="max-width: 560px">
        <v-text-field v-model="settings.default_currency" label="Default currency" density="compact" class="mb-2" />
        <v-text-field v-model="enabledCurrenciesText" label="Enabled currencies (comma-separated)" density="compact" class="mb-2" />
        <v-text-field v-model.number="settings.period_close_day" label="Period close day" type="number" density="compact" class="mb-2" />
        <v-text-field v-model="settings.jurisdiction_default" label="Default jurisdiction" density="compact" class="mb-2" />
        <v-btn v-if="canSetup" color="primary" size="small" :loading="busy" @click="saveSettings">Save</v-btn>
      </div>

      <div v-else-if="tab === 'wages'">
        <div v-if="canSetup" class="payroll-panel d-flex ga-2 flex-wrap align-center">
          <v-text-field v-model="newWage.code" label="Code" density="compact" hide-details style="max-width: 120px" />
          <v-text-field v-model="newWage.name" label="Name" density="compact" hide-details style="max-width: 180px" />
          <v-select
            v-model="newWage.category"
            :items="['earning', 'benefit', 'deduction', 'tax', 'employer_contrib']"
            density="compact"
            hide-details
            style="max-width: 160px"
          />
          <v-btn color="primary" size="small" :loading="busy" @click="addWage">Add</v-btn>
        </div>
        <div class="payroll-table-wrap">
          <v-table density="compact">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Category</th>
                <th>Method</th>
                <th>System</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in wages" :key="w.id">
                <td>{{ w.code }}</td>
                <td>{{ w.name }}</td>
                <td>{{ w.category }}</td>
                <td>{{ w.calc_method }}</td>
                <td>{{ w.is_system ? 'Yes' : '' }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </div>

      <div v-else-if="tab === 'tax'">
        <div v-if="canSetup" class="payroll-panel">
          <div class="d-flex ga-2 flex-wrap mb-2">
            <v-text-field v-model="taxForm.code" label="Code" density="compact" hide-details style="max-width: 120px" />
            <v-text-field v-model="taxForm.name" label="Name" density="compact" hide-details style="max-width: 180px" />
            <v-text-field v-model="taxForm.effective_from" label="Effective from" type="date" density="compact" hide-details />
            <v-text-field
              v-model.number="taxForm.bands[0].rate_percent"
              label="Rate %"
              type="number"
              density="compact"
              hide-details
              style="max-width: 100px"
            />
            <v-btn color="primary" size="small" :loading="busy" @click="addTax">Add rule</v-btn>
          </div>
        </div>
        <div class="payroll-table-wrap">
          <v-table density="compact">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>From</th>
                <th>Bands</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in taxes" :key="t.id">
                <td>{{ t.code }}</td>
                <td>{{ t.name }}</td>
                <td>{{ t.effective_from }}</td>
                <td>{{ t.bands?.length || 0 }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </div>

      <div v-else-if="tab === 'staff' && canStaffPay">
        <div class="payroll-panel d-flex ga-2 flex-wrap align-center">
          <v-text-field v-model.number="staffId" label="Staff ID" type="number" density="compact" hide-details style="max-width: 120px" />
          <v-text-field v-model="staffForm.currency" label="Currency" density="compact" hide-details style="max-width: 100px" />
          <v-text-field v-model.number="staffForm.basic_salary" label="Basic salary" type="number" density="compact" hide-details style="max-width: 150px" />
          <v-btn color="primary" size="small" :loading="busy" :disabled="!staffId" @click="savePay">Save pay</v-btn>
          <v-btn
            v-if="staffId"
            variant="text"
            size="small"
            :to="{ name: 'staff-show', params: { id: staffId } }"
          >
            Open staff profile
          </v-btn>
        </div>
        <p class="text-caption text-medium-emphasis mb-2">
          Prefer editing pay on the staff profile (Payroll section). This list is a quick directory.
        </p>
        <div class="payroll-table-wrap">
          <v-table density="compact">
            <thead>
              <tr>
                <th>Staff</th>
                <th>SAP</th>
                <th>Currency</th>
                <th>Basic</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in staffPay" :key="s.id">
                <td>
                  <RouterLink :to="{ name: 'staff-show', params: { id: s.staff_id } }">
                    {{ s.staff_name || `Staff #${s.staff_id}` }}
                  </RouterLink>
                </td>
                <td>{{ s.sap_number || '—' }}</td>
                <td>{{ s.currency }}</td>
                <td>{{ Number(s.basic_salary).toFixed(2) }}</td>
                <td>
                  <span class="payroll-status" :class="`payroll-status--${s.pay_status}`">{{ s.pay_status }}</span>
                </td>
                <td>
                  <v-btn size="x-small" variant="text" color="primary" :to="{ name: 'staff-show', params: { id: s.staff_id } }">
                    Profile
                  </v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </div>
    </template>
  </PayrollPageShell>
</template>
