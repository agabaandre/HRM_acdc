<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import {
  createLoan,
  decideLoan,
  disburseLoan,
  fetchLoans,
  fetchPeriods,
  type PayrollLoan,
  type PayrollPeriod,
} from '@/lib/payrollApi'

const auth = useAuthStore()
const tab = ref<'mine' | 'approvals' | 'admin'>('mine')
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const rows = ref<PayrollLoan[]>([])
const periods = ref<PayrollPeriod[]>([])

const principal = ref(1000)
const loanType = ref<'loan' | 'advance'>('advance')
const installments = ref(3)
const startPeriodId = ref<number | null>(null)

const isHr = computed(() => !!auth.me?.profile?.is_hr || auth.me?.profile?.role_id === 20)
const canApprove = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.APPROVE_LOANS) || auth.hasPermission(17))
const canManage = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.MANAGE_LOANS) || auth.hasPermission(17))

async function load() {
  loading.value = true
  error.value = null
  try {
    const params =
      tab.value === 'approvals'
        ? { pending_approval: true }
        : tab.value === 'mine'
          ? { mine: true }
          : {}
    rows.value = await fetchLoans(params)
    if (canManage.value) periods.value = await fetchPeriods()
    if (!startPeriodId.value && periods.value[0]) startPeriodId.value = periods.value[0].id
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function submitRequest() {
  busy.value = true
  error.value = null
  try {
    await createLoan({
      type: loanType.value,
      principal: principal.value,
      interest_rate: loanType.value === 'advance' ? 0 : 0,
      installment_count: installments.value,
    })
    success.value = 'Loan request submitted.'
    tab.value = 'mine'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function decide(id: number, decision: 'approve' | 'reject') {
  busy.value = true
  try {
    await decideLoan(id, decision)
    success.value = `Loan ${decision}d.`
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function disburse(id: number) {
  if (!startPeriodId.value) return
  busy.value = true
  try {
    await disburseLoan(id, {
      start_period_id: startPeriodId.value,
      installment_count: installments.value,
    })
    success.value = 'Loan disbursed.'
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <PayrollPageShell title="Loans & advances" lede="Request, approve, disburse, and track installment deductions.">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>

    <div class="payroll-panel pa-2 mb-3">
      <v-tabs v-model="tab" color="primary" density="comfortable" @update:model-value="load">
        <v-tab value="mine">
          <i class="fa-solid fa-user me-2" aria-hidden="true" />
          Mine
        </v-tab>
        <v-tab v-if="canApprove" value="approvals">
          <i class="fa-solid fa-clipboard-check me-2" aria-hidden="true" />
          Approvals
        </v-tab>
        <v-tab v-if="canManage" value="admin">
          <i class="fa-solid fa-briefcase me-2" aria-hidden="true" />
          Admin
        </v-tab>
      </v-tabs>
    </div>

    <div v-if="tab === 'mine'" class="payroll-panel">
      <div class="payroll-panel__title">New request</div>
      <div class="d-flex ga-2 flex-wrap align-center">
        <v-select
          v-model="loanType"
          :items="[
            { title: 'Advance', value: 'advance' },
            { title: 'Loan', value: 'loan' },
          ]"
          density="compact"
          hide-details
          style="max-width: 140px"
        />
        <v-text-field v-model.number="principal" label="Principal" type="number" density="compact" hide-details style="max-width: 140px" />
        <v-text-field v-model.number="installments" label="Installments" type="number" density="compact" hide-details style="max-width: 130px" />
        <v-btn color="primary" size="small" :loading="busy" @click="submitRequest">Submit</v-btn>
      </div>
    </div>

    <div v-if="canManage && tab === 'admin'" class="payroll-panel">
      <v-select
        v-model="startPeriodId"
        :items="periods"
        item-title="label"
        item-value="id"
        label="Disburse start period"
        density="compact"
        hide-details
        style="max-width: 220px"
      />
    </div>

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>
    <div v-else class="payroll-table-wrap">
      <v-table density="compact">
        <thead>
          <tr>
            <th>ID</th>
            <th>Staff</th>
            <th>Type</th>
            <th>Principal</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="loan in rows" :key="loan.id">
            <td>{{ loan.id }}</td>
            <td>{{ loan.staff_id }}</td>
            <td>{{ loan.type }}</td>
            <td>{{ Number(loan.principal).toFixed(2) }} {{ loan.currency }}</td>
            <td>
              <span class="payroll-status" :class="`payroll-status--${loan.status}`">{{ loan.status }}</span>
            </td>
            <td>
              <div class="d-flex ga-1">
                <template v-if="tab === 'approvals' && loan.status === 'pending_supervisor'">
                  <v-btn size="small" color="primary" :loading="busy" @click="decide(loan.id, 'approve')">Approve</v-btn>
                  <v-btn size="small" variant="text" :loading="busy" @click="decide(loan.id, 'reject')">Reject</v-btn>
                </template>
                <template v-if="tab === 'admin' && loan.status === 'pending_payroll'">
                  <v-btn size="small" color="primary" :loading="busy" @click="disburse(loan.id)">Disburse</v-btn>
                </template>
              </div>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="text-center text-medium-emphasis py-6">No loans in this view.</td>
          </tr>
        </tbody>
      </v-table>
    </div>
  </PayrollPageShell>
</template>
