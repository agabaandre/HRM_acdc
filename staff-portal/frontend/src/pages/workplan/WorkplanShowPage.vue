<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { fetchWorkplan } from '@/lib/workplanApi'

const route = useRoute()
const loading = ref(false)
const error = ref<string | null>(null)
const plan = ref<Record<string, unknown> | null>(null)
const subs = ref<Array<Record<string, unknown>>>([])
const weekly = ref<Array<Record<string, unknown>>>([])
const ingestedFrom = ref('')

const planId = computed(() => {
  const fromParam = Number(route.params.id)
  if (fromParam) return fromParam
  return Number(route.query.id || route.query.show) || 0
})

const title = computed(() => String(plan.value?.activity_name || 'Workplan activity'))

async function load() {
  if (!planId.value) return
  loading.value = true
  error.value = null
  try {
    const res = await fetchWorkplan(planId.value)
    plan.value = res.plan as Record<string, unknown>
    subs.value = res.sub_activities as Array<Record<string, unknown>>
    weekly.value = (res.weekly_tasks || []) as Array<Record<string, unknown>>
    ingestedFrom.value = 'workplan_tasks → work_planner_tasks → work_plan_weekly_tasks'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load workplan')
    plan.value = null
    subs.value = []
    weekly.value = []
  } finally {
    loading.value = false
  }
}

watch(planId, () => void load())
onMounted(() => void load())
</script>

<template>
  <div>
    <PortalPageChrome :title="title" lede="Activity detail, planner sub-activities, and weekly tasks.">
      <template #actions>
        <RouterLink to="/workplan" style="text-decoration:none">
          <v-btn size="small" variant="outlined">All activities</v-btn>
        </RouterLink>
        <RouterLink :to="{ path: '/tasks/weekly' }" style="text-decoration:none" class="ms-2">
          <v-btn size="small" variant="tonal">Weekly tasks</v-btn>
        </RouterLink>
      </template>
    </PortalPageChrome>

    <v-alert v-if="ingestedFrom && plan" type="info" variant="tonal" class="mb-3" density="compact">
      Chain: {{ ingestedFrom }}
    </v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <template v-else-if="plan">
      <v-sheet border rounded class="pa-3 mb-4">
        <v-row dense>
          <v-col cols="12" md="4"><strong>Division:</strong> {{ plan.division_name || '—' }}</v-col>
          <v-col cols="12" md="4"><strong>Year:</strong> {{ plan.year || '—' }}</v-col>
          <v-col cols="12" md="4"><strong>Budget:</strong> {{ plan.has_budget ? 'Yes' : 'No' }}</v-col>
          <v-col cols="12"><strong>Broad activity:</strong> {{ plan.broad_activity || '—' }}</v-col>
          <v-col cols="12"><strong>Outcome:</strong> {{ plan.intermediate_outcome || '—' }}</v-col>
          <v-col cols="12"><strong>Indicator:</strong> {{ plan.output_indicator || '—' }}</v-col>
          <v-col cols="12"><strong>Target:</strong> {{ plan.cumulative_target || '—' }}</v-col>
        </v-row>
      </v-sheet>

      <div class="text-subtitle-2 mb-2">Sub-activities ({{ subs.length }})</div>
      <v-table density="compact" class="mb-6">
        <thead>
          <tr>
            <th>Activity</th>
            <th>Start</th>
            <th>End</th>
            <th>Priority</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(s, i) in subs" :key="String(s.activity_id ?? s.id ?? i)">
            <td>{{ s.activity_name || '—' }}</td>
            <td>{{ s.start_date || '—' }}</td>
            <td>{{ s.end_date || '—' }}</td>
            <td>{{ s.priority || '—' }}</td>
            <td>{{ s.status || '—' }}</td>
          </tr>
          <tr v-if="!subs.length">
            <td colspan="5" class="text-medium-emphasis">No sub-activities.</td>
          </tr>
        </tbody>
      </v-table>

      <div class="text-subtitle-2 mb-2">Weekly tasks ({{ weekly.length }})</div>
      <v-table density="compact">
        <thead>
          <tr>
            <th>Week</th>
            <th>Staff</th>
            <th>Activity</th>
            <th>Start</th>
            <th>End</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(w, i) in weekly" :key="String(w.activity_id ?? i)">
            <td>{{ w.week || '—' }}</td>
            <td>{{ w.staff_name || w.staff_id || '—' }}</td>
            <td>{{ w.activity_name || '—' }}</td>
            <td>{{ w.start_date || '—' }}</td>
            <td>{{ w.end_date || '—' }}</td>
            <td>{{ w.status || '—' }}</td>
          </tr>
          <tr v-if="!weekly.length">
            <td colspan="6" class="text-medium-emphasis">No weekly tasks linked to this activity.</td>
          </tr>
        </tbody>
      </v-table>
    </template>
  </div>
</template>
