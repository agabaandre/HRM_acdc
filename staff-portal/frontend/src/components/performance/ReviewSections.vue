<script setup lang="ts">
import { computed } from 'vue'
import PortalRichText from '@/components/atoms/PortalRichText.vue'
import PortalRichTextEditor from '@/components/atoms/PortalRichTextEditor.vue'
import CompetencyRatingTable from '@/components/performance/CompetencyRatingTable.vue'
import PerformanceObjectivesTable from '@/components/performance/PerformanceObjectivesTable.vue'
import {
  normalizePerformanceSkillIds,
  performanceSkillItems,
  type PerformanceFormState,
  type PerformancePhase,
  type PerformanceSkillCatalogItem,
  type PerformanceCompetencyCatalogItem,
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
  skills: PerformanceSkillCatalogItem[]
  competencyGroups: Record<string, PerformanceCompetencyCatalogItem[]>
  competencyLabels: Record<string, string>
  readonly: boolean
}>()

const skillItems = computed(() => performanceSkillItems(props.skills))

const recommendedSkillIds = computed<number[]>({
  get: () =>
    normalizePerformanceSkillIds(
      props.phase === 'midterm'
        ? props.form.midterm_recommended_skills
        : props.form.endterm_recommended_skills,
    ),
  set: (value) => {
    if (props.phase === 'midterm') {
      props.form.midterm_recommended_skills = value
      return
    }
    props.form.endterm_recommended_skills = value
  },
})

const reviewSummary = computed(() => [
  { label: 'Recommended?', value: props.form.training_recommended || 'No', rich: false },
  {
    label: 'Required Skills',
    value:
      props.form.required_skills
        .map((id) => props.skills.find((skill) => Number(skill.id) === Number(id))?.skill || `Skill #${id}`)
        .join(', ') || 'None listed',
    rich: false,
  },
  { label: 'Training Contributions', value: props.form.training_contributions || '', rich: true },
  { label: 'Recommended AUC Courses', value: props.form.recommended_trainings || '', rich: true },
  { label: 'Other Courses', value: props.form.recommended_trainings_details || '', rich: true },
])

function getReviewField(field: ReviewTextField): string {
  return props.form[field]
}

function setReviewField(field: ReviewTextField, value: string): void {
  props.form[field] = value as never
}

const competencyScores = computed<Record<string, number | string>>({
  get: () => {
    const raw = props.phase === 'midterm' ? props.form.midterm_competency : props.form.endterm_competency
    if (Array.isArray(raw) || raw == null) return {}
    return raw as Record<string, number | string>
  },
  set: (value) => {
    if (props.phase === 'midterm') {
      props.form.midterm_competency = value
      return
    }
    props.form.endterm_competency = value
  },
})

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
      <v-card-title class="text-h6">B. Review of Performance Objectives</v-card-title>
      <v-card-subtitle>
        Fill out the staff self-appraisal and the appraiser rating for each active objective.
      </v-card-subtitle>
      <v-card-text>
        <PerformanceObjectivesTable
          :form="form"
          variant="review"
          :readonly="readonly"
        />
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">C. Appraiser's Comments</v-card-title>
      <v-card-text class="d-flex flex-column ga-4">
        <PortalRichText
          v-if="readonly"
          :value="getReviewField(achievementsField)"
          label="1. What has been achieved in relation to the Performance Objectives?"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(achievementsField)"
          label="1. What has been achieved in relation to the Performance Objectives?"
          :min-rows="4"
          @update:model-value="setReviewField(achievementsField, $event)"
        />
        <PortalRichText
          v-if="readonly"
          :value="getReviewField(nonAchievementsField)"
          label="2. Specify non-achievements in relation to Performance Objectives"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(nonAchievementsField)"
          label="2. Specify non-achievements in relation to Performance Objectives"
          :min-rows="4"
          @update:model-value="setReviewField(nonAchievementsField, $event)"
        />
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">D. Competencies</v-card-title>
      <v-card-text>
        <CompetencyRatingTable
          v-model="competencyScores"
          :competency-groups="competencyGroups"
          :competency-labels="competencyLabels"
          :readonly="readonly"
        />
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
            <PortalRichText v-if="row.rich" :value="row.value" empty-text="—" />
            <div v-else class="text-body-2">{{ row.value }}</div>
          </div>
        </v-sheet>

        <PortalRichText
          v-if="readonly"
          :value="getReviewField(trainingReviewField)"
          label="1. Comments on progress made against the employee's Personal Development Plan (PDP)"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(trainingReviewField)"
          label="1. Comments on progress made against the employee's Personal Development Plan (PDP)"
          :min-rows="5"
          @update:model-value="setReviewField(trainingReviewField, $event)"
        />
        <v-select
          v-model="recommendedSkillIds"
          :items="skillItems"
          item-title="title"
          item-value="value"
          :disabled="readonly"
          label="2. Additional training recommended - skill area(s)"
          variant="outlined"
          multiple
          chips
          closable-chips
        />
        <PortalRichText
          v-if="readonly"
          :value="getReviewField(trainingContributionsField)"
          label="How will training contribute to development?"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(trainingContributionsField)"
          label="How will training contribute to development?"
          :min-rows="3"
          @update:model-value="setReviewField(trainingContributionsField, $event)"
        />
        <PortalRichText
          v-if="readonly"
          :value="getReviewField(recommendedTrainingsField)"
          label="Recommended course(s) from the AUC L&D Catalogue"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(recommendedTrainingsField)"
          label="Recommended course(s) from the AUC L&D Catalogue"
          :min-rows="3"
          @update:model-value="setReviewField(recommendedTrainingsField, $event)"
        />
        <PortalRichText
          v-if="readonly"
          :value="getReviewField(recommendedTrainingsDetailsField)"
          label="Other recommendable course(s)"
        />
        <PortalRichTextEditor
          v-else
          :model-value="getReviewField(recommendedTrainingsDetailsField)"
          label="Other recommendable course(s)"
          :min-rows="3"
          @update:model-value="setReviewField(recommendedTrainingsDetailsField, $event)"
        />
      </v-card-text>
    </v-card>
  </div>
</template>
