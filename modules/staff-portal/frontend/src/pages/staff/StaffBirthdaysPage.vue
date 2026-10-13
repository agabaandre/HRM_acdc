<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPageSkeleton from '@/components/molecules/PortalPageSkeleton.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import StaffSortableTh from '@/components/molecules/StaffSortableTh.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import { useClientTableSort } from '@/composables/useStaffTableSort'
import {
  fetchBirthdays,
  type BirthdayRange,
  type BirthdayRow,
} from '@/lib/staffApi'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()
const loading = ref(true)
const error = ref<string | null>(null)
const rows = ref<BirthdayRow[]>([])
const range = ref<BirthdayRange>('today')
const total = ref(0)

const { sortBy, sortDir, toggleSort, sortIcon, sortedRows } = useClientTableSort<BirthdayRow>(
  rows,
  {
    name: (row) => [row.fname, row.lname, row.oname].filter(Boolean).join(' '),
    date_of_birth: (row) => row.date_of_birth || '',
    next_birthday: (row) => row.next_birthday || '',
    age: (row) => row.age ?? null,
    division: (row) => row.division_name || '',
    email: (row) => row.work_email || '',
  },
  'name',
)

const displayRows = computed(() => sortedRows())

const birthdayRangeItems = computed<PortalPillNavItem[]>(() => [
  {
    key: 'today',
    label: locale.t('subnav.bday_today', 'Today'),
    icon: 'fa-solid fa-sun',
    active: range.value === 'today',
  },
  {
    key: 'tomorrow',
    label: locale.t('subnav.bday_tomorrow', 'Tomorrow'),
    icon: 'fa-solid fa-cloud-sun',
    active: range.value === 'tomorrow',
  },
  {
    key: 'next_7',
    label: locale.t('subnav.bday_next_7', 'Next 7 days'),
    icon: 'fa-solid fa-calendar-week',
    active: range.value === 'next_7',
  },
  {
    key: 'next_30',
    label: locale.t('subnav.bday_next_30', 'Next 30 days'),
    icon: 'fa-solid fa-calendar',
    active: range.value === 'next_30',
  },
])

const rangeLabel = computed(
  () => birthdayRangeItems.value.find((t) => t.key === range.value)?.label || locale.t('subnav.birthdays', 'Birthdays'),
)

function personName(row: BirthdayRow): string {
  return [row.lname, row.fname, row.oname]
    .map((part) => (typeof part === 'string' ? part.trim() : ''))
    .filter(Boolean)
    .join(' ')
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const res = await fetchBirthdays(range.value)
    rows.value = res.data || []
    total.value = res.meta?.total ?? rows.value.length
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load birthdays')
    rows.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

watch(range, () => void load())
onMounted(() => void load())

function setRange(key: string) {
  range.value = key as BirthdayRange
}
</script>

<template>
  <div>
    <PortalPageChrome
      title="Birthdays"
      :lede="`${rangeLabel} — ${total} staff`"
      icon="fa-solid fa-cake-candles"
    >
      <template #tabs>
        <StaffSubnav />
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>

    <PortalPillSubnav
      class="mb-3"
      :items="birthdayRangeItems"
      :aria-label="locale.t('subnav.birthday_range', 'Birthday range')"
      @select="setRange"
    />

    <PortalPageSkeleton v-if="loading" variant="table" :rows="8" :cols="6" />

    <v-card v-else class="portal-data-table-card mb-3" variant="outlined">
      <v-table density="compact" class="portal-data-table">
        <thead>
          <tr>
            <th style="width: 3rem">#</th>
            <StaffSortableTh
              label="Name"
              :active="sortBy === 'name'"
              :direction="sortDir"
              :icon="sortIcon('name')"
              @click="toggleSort('name')"
            />
            <StaffSortableTh
              label="Date of birth"
              :active="sortBy === 'date_of_birth'"
              :direction="sortDir"
              :icon="sortIcon('date_of_birth')"
              @click="toggleSort('date_of_birth')"
            />
            <StaffSortableTh
              label="Next birthday"
              :active="sortBy === 'next_birthday'"
              :direction="sortDir"
              :icon="sortIcon('next_birthday')"
              @click="toggleSort('next_birthday')"
            />
            <StaffSortableTh
              label="Age"
              :active="sortBy === 'age'"
              :direction="sortDir"
              :icon="sortIcon('age')"
              @click="toggleSort('age')"
            />
            <StaffSortableTh
              label="Division"
              :active="sortBy === 'division'"
              :direction="sortDir"
              :icon="sortIcon('division')"
              @click="toggleSort('division')"
            />
            <StaffSortableTh
              label="Email"
              :active="sortBy === 'email'"
              :direction="sortDir"
              :icon="sortIcon('email')"
              @click="toggleSort('email')"
            />
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in displayRows" :key="row.staff_id">
            <td>
              <span class="portal-dt-row-num">{{ index + 1 }}</span>
            </td>
            <td>
              <RouterLink :to="`/staff/${row.staff_id}`">{{ personName(row) || `Staff ${row.staff_id}` }}</RouterLink>
            </td>
            <td>{{ row.date_of_birth || '—' }}</td>
            <td>{{ row.next_birthday || '—' }}</td>
            <td>{{ row.age != null ? row.age : '—' }}</td>
            <td>{{ row.division_name || '—' }}</td>
            <td>{{ row.work_email || '—' }}</td>
          </tr>
          <tr v-if="!displayRows.length">
            <td colspan="7" class="text-medium-emphasis text-center py-6">
              No birthdays for {{ rangeLabel.toLowerCase() }}.
            </td>
          </tr>
        </tbody>
      </v-table>
    </v-card>
  </div>
</template>
