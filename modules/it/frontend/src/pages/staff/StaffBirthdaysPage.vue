<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import PortalPillSubnav, { type PortalPillNavItem } from '@/components/molecules/PortalPillSubnav.vue'
import StaffSubnav from '@/components/molecules/StaffSubnav.vue'
import {
  fetchBirthdays,
  type BirthdayRange,
  type BirthdayRow,
} from '@/lib/staffApi'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()
const loading = ref(false)
const error = ref<string | null>(null)
const rows = ref<BirthdayRow[]>([])
const range = ref<BirthdayRange>('today')
const total = ref(0)

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

    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-table v-else density="compact" class="portal-data-table">
      <thead>
        <tr>
          <th style="width: 3rem">#</th>
          <th>Name</th>
          <th>Date of birth</th>
          <th>Next birthday</th>
          <th>Age</th>
          <th>Division</th>
          <th>Email</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, index) in rows" :key="row.staff_id">
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
        <tr v-if="!rows.length">
          <td colspan="7" class="text-medium-emphasis text-center py-6">
            No birthdays for {{ rangeLabel.toLowerCase() }}.
          </td>
        </tr>
      </tbody>
    </v-table>
  </div>
</template>

<style scoped>
.birthday-tabs {
  border-bottom: 1px solid rgba(58, 71, 82, 0.12);
}
.birthday-tabs :deep(.v-tab) {
  text-transform: none;
  letter-spacing: 0;
  font-weight: 500;
  min-width: auto;
}
.birthday-tabs :deep(.v-tab--selected) {
  background: rgba(17, 154, 72, 0.08);
  border-radius: 0.35rem 0.35rem 0 0;
}
</style>
