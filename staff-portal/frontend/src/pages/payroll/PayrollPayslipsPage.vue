<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PayrollPageShell from '@/components/payroll/PayrollPageShell.vue'
import { api } from '@/lib/api'
import { fetchPayslips, payslipPdfUrl, type Payslip } from '@/lib/payrollApi'

const route = useRoute()
const loading = ref(true)
const error = ref<string | null>(null)
const rows = ref<Payslip[]>([])

async function load() {
  loading.value = true
  error.value = null
  try {
    const staffId = route.query.staff_id ? Number(route.query.staff_id) : undefined
    rows.value = await fetchPayslips(staffId ? { staff_id: staffId } : undefined)
  } catch (e) {
    error.value = apiErrorMessage(e)
  } finally {
    loading.value = false
  }
}

async function openPdf(id: number) {
  try {
    const res = await api.get(payslipPdfUrl(id), { responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    window.open(url, '_blank')
  } catch (e) {
    error.value = apiErrorMessage(e)
  }
}

watch(() => route.query.staff_id, () => void load())
onMounted(load)
</script>

<template>
  <PayrollPageShell title="Payslips" lede="Download posted pay statements for yourself or filtered staff.">
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <div v-if="route.query.staff_id" class="payroll-panel text-body-2">
      Filtered to
      <RouterLink :to="{ name: 'staff-show', params: { id: Number(route.query.staff_id) } }">
        staff #{{ route.query.staff_id }}
      </RouterLink>
      ·
      <RouterLink :to="{ name: 'payroll-payslips' }">Clear filter</RouterLink>
    </div>

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>
    <div v-else class="payroll-table-wrap">
      <v-table density="compact">
        <thead>
          <tr>
            <th>ID</th>
            <th>Staff</th>
            <th>Period</th>
            <th>Generated</th>
            <th>Emailed</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in rows" :key="p.id">
            <td>{{ p.id }}</td>
            <td>
              <RouterLink :to="{ name: 'staff-show', params: { id: p.staff_id } }">
                {{ p.staff_name || `#${p.staff_id}` }}
              </RouterLink>
            </td>
            <td>{{ p.period?.label }}</td>
            <td>{{ p.generated_at || '—' }}</td>
            <td>{{ p.emailed_at || '—' }}</td>
            <td>
              <v-btn size="small" variant="text" color="primary" @click="openPdf(p.id)">PDF</v-btn>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="6" class="text-center text-medium-emphasis py-6">No payslips yet.</td>
          </tr>
        </tbody>
      </v-table>
    </div>
  </PayrollPageShell>
</template>
