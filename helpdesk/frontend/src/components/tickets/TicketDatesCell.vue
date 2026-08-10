<script setup lang="ts">
import { formatDateTime } from '../../lib/formatDateTime'

defineProps<{
  createdAt?: string | null
  resolvedAt?: string | null
  closedAt?: string | null
}>()
</script>

<template>
  <div class="hd-dt-dates">
    <div v-if="createdAt" class="hd-dt-date-row">
      <span class="hd-dt-date-label">Created</span>
      <time class="hd-dt-created" :datetime="createdAt" :title="formatDateTime(createdAt)">
        {{ formatDateTime(createdAt) }}
      </time>
    </div>
    <div v-if="resolvedAt" class="hd-dt-date-row">
      <span class="hd-dt-date-label">Resolved</span>
      <time class="hd-dt-created" :datetime="resolvedAt" :title="formatDateTime(resolvedAt)">
        {{ formatDateTime(resolvedAt) }}
      </time>
    </div>
    <div v-if="closedAt" class="hd-dt-date-row">
      <span class="hd-dt-date-label">Closed</span>
      <time class="hd-dt-created" :datetime="closedAt" :title="formatDateTime(closedAt)">
        {{ formatDateTime(closedAt) }}
      </time>
    </div>
    <span v-if="!createdAt && !resolvedAt && !closedAt" class="hd-dt-empty">—</span>
  </div>
</template>

<style scoped>
.hd-dt-dates {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  min-width: 0;
}
.hd-dt-date-row {
  display: flex;
  flex-direction: column;
  gap: 0.05rem;
  line-height: 1.25;
}
.hd-dt-date-label {
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #64748b;
}
.hd-dt-created {
  font-size: 0.8rem;
  color: #334155;
  white-space: nowrap;
}
</style>
