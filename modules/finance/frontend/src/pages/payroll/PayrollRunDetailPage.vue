<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import { fetchRun, postRun, simulateRun, type PayrollRun } from '@/lib/payrollApi'

const route = useRoute()
const run = ref<PayrollRun | null>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const allowNeg = ref(false)

async function load() {
  loading.value = true
  error.value = null
  try {
    run.value = await fetchRun(Number(route.params.id))
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function doSimulate() {
  busy.value = true
  error.value = null
  try {
    await simulateRun(Number(route.params.id))
    success.value = 'Simulation complete.'
    run.value = await fetchRun(Number(route.params.id))
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

async function doPost() {
  busy.value = true
  error.value = null
  try {
    await postRun(Number(route.params.id), allowNeg.value)
    success.value = 'Run posted; payslips generated.'
    run.value = await fetchRun(Number(route.params.id))
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>

<template>
  <PayrollPageShell
    :title="run?.title || `Run #${route.params.id}`"
    :lede="run ? `Status: ${run.status}` : 'Simulate calculations, then post to generate payslips.'"
  >
    <template #actions>
      <v-btn variant="outlined" size="small" :to="{ name: 'payroll-runs' }">
        <i class="fa-solid fa-arrow-left me-2" aria-hidden="true" />
        All runs
      </v-btn>
    </template>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>
    <template v-else-if="run">
      <div class="payroll-panel d-flex ga-2 align-center flex-wrap">
        <v-btn
          color="primary"
          size="small"
          :loading="busy"
          :disabled="run.status === 'posted' || run.status === 'cancelled'"
          @click="doSimulate"
        >
          Simulate
        </v-btn>
        <v-btn
          color="primary"
          variant="tonal"
          size="small"
          :loading="busy"
          :disabled="run.status !== 'simulated'"
          @click="doPost"
        >
          Post
        </v-btn>
        <v-checkbox v-model="allowNeg" label="Allow negative net" density="compact" hide-details />
        <span class="payroll-status" :class="`payroll-status--${run.status}`">{{ run.status }}</span>
        <span class="text-medium-emphasis text-body-2">
          Staff {{ run.staff_count }} · Gross {{ Number(run.total_gross_default).toFixed(2) }} · Net
          {{ Number(run.total_net_default).toFixed(2) }}
        </span>
      </div>

      <div class="payroll-table-wrap">
        <v-table density="compact">
          <thead>
            <tr>
              <th>Staff</th>
              <th>Currency</th>
              <th>Basic</th>
              <th>Gross</th>
              <th>Tax</th>
              <th>Deductions</th>
              <th>Net</th>
              <th>Net (default)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in run.lines || []" :key="line.id">
              <td>
                <RouterLink :to="{ name: 'staff-show', params: { id: line.staff_id } }">
                  {{ line.staff_name || `#${line.staff_id}` }}
                </RouterLink>
                <div v-if="line.sap_number" class="text-caption text-medium-emphasis">SAP {{ line.sap_number }}</div>
              </td>
              <td>{{ line.currency }}</td>
              <td>{{ Number(line.basic).toFixed(2) }}</td>
              <td>{{ Number(line.gross).toFixed(2) }}</td>
              <td>{{ Number(line.tax).toFixed(2) }}</td>
              <td>{{ Number(line.deductions).toFixed(2) }}</td>
              <td>{{ Number(line.net).toFixed(2) }}</td>
              <td>{{ Number(line.net_default).toFixed(2) }}</td>
            </tr>
            <tr v-if="!(run.lines || []).length">
              <td colspan="8" class="text-center text-medium-emphasis py-6">
                No lines yet. Click Simulate to calculate pay for active staff.
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>
    </template>
  </PayrollPageShell>
</template>
