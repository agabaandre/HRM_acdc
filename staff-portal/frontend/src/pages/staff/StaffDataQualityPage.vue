<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { fetchDataQuality } from '@/lib/staffApi'

const loading = ref(false)
const error = ref<string | null>(null)
const counts = ref({ missing_email: 0, missing_dob: 0, missing_sap: 0 })
const sample = ref<Array<Record<string, unknown>>>([])

onMounted(async () => {
  loading.value = true
  try {
    const data = await fetchDataQuality()
    counts.value = data.counts
    sample.value = data.sample
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load data quality')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <PortalPageChrome title="Data quality" lede="Missing email, date of birth, or SAP number.">
      <template #tabs>
        <StaffSubnav />
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else>
      <v-row dense class="mb-4">
        <v-col cols="12" sm="4">
          <v-sheet border rounded class="pa-3">
            <div class="text-caption text-medium-emphasis">Missing email</div>
            <div class="text-h5">{{ counts.missing_email }}</div>
          </v-sheet>
        </v-col>
        <v-col cols="12" sm="4">
          <v-sheet border rounded class="pa-3">
            <div class="text-caption text-medium-emphasis">Missing DOB</div>
            <div class="text-h5">{{ counts.missing_dob }}</div>
          </v-sheet>
        </v-col>
        <v-col cols="12" sm="4">
          <v-sheet border rounded class="pa-3">
            <div class="text-caption text-medium-emphasis">Missing SAP</div>
            <div class="text-h5">{{ counts.missing_sap }}</div>
          </v-sheet>
        </v-col>
      </v-row>

      <div class="text-subtitle-2 mb-2">Sample (up to 50)</div>
      <v-table density="compact">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>DOB</th>
            <th>SAP</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in sample" :key="String(row.staff_id)">
            <td>
              <RouterLink :to="`/staff/${row.staff_id}`">{{ row.lname }}, {{ row.fname }}</RouterLink>
            </td>
            <td>{{ row.work_email || '—' }}</td>
            <td>{{ row.date_of_birth || '—' }}</td>
            <td>{{ row.sap_number || '—' }}</td>
          </tr>
          <tr v-if="!sample.length">
            <td colspan="4" class="text-medium-emphasis">No incomplete records in sample.</td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </div>
</template>
