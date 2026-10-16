<script setup lang="ts">
import { computed } from 'vue'
import type { PerformanceCompetencyCatalogItem } from '@/lib/performanceApi'

const props = defineProps<{
  competencyGroups: Record<string, PerformanceCompetencyCatalogItem[]>
  competencyLabels: Record<string, string>
  modelValue: Record<string, number | string>
  readonly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, number | string>]
}>()

const scores = [5, 4, 3, 2, 1] as const

/** CI3 order: AU Values → Core → Functional → Leadership */
const categoryOrder = computed(() => {
  const preferred = ['values', 'core', 'functional', 'leadership']
  const labels = props.competencyLabels || {}
  const fromLabels = preferred.filter((key) => (props.competencyGroups[key] || []).length > 0)
  const extras = Object.keys(props.competencyGroups).filter(
    (key) => !preferred.includes(key) && (props.competencyGroups[key] || []).length > 0,
  )
  return fromLabels.length || Object.keys(labels).length
    ? [...fromLabels, ...extras]
    : Object.keys(props.competencyGroups)
})

function keyFor(item: PerformanceCompetencyCatalogItem): string {
  return `competency_${item.id}`
}

function selected(item: PerformanceCompetencyCatalogItem): number | null {
  const raw = props.modelValue[keyFor(item)]
  if (raw === undefined || raw === null || raw === '') return null
  const n = Number(raw)
  return Number.isFinite(n) ? n : null
}

function select(item: PerformanceCompetencyCatalogItem, score: number): void {
  if (props.readonly) return
  emit('update:modelValue', {
    ...props.modelValue,
    [keyFor(item)]: score,
  })
}

function scoreText(item: PerformanceCompetencyCatalogItem, score: number): string {
  const key = `score_${score}` as keyof PerformanceCompetencyCatalogItem
  return String(item[key] || '')
}
</script>

<template>
  <div class="competency-rating-tables">
    <p class="text-body-2 text-medium-emphasis mb-4">
      Rate each competency from 5 (highest) to 1 (lowest). AU Values and Core/Functional competencies are
      required; Leadership can remain blank if not applicable.
    </p>

    <div v-for="categoryKey in categoryOrder" :key="categoryKey" class="mb-6">
      <h3 class="text-subtitle-1 font-weight-bold mb-2">
        {{ competencyLabels[categoryKey] || categoryKey }}
      </h3>

      <div class="competency-table-wrap">
        <table class="competency-table">
          <thead>
            <tr>
              <th class="competency-table__competency">Competency</th>
              <th v-for="score in scores" :key="score" class="competency-table__score">{{ score }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in competencyGroups[categoryKey] || []" :key="item.id">
              <td class="competency-table__competency">
                <strong>{{ item.id }}. {{ item.description }}</strong>
                <div v-if="item.annotation" class="competency-table__annotation">{{ item.annotation }}</div>
              </td>
              <td v-for="score in scores" :key="score" class="competency-table__score">
                <label class="competency-table__cell" :class="{ 'is-selected': selected(item) === score }">
                  <input
                    type="radio"
                    class="competency-table__radio"
                    :name="keyFor(item)"
                    :value="score"
                    :checked="selected(item) === score"
                    :disabled="readonly"
                    @change="select(item, score)"
                  />
                  <span class="competency-table__score-text">{{ scoreText(item, score) }}</span>
                </label>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.competency-table-wrap {
  overflow-x: auto;
  border: 1px solid #dfe5ef;
  border-radius: 8px;
  background: #fff;
}

.competency-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 860px;
  font-size: 0.82rem;
}

.competency-table th,
.competency-table td {
  border: 1px solid #e5e9f0;
  vertical-align: top;
  padding: 0.65rem 0.5rem;
}

.competency-table thead th {
  background: #f8fafc;
  color: #3a4752;
  font-weight: 700;
  text-align: center;
}

.competency-table__competency {
  width: 34%;
  text-align: left !important;
  color: #3a4752;
}

.competency-table__annotation {
  margin-top: 0.25rem;
  color: #768b9e;
  font-size: 0.75rem;
  line-height: 1.35;
  white-space: pre-wrap;
}

.competency-table__score {
  width: 13.2%;
  text-align: center;
}

.competency-table__cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.35rem;
  min-height: 100%;
  cursor: pointer;
  margin: 0;
}

.competency-table__cell.is-selected {
  background: rgba(13, 122, 58, 0.06);
  border-radius: 4px;
}

.competency-table__radio {
  accent-color: #0d7a3a;
  width: 1rem;
  height: 1rem;
  margin: 0;
  cursor: pointer;
}

.competency-table__radio:disabled {
  cursor: not-allowed;
}

.competency-table__score-text {
  color: #455a64;
  line-height: 1.3;
  font-size: 0.72rem;
}
</style>
