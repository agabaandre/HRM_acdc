<script setup lang="ts">
import type { PerformanceTrailEntry } from '@/lib/performanceApi'

defineProps<{
  items: PerformanceTrailEntry[]
}>()

function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '—'
  }

  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) {
    return value
  }

  return new Intl.DateTimeFormat(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed)
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="text-h6">Approval Trail</v-card-title>
    <v-card-text>
      <v-table density="compact">
        <thead>
          <tr>
            <th>Staff</th>
            <th>Action</th>
            <th>Date</th>
            <th>Comment</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in items" :key="`${item.staff_id}-${item.action}-${index}`">
            <td>{{ item.staff_id }}</td>
            <td>{{ item.action }}</td>
            <td>{{ formatDate(item.created_at) }}</td>
            <td style="white-space: pre-wrap">{{ item.comments || '—' }}</td>
          </tr>
          <tr v-if="!items.length">
            <td colspan="4" class="text-medium-emphasis">No approval activity yet.</td>
          </tr>
        </tbody>
      </v-table>
    </v-card-text>
  </v-card>
</template>
