<script setup lang="ts">
import { computed } from 'vue'
import PortalRichTextEditor from '@/components/atoms/PortalRichTextEditor.vue'
import { hasRichTextContent } from '@/lib/richText'
import type { PerformanceFormState, PerformanceObjective } from '@/lib/performanceApi'

const props = defineProps<{
  form: PerformanceFormState
  readonly: boolean
  variant: 'plan' | 'review'
  periodEndYear?: number
}>()

const ratingOptions = [
  { title: '5 Exceptional', value: 5 },
  { title: '4 Exceeds Expectations', value: 4 },
  { title: '3 Meets Expectations', value: 3 },
  { title: '2 Needs Improvement', value: 2 },
  { title: '1 Unsatisfactory', value: 1 },
]

const isReview = computed(() => props.variant === 'review')
const planFieldsLocked = computed(() => props.readonly || isReview.value)

type ObjectiveRow = {
  index: number
  displayNum: number
  required: boolean
}

const rows = computed<ObjectiveRow[]>(() => {
  if (!isReview.value) {
    return [1, 2, 3, 4, 5].map((index) => ({
      index,
      displayNum: index,
      required: index <= 3,
    }))
  }

  return Object.entries(props.form.objectives)
    .map(([key, value]) => ({ index: Number(key), value }))
    .filter(({ value }) => hasRichTextContent(String(value.objective || '')))
    .sort((a, b) => a.index - b.index)
    .map(({ index }, position) => ({
      index,
      displayNum: position + 1,
      required: false,
    }))
})

function blankObjective(): PerformanceObjective {
  return {
    objective: '',
    timeline: '',
    indicator: '',
    weight: '',
    self_appraisal: '',
    appraiser_rating: '',
  }
}

function objectiveAt(index: number): PerformanceObjective {
  const existing = props.form.objectives[index]
  if (existing) {
    return existing
  }
  const created = blankObjective()
  props.form.objectives[index] = created
  return created
}
</script>

<template>
  <div class="perf-obj-table-wrap">
    <table class="perf-obj-table" :class="{ 'perf-obj-table--review': isReview }">
      <colgroup>
        <col class="perf-obj-table__col-num">
        <col class="perf-obj-table__col-objective">
        <col class="perf-obj-table__col-timeline">
        <col class="perf-obj-table__col-deliverables">
        <col class="perf-obj-table__col-weight">
        <col v-if="isReview" class="perf-obj-table__col-appraisal">
        <col v-if="isReview" class="perf-obj-table__col-rating">
      </colgroup>
      <thead>
        <tr>
          <th class="perf-obj-table__num">#</th>
          <th>
            Objective
            <small v-if="!isReview">Statement of the result that needs to be achieved</small>
          </th>
          <th class="perf-obj-table__timeline">
            Timeline
            <small v-if="!isReview">Timeframe within which the result is to be achieved</small>
          </th>
          <th>
            {{ isReview ? 'Deliverables & KPIs' : "Deliverables and KPI's" }}
            <small v-if="!isReview">
              Deliverables — evidence the result has been achieved; KPIs show how well it was achieved
            </small>
          </th>
          <th class="perf-obj-table__weight">
            {{ isReview ? 'Weight (%)' : 'Weight' }}
            <small v-if="!isReview">The total weight of all objectives should be 100%</small>
          </th>
          <template v-if="isReview">
            <th>Staff Self Appraisal <span class="perf-obj-table__req">*</span></th>
            <th class="perf-obj-table__rating">Appraiser's Rating <span class="perf-obj-table__req">*</span></th>
          </template>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!rows.length">
          <td :colspan="isReview ? 7 : 5" class="perf-obj-table__empty">
            No objectives available for this review yet.
          </td>
        </tr>
        <tr v-for="row in rows" :key="row.index">
          <td class="perf-obj-table__num">
            {{ row.displayNum }}
            <span v-if="row.required" class="perf-obj-table__req">*</span>
          </td>
          <td class="perf-obj-table__rich">
            <PortalRichTextEditor
              v-model="objectiveAt(row.index).objective"
              :disabled="planFieldsLocked"
              keep-toolbar
              :min-rows="3"
            />
          </td>
          <td>
            <UDateInput
              v-model="objectiveAt(row.index).timeline"
              :disabled="planFieldsLocked"
              :placeholder="!isReview && row.required ? `${periodEndYear}-12-31` : ''"
              :clearable="!planFieldsLocked"
              hide-details
              density="compact"
            />
          </td>
          <td class="perf-obj-table__rich">
            <PortalRichTextEditor
              v-model="objectiveAt(row.index).indicator"
              :disabled="planFieldsLocked"
              keep-toolbar
              :min-rows="3"
            />
          </td>
          <td>
            <v-text-field
              v-model="objectiveAt(row.index).weight"
              :readonly="planFieldsLocked"
              type="number"
              min="0"
              max="100"
              variant="outlined"
              density="compact"
              hide-details
            />
          </td>
          <template v-if="isReview">
            <td class="perf-obj-table__rich">
              <PortalRichTextEditor
                v-model="objectiveAt(row.index).self_appraisal"
                :disabled="readonly"
                keep-toolbar
                :min-rows="3"
              />
            </td>
            <td>
              <v-select
                v-model="objectiveAt(row.index).appraiser_rating"
                :items="ratingOptions"
                :readonly="readonly"
                :disabled="readonly"
                variant="outlined"
                density="compact"
                hide-details
                placeholder="Select"
              />
            </td>
          </template>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.perf-obj-table-wrap {
  overflow-x: auto;
}

.perf-obj-table {
  width: 100%;
  min-width: 52rem;
  border-collapse: collapse;
  table-layout: fixed;
}

.perf-obj-table--review {
  min-width: 78rem;
}

.perf-obj-table th,
.perf-obj-table td {
  border: 1px solid rgba(58, 71, 82, 0.16);
  padding: 0.55rem 0.5rem;
  vertical-align: top;
  text-align: left;
}

.perf-obj-table thead th {
  background: #f4f6f8;
  font-size: 0.8rem;
  font-weight: 650;
  color: #37474f;
  line-height: 1.3;
}

.perf-obj-table thead small {
  display: block;
  margin-top: 0.2rem;
  font-size: 0.68rem;
  font-weight: 400;
  color: #607d8b;
  line-height: 1.35;
}

.perf-obj-table__col-num {
  width: 2.6rem;
}

.perf-obj-table__col-timeline {
  width: 9.25rem;
}

.perf-obj-table__col-weight {
  width: 5.5rem;
}

.perf-obj-table__col-rating {
  width: 12rem;
}

.perf-obj-table--review .perf-obj-table__col-objective,
.perf-obj-table--review .perf-obj-table__col-deliverables,
.perf-obj-table--review .perf-obj-table__col-appraisal {
  width: 18%;
}

.perf-obj-table__num {
  width: 2.5rem;
  text-align: center;
  font-weight: 650;
}

.perf-obj-table__timeline {
  width: 9.5rem;
}

.perf-obj-table__weight {
  width: 5.5rem;
}

.perf-obj-table__rating {
  width: 11.5rem;
}

.perf-obj-table__req {
  color: #c62828;
  font-weight: 700;
}

.perf-obj-table__empty {
  text-align: center;
  color: #78909c;
  padding: 1.25rem 0.75rem;
}

.perf-obj-table__rich :deep(.portal-rich-field) {
  gap: 0;
}

.perf-obj-table__rich :deep(.portal-rich-editor .ql-toolbar.ql-snow),
.perf-obj-table__rich :deep(.portal-rich-editor .ql-container.ql-snow) {
  border-radius: 6px;
}

.perf-obj-table__rich :deep(.portal-rich-editor .ql-toolbar.ql-snow) {
  border-bottom-left-radius: 0;
  border-bottom-right-radius: 0;
  padding: 0.2rem 0.35rem;
}

.perf-obj-table__rich :deep(.portal-rich-editor .ql-container.ql-snow) {
  border-top-left-radius: 0;
  border-top-right-radius: 0;
}

.perf-obj-table__rich :deep(.ql-editor) {
  padding: 0.45rem 0.6rem;
}

.perf-obj-table :deep(.v-input) {
  margin: 0;
}

.perf-obj-table :deep(.v-field) {
  --v-input-control-height: 40px;
}
</style>
