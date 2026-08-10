<script setup lang="ts">
import { computed } from 'vue'
import { formatDateTime, formatDateTimeLong } from '../../lib/formatDateTime'

export interface TimelineItem {
  key: string
  label: string
  at: string | null
  detail?: string | null
  actor?: string | null
  kind?: string
}

const props = defineProps<{
  items: TimelineItem[]
}>()

const rows = computed(() =>
  (props.items || []).filter((item) => item?.at && item?.label),
)

function kindClass(kind?: string): string {
  return kind === 'milestone' ? 'tl-node--milestone' : 'tl-node--event'
}
</script>

<template>
  <section v-if="rows.length" class="ticket-timeline" aria-label="Ticket timeline">
    <h3 class="h3">Ticket timeline</h3>
    <ol class="tl-list">
      <li
        v-for="(item, index) in rows"
        :key="`${item.key}-${item.at}-${index}`"
        class="tl-item"
        :class="kindClass(item.kind)"
      >
        <div class="tl-rail" aria-hidden="true">
          <span class="tl-dot" />
          <span v-if="index < rows.length - 1" class="tl-line" />
        </div>
        <div class="tl-body">
          <div class="tl-head">
            <strong class="tl-label">{{ item.label }}</strong>
            <time
              v-if="item.at"
              class="tl-time"
              :datetime="item.at"
              :title="formatDateTimeLong(item.at)"
            >
              {{ formatDateTime(item.at) }}
            </time>
          </div>
          <p v-if="item.detail" class="tl-detail">{{ item.detail }}</p>
          <p v-if="item.actor" class="tl-actor">by {{ item.actor }}</p>
        </div>
      </li>
    </ol>
  </section>
</template>

<style scoped>
.ticket-timeline {
  margin: 0 0 1.25rem;
  padding: 0.9rem 1rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #f8fafc;
}
.h3 {
  margin: 0 0 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: #0f172a;
}
.tl-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.tl-item {
  display: grid;
  grid-template-columns: 1.25rem 1fr;
  gap: 0.65rem;
  min-width: 0;
}
.tl-rail {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.tl-dot {
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 999px;
  margin-top: 0.3rem;
  background: #94a3b8;
  border: 2px solid #e2e8f0;
  box-shadow: 0 0 0 2px #f8fafc;
  flex: 0 0 auto;
  z-index: 1;
}
.tl-node--milestone .tl-dot {
  background: #119a48;
  border-color: #bbf7d0;
}
.tl-node--event .tl-dot {
  background: #0ea5e9;
  border-color: #bae6fd;
}
.tl-line {
  flex: 1 1 auto;
  width: 2px;
  min-height: 1.1rem;
  margin: 0.15rem 0 0;
  background: #cbd5e1;
}
.tl-body {
  min-width: 0;
  padding-bottom: 0.85rem;
}
.tl-item:last-child .tl-body {
  padding-bottom: 0.15rem;
}
.tl-head {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.35rem 0.75rem;
}
.tl-label {
  font-size: 0.92rem;
  color: #0f172a;
}
.tl-time {
  font-size: 0.8rem;
  color: #64748b;
}
.tl-detail,
.tl-actor {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  line-height: 1.4;
  color: #475569;
  word-break: break-word;
}
.tl-actor {
  color: #64748b;
  font-style: italic;
}
</style>
