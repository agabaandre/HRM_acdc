<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPageSkeleton from '@/components/molecules/PortalPageSkeleton.vue'
import StaffSortableTh from '@/components/molecules/StaffSortableTh.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { useClientTableSort } from '@/composables/useStaffTableSort'
import { fetchDataQuality } from '@/lib/staffApi'

const loading = ref(true)
const error = ref<string | null>(null)
const counts = ref({ missing_email: 0, missing_dob: 0, missing_sap: 0 })
const sample = ref<Array<Record<string, unknown>>>([])

const { sortBy, sortDir, toggleSort, sortIcon, sortedRows } = useClientTableSort(
  sample,
  {
    name: (row) => `${row.fname || ''} ${row.lname || ''}`.trim(),
    email: (row) => (row.work_email as string) || '',
    dob: (row) => (row.date_of_birth as string) || '',
    sap: (row) => (row.sap_number as string) || '',
  },
  'name',
)

const displayRows = computed(() => sortedRows())

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
    <PortalPageSkeleton v-if="loading" variant="table" :rows="6" :cols="4" />

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
      <v-card class="portal-data-table-card" variant="outlined">
        <v-table density="compact" class="portal-data-table">
          <thead>
            <tr>
              <StaffSortableTh
                label="Name"
                :active="sortBy === 'name'"
                :direction="sortDir"
                :icon="sortIcon('name')"
                @click="toggleSort('name')"
              />
              <StaffSortableTh
                label="Email"
                :active="sortBy === 'email'"
                :direction="sortDir"
                :icon="sortIcon('email')"
                @click="toggleSort('email')"
              />
              <StaffSortableTh
                label="DOB"
                :active="sortBy === 'dob'"
                :direction="sortDir"
                :icon="sortIcon('dob')"
                @click="toggleSort('dob')"
              />
              <StaffSortableTh
                label="SAP"
                :active="sortBy === 'sap'"
                :direction="sortDir"
                :icon="sortIcon('sap')"
                @click="toggleSort('sap')"
              />
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in displayRows" :key="String(row.staff_id)">
              <td>
                <RouterLink :to="`/staff/${row.staff_id}`">{{ row.lname }}, {{ row.fname }}</RouterLink>
              </td>
              <td>{{ row.work_email || '—' }}</td>
              <td>{{ row.date_of_birth || '—' }}</td>
              <td>{{ row.sap_number || '—' }}</td>
            </tr>
            <tr v-if="!displayRows.length">
              <td colspan="4" class="text-medium-emphasis">No incomplete records in sample.</td>
            </tr>
          </tbody>
        </v-table>
      </v-card>
    </template>
  </div>
</template>
