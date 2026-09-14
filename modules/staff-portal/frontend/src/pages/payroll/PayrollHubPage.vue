<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { PAYROLL_PERMS } from '@/lib/payrollPermissions'
import { fetchPayrollDashboard, type DashboardData } from '@/lib/payrollApi'

const auth = useAuthStore()
const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<DashboardData | null>(null)

const isHr = computed(() => !!auth.me?.profile?.is_hr || auth.me?.profile?.role_id === 20)
const canSetup = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.MANAGE_SETUP) || auth.hasPermission(17))
const canRun = computed(() => isHr.value || auth.hasPermission(PAYROLL_PERMS.RUN_PAYROLL) || auth.hasPermission(17))
const canLoans = computed(
  () =>
    isHr.value ||
    auth.hasPermission(PAYROLL_PERMS.MANAGE_LOANS) ||
    auth.hasPermission(PAYROLL_PERMS.APPROVE_LOANS) ||
    auth.hasPermission(PAYROLL_PERMS.REQUEST_LOAN) ||
    auth.hasPermission(17),
)

const kpis = computed(() => {
  const d = data.value
  if (!d) return []
  return [
    {
      label: 'Open period',
      value: d.open_period?.label || '—',
      icon: 'fa-solid fa-calendar-days',
      hint: d.open_period?.status || 'No open period',
    },
    {
      label: 'Last run',
      value: d.last_run?.title || d.last_run?.status || '—',
      icon: 'fa-solid fa-flag-checkered',
      hint: d.last_run?.status ? `Status: ${d.last_run.status}` : 'No runs yet',
    },
    {
      label: 'Loan approvals',
      value: String(d.pending_loan_approvals ?? 0),
      icon: 'fa-solid fa-clipboard-check',
      hint: 'Awaiting supervisor decision',
    },
    {
      label: 'Missing pay master',
      value: String(d.staff_missing_pay_master ?? 0),
      icon: 'fa-solid fa-user-slash',
      hint: `${d.staff_with_pay_count ?? 0} of ${d.active_staff_count ?? 0} staff have pay`,
    },
  ]
})

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await fetchPayrollDashboard()
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <PayrollPageShell title="Payroll" lede="Wage runs, payslips, loans, and setup for staff.">
    <template #actions>
      <v-btn v-if="canRun" color="primary" size="small" :to="{ name: 'payroll-runs' }">
        <i class="fa-solid fa-play me-2" aria-hidden="true" />
        New run
      </v-btn>
      <v-btn v-if="canSetup" variant="outlined" size="small" :to="{ name: 'payroll-setup' }">
        <i class="fa-solid fa-sliders me-2" aria-hidden="true" />
        Setup
      </v-btn>
    </template>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="data">
      <v-row class="mb-4" dense>
        <v-col v-for="card in kpis" :key="card.label" cols="12" sm="6" md="3">
          <div class="payroll-kpi">
            <div class="payroll-kpi__icon" aria-hidden="true">
              <i :class="card.icon" />
            </div>
            <div>
              <div class="payroll-kpi__label">{{ card.label }}</div>
              <div class="payroll-kpi__value">{{ card.value }}</div>
              <div class="payroll-kpi__hint">{{ card.hint }}</div>
            </div>
          </div>
        </v-col>
      </v-row>

      <section class="payroll-panel">
        <h2 class="payroll-panel__title">Quick links</h2>
        <p class="payroll-panel__lede">Jump into the most common payroll workflows.</p>
        <div class="payroll-quick">
          <v-btn
            v-if="canRun"
            class="payroll-quick__btn"
            variant="flat"
            color="primary"
            :to="{ name: 'payroll-runs' }"
          >
            <i class="fa-solid fa-play me-2" aria-hidden="true" />
            Periods &amp; runs
          </v-btn>
          <v-btn class="payroll-quick__btn" variant="tonal" color="primary" :to="{ name: 'payroll-payslips' }">
            <i class="fa-solid fa-file-invoice-dollar me-2" aria-hidden="true" />
            Payslips
          </v-btn>
          <v-btn
            v-if="canLoans"
            class="payroll-quick__btn"
            variant="tonal"
            color="primary"
            :to="{ name: 'payroll-loans' }"
          >
            <i class="fa-solid fa-hand-holding-dollar me-2" aria-hidden="true" />
            Loans &amp; advances
          </v-btn>
          <v-btn
            v-if="canSetup || canRun"
            class="payroll-quick__btn"
            variant="outlined"
            :to="{ name: 'payroll-setup' }"
          >
            <i class="fa-solid fa-gear me-2" aria-hidden="true" />
            Wage types &amp; tax
          </v-btn>
          <v-btn
            v-if="canSetup"
            class="payroll-quick__btn"
            variant="outlined"
            :to="{ name: 'staff' }"
          >
            <i class="fa-solid fa-users me-2" aria-hidden="true" />
            Staff pay on profiles
          </v-btn>
        </div>
      </section>
    </template>
  </PayrollPageShell>
</template>

<style scoped>
.payroll-kpi {
  display: flex;
  gap: 0.75rem;
  align-items: flex-start;
  min-height: 5.5rem;
  padding: 0.9rem 0.95rem;
  border-radius: 0.7rem;
  border: 1px solid rgba(58, 71, 82, 0.1);
  background: rgba(255, 255, 255, 0.88);
  box-shadow: 0 1px 2px rgba(58, 71, 82, 0.04);
}
.payroll-kpi__icon {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 0.55rem;
  display: grid;
  place-items: center;
  background: rgba(17, 154, 72, 0.12);
  color: #0d7a3a;
  flex-shrink: 0;
}
.payroll-kpi__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: rgba(58, 71, 82, 0.62);
  font-weight: 700;
}
.payroll-kpi__value {
  margin-top: 0.15rem;
  font-size: 1.15rem;
  font-weight: 700;
  color: #3a4752;
  line-height: 1.25;
  word-break: break-word;
}
.payroll-kpi__hint {
  margin-top: 0.2rem;
  font-size: 0.78rem;
  color: rgba(58, 71, 82, 0.62);
}
.payroll-panel {
  padding: 1rem 1.05rem 1.1rem;
  border-radius: 0.75rem;
  border: 1px solid rgba(58, 71, 82, 0.1);
  background: rgba(255, 255, 255, 0.9);
  box-shadow: 0 1px 2px rgba(58, 71, 82, 0.04);
}
.payroll-panel__title {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #3a4752;
}
.payroll-panel__lede {
  margin: 0.2rem 0 0.85rem;
  font-size: 0.85rem;
  color: rgba(58, 71, 82, 0.65);
}
.payroll-quick {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}
.payroll-quick__btn {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0.01em;
}
</style>
