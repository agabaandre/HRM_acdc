<script setup lang="ts">
import { computed } from 'vue'

type Day = { date: string; label: string; created: number; completed: number }

const props = defineProps<{ activity: Day[] }>()

const maxValue = computed(() => Math.max(...props.activity.flatMap((day) => [day.created, day.completed]), 1))

function barHeight(value: number): string {
  return `${Math.max(8, Math.round((value / maxValue.value) * 100))}%`
}
</script>

<template>
  <v-card elevation="10" class="withbg h-100">
    <v-card-text>
      <div class="d-sm-flex align-center justify-space-between gap-3">
        <div>
          <h3 class="card-title mb-1">Delivery throughput</h3>
          <h5 class="card-subtitle">Work items opened vs completed over eight weeks</h5>
        </div>
        <div class="d-flex align-center ga-3">
          <div class="d-flex align-center ga-2">
            <span class="chart-legend chart-legend--primary" />
            <span class="text-subtitle-2">Opened</span>
          </div>
          <div class="d-flex align-center ga-2">
            <span class="chart-legend chart-legend--success" />
            <span class="text-subtitle-2">Completed</span>
          </div>
        </div>
      </div>

      <div class="activity-chart mt-6">
        <div class="activity-chart__bars">
          <div v-for="day in activity" :key="day.date" class="activity-chart__group">
            <div class="activity-chart__stack">
              <div
                class="activity-chart__bar activity-chart__bar--sent"
                :style="{ height: barHeight(day.created) }"
                :title="`${day.created} opened`"
              />
              <div
                class="activity-chart__bar activity-chart__bar--done"
                :style="{ height: barHeight(day.completed) }"
                :title="`${day.completed} completed`"
              />
            </div>
            <span class="activity-chart__label">{{ day.label }}</span>
          </div>
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.activity-chart__bars {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  min-height: 220px;
  padding-top: 8px;
}
.activity-chart__group {
  flex: 1 1 0;
  min-width: 0;
  text-align: center;
}
.activity-chart__stack {
  display: flex;
  align-items: flex-end;
  justify-content: center;
  gap: 6px;
  height: 180px;
}
.activity-chart__bar {
  width: 14px;
  min-height: 8px;
  border-radius: 6px 6px 2px 2px;
}
.activity-chart__bar--sent {
  background: rgb(var(--v-theme-primary));
}
.activity-chart__bar--done {
  background: rgb(var(--v-theme-success));
}
.activity-chart__label {
  display: block;
  margin-top: 10px;
  font-size: 0.75rem;
  opacity: 0.72;
}
.chart-legend {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}
.chart-legend--primary {
  background: rgb(var(--v-theme-primary));
}
.chart-legend--success {
  background: rgb(var(--v-theme-success));
}
</style>
