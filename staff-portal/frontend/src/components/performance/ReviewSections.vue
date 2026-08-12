<script setup lang="ts">
import { computed } from 'vue'
import type {
  PerformanceCompetencyCatalogItem,
  PerformanceContract,
  PerformanceFormState,
  PerformanceObjective,
  PerformancePhase,
  PerformanceSkillCatalogItem,
} from '@/lib/performanceApi'

type ReviewTextField =
  | 'midterm_training_review'
  | 'midterm_achievements'
  | 'midterm_non_achievements'
  | 'midterm_training_contributions'
  | 'midterm_recommended_trainings'
  | 'midterm_recommended_trainings_details'
  | 'endterm_training_review'
  | 'endterm_achievements'
  | 'endterm_non_achievements'
  | 'endterm_training_contributions'
  | 'endterm_recommended_trainings'
  | 'endterm_recommended_trainings_details'

const props = defineProps<{
  phase: Exclude<PerformancePhase, 'ppa'>
  form: PerformanceFormState
  contract: PerformanceContract
  skills: PerformanceSkillCatalogItem[]
  competencyGroups: Record<string, PerformanceCompetencyCatalogItem[]>
  competencyLabels: Record<string, string>
  periodLabel: string
  readonly: boolean
}>()

const ratingOptions = [
  { title: '5 Exceptional', value: 5 },
  { title: '4 Exceeds Expectations', value: 4 },
  { title: '3 Meets Expectations', value: 3 },
  { title: '2 Needs Improvement', value: 2 },
  { title: '1 Unsatisfactory', value: 1 },
]

const visibleObjectives = computed(() =>
  Object.entries(props.form.objectives)
    .map(([key, value]) => ({ index: Number(key), value }))
    .filter(({ value }) => String(value.objective || '').trim() !== '')
    .sort((a, b) => a.index - b.index),
)

const recommendedSkillIds = computed<Array<number | string>>({
  get: () =>
    props.phase === 'midterm'
      ? props.form.midterm_recommended_skills
      : props.form.endterm_recommended_skills,
  set: (value) => {
    if (props.phase === 'midterm') {
      props.form.midterm_recommended_skills = value
      return
    }
    props.form.endterm_recommended_skills = value
  },
})

const reviewSummary = computed(() => [
  { label: 'Recommended?', value: props.form.training_recommended || 'No' },
  {
    label: 'Required Skills',
    value:
      props.form.required_skills
        .map((id) => props.skills.find((skill) => skill.id === Number(id))?.skill || `Skill #${id}`)
        .join(', ') || 'None listed',
  },
  { label: 'Training Contributions', value: props.form.training_contributions || '—' },
  { label: 'Recommended AUC Courses', value: props.form.recommended_trainings || '—' },
  { label: 'Other Courses', value: props.form.recommended_trainings_details || '—' },
])

function objectiveAt(index: number): PerformanceObjective {
  return props.form.objectives[index]
}

function getReviewField(field: ReviewTextField): string {
  return props.form[field]
}

function setReviewField(field: ReviewTextField, value: string): void {
  props.form[field] = value as never
}

function competencyModel(): Record<string, number | string> {
  return props.phase === 'midterm' ? props.form.midterm_competency : props.form.endterm_competency
}

function competencyKey(item: PerformanceCompetencyCatalogItem): string {
  return `competency_${item.id}`
}

function competencyValue(item: PerformanceCompetencyCatalogItem): number | string | null {
  return competencyModel()[competencyKey(item)] ?? null
}

function setCompetencyValue(item: PerformanceCompetencyCatalogItem, value: number): void {
  competencyModel()[competencyKey(item)] = value
}

const trainingReviewField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_training_review' : 'endterm_training_review',
)
const achievementsField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_achievements' : 'endterm_achievements',
)
const nonAchievementsField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_non_achievements' : 'endterm_non_achievements',
)
const trainingContributionsField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_training_contributions' : 'endterm_training_contributions',
)
const recommendedTrainingsField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_recommended_trainings' : 'endterm_recommended_trainings',
)
const recommendedTrainingsDetailsField = computed<ReviewTextField>(() =>
  props.phase === 'midterm' ? 'midterm_recommended_trainings_details' : 'endterm_recommended_trainings_details',
)
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-card variant="outlined">
      <v-card-title class="text-h6">A. Personal Details</v-card-title>
      <v-card-text>
        <v-table density="compact">
          <tbody>
            <tr>
              <th class="perf-label">Name</th>
              <td>{{ [contract.fname, contract.lname].filter(Boolean).join(' ') || '—' }}</td>
              <th class="perf-label">SAP NO</th>
              <td>{{ contract.SAPNO || '—' }}</td>
            </tr>
            <tr>
              <th class="perf-label">Position</th>
              <td>{{ contract.job_name || '—' }}</td>
              <th class="perf-label">In this Position Since</th>
              <td>{{ contract.initiation_date || '—' }}</td>
            </tr>
            <tr>
              <th class="perf-label">Directorate/Department</th>
              <td>{{ contract.division_name || '—' }}</td>
              <th class="perf-label">Performance Period</th>
              <td>{{ periodLabel }}</td>
            </tr>
            <tr>
              <th class="perf-label">Direct Supervisor</th>
              <td>{{ contract.first_supervisor || form.supervisor_id || '—' }}</td>
              <th class="perf-label">Second Supervisor</th>
              <td>{{ contract.second_supervisor || form.supervisor2_id || '—' }}</td>
            </tr>
            <tr>
              <th class="perf-label">Funder</th>
              <td>{{ contract.funder || '—' }}</td>
              <th class="perf-label">Contract Type</th>
              <td>{{ contract.contract_type || '—' }}</td>
            </tr>
          </tbody>
        </v-table>
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">B. Review of Performance Objectives</v-card-title>
      <v-card-subtitle>
        Fill out the staff self-appraisal and the appraiser rating for each active objective.
      </v-card-subtitle>
      <v-card-text>
        <div v-if="visibleObjectives.length" class="d-flex flex-column ga-4">
          <div
            v-for="{ index, value } in visibleObjectives"
            :key="index"
            class="perf-objective-row"
          >
            <div class="text-subtitle-2 mb-3">Objective {{ index }}</div>
            <v-row dense>
              <v-col cols="12" md="4">
                <v-textarea :model-value="value.objective" label="Objective" rows="4" auto-grow variant="outlined" readonly />
              </v-col>
              <v-col cols="12" md="2">
                <v-text-field :model-value="value.timeline" label="Timeline" variant="outlined" readonly />
              </v-col>
              <v-col cols="12" md="3">
                <v-textarea :model-value="value.indicator" label="Deliverables and KPI's" rows="4" auto-grow variant="outlined" readonly />
              </v-col>
              <v-col cols="12" md="1">
                <v-text-field :model-value="value.weight" label="Weight" variant="outlined" readonly />
              </v-col>
              <v-col cols="12" md="4">
                <v-textarea
                  v-model="objectiveAt(index).self_appraisal"
                  :readonly="readonly"
                  label="Staff Self Appraisal"
                  rows="4"
                  auto-grow
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="2">
                <v-select
                  v-model="objectiveAt(index).appraiser_rating"
                  :items="ratingOptions"
                  :readonly="readonly"
                  :disabled="readonly"
                  label="Appraiser's Rating"
                  variant="outlined"
                />
              </v-col>
            </v-row>
          </div>
        </div>
        <v-alert v-else type="info" variant="tonal" density="compact">No objectives available for this review yet.</v-alert>
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">C. Appraiser's Comments</v-card-title>
      <v-card-text class="d-flex flex-column ga-4">
        <v-textarea
          :model-value="getReviewField(achievementsField)"
          :readonly="readonly"
          label="1. What has been achieved in relation to the Performance Objectives?"
          rows="4"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(achievementsField, String($event ?? ''))"
        />
        <v-textarea
          :model-value="getReviewField(nonAchievementsField)"
          :readonly="readonly"
          label="2. Specify non-achievements in relation to Performance Objectives"
          rows="4"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(nonAchievementsField, String($event ?? ''))"
        />
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">D. Competencies</v-card-title>
      <v-card-subtitle>
        AU Values and Core/Functional competencies are required. Leadership competencies can remain blank if not applicable.
      </v-card-subtitle>
      <v-card-text class="d-flex flex-column ga-4">
        <div
          v-for="(items, categoryKey) in competencyGroups"
          :key="categoryKey"
          class="d-flex flex-column ga-3"
        >
          <div class="text-subtitle-1 font-weight-bold">{{ competencyLabels[categoryKey] || categoryKey }}</div>
          <div
            v-for="item in items"
            :key="item.id"
            class="perf-competency-row"
          >
            <div class="mb-2">
              <div class="font-weight-medium">{{ item.id }}. {{ item.description }}</div>
              <div v-if="item.annotation" class="text-caption text-medium-emphasis">{{ item.annotation }}</div>
            </div>
            <v-radio-group
              :model-value="competencyValue(item)"
              :disabled="readonly"
              inline
              @update:model-value="setCompetencyValue(item, Number($event))"
            >
              <v-radio v-for="score in [5, 4, 3, 2, 1]" :key="score" :value="score">
                <template #label>
                  <div class="d-flex flex-column">
                    <span>{{ score }}</span>
                    <small class="text-medium-emphasis">{{ item[`score_${score}` as keyof PerformanceCompetencyCatalogItem] }}</small>
                  </div>
                </template>
              </v-radio>
            </v-radio-group>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">E. Personal Development Plan - Progress Review</v-card-title>
      <v-card-text class="d-flex flex-column ga-4">
        <v-sheet border rounded class="pa-4">
          <div class="text-subtitle-2 mb-3">Original PDP Training Plan</div>
          <div
            v-for="row in reviewSummary"
            :key="row.label"
            class="mb-3"
          >
            <div class="text-caption text-medium-emphasis">{{ row.label }}</div>
            <div class="text-body-2" style="white-space: pre-wrap">{{ row.value }}</div>
          </div>
        </v-sheet>

        <v-textarea
          :model-value="getReviewField(trainingReviewField)"
          :readonly="readonly"
          label="1. Comments on progress made against the employee's Personal Development Plan (PDP)"
          rows="5"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(trainingReviewField, String($event ?? ''))"
        />
        <v-select
          v-model="recommendedSkillIds"
          :items="skills.map((skill) => ({ title: skill.skill, value: skill.id }))"
          :disabled="readonly"
          label="2. Additional training recommended - skill area(s)"
          variant="outlined"
          multiple
          chips
          closable-chips
        />
        <v-textarea
          :model-value="getReviewField(trainingContributionsField)"
          :readonly="readonly"
          label="How will training contribute to development?"
          rows="3"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(trainingContributionsField, String($event ?? ''))"
        />
        <v-textarea
          :model-value="getReviewField(recommendedTrainingsField)"
          :readonly="readonly"
          label="Recommended course(s) from the AUC L&D Catalogue"
          rows="3"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(recommendedTrainingsField, String($event ?? ''))"
        />
        <v-textarea
          :model-value="getReviewField(recommendedTrainingsDetailsField)"
          :readonly="readonly"
          label="Other recommendable course(s)"
          rows="3"
          auto-grow
          variant="outlined"
          @update:model-value="setReviewField(recommendedTrainingsDetailsField, String($event ?? ''))"
        />
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.perf-label {
  width: 12rem;
  font-weight: 600;
  color: rgba(0, 0, 0, 0.68);
}

.perf-objective-row,
.perf-competency-row {
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 1rem;
}
</style>
