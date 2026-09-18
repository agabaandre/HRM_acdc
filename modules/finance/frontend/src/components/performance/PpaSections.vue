<script setup lang="ts">
import { computed } from 'vue'
import PortalRichText from '@/components/atoms/PortalRichText.vue'
import PortalRichTextEditor from '@/components/atoms/PortalRichTextEditor.vue'
import PerformanceObjectivesTable from '@/components/performance/PerformanceObjectivesTable.vue'
import {
  normalizePerformanceSkillIds,
  performanceSkillItems,
  type PerformanceFormState,
  type PerformanceSkillCatalogItem,
} from '@/lib/performanceApi'

const props = defineProps<{
  form: PerformanceFormState
  skills: PerformanceSkillCatalogItem[]
  periodEndYear: number
  readonly: boolean
}>()

const skillItems = computed(() => performanceSkillItems(props.skills))

const requiredSkillIds = computed({
  get: () => normalizePerformanceSkillIds(props.form.required_skills),
  set: (value) => {
    props.form.required_skills = value
  },
})

const totalWeight = computed(() =>
  [1, 2, 3, 4, 5].reduce((sum, index) => {
    const raw = props.form.objectives[index]?.weight
    const weight = typeof raw === 'number' ? raw : Number.parseFloat(String(raw || 0))
    return sum + (Number.isFinite(weight) ? weight : 0)
  }, 0),
)
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-card variant="outlined">
      <v-card-title class="text-h6">B. Performance Objectives</v-card-title>
      <v-card-subtitle>
        Individual objectives should be derived from the Departmental Work Plan. The first three rows are required.
      </v-card-subtitle>
      <v-card-text>
        <PerformanceObjectivesTable
          :form="form"
          variant="plan"
          :readonly="readonly"
          :period-end-year="periodEndYear"
        />

        <v-alert
          class="mt-3"
          density="compact"
          :type="Math.abs(totalWeight - 100) < 0.01 ? 'success' : 'warning'"
          variant="tonal"
        >
          Objective weight total: <strong>{{ totalWeight }}</strong>%.
          <span v-if="Math.abs(totalWeight - 100) >= 0.01">Submission requires exactly 100%.</span>
        </v-alert>
      </v-card-text>
    </v-card>

    <v-card variant="outlined">
      <v-card-title class="text-h6">C. Personal Development Plan</v-card-title>
      <v-card-text>
        <v-radio-group
          v-model="form.training_recommended"
          :disabled="readonly"
          inline
          label="Is training recommended for this staff member?"
        >
          <v-radio label="Yes" value="Yes" />
          <v-radio label="No" value="No" />
        </v-radio-group>

        <v-expand-transition>
          <div v-if="form.training_recommended === 'Yes'" class="d-flex flex-column ga-4">
            <v-select
              v-model="requiredSkillIds"
              :items="skillItems"
              item-title="title"
              item-value="value"
              :disabled="readonly"
              label="Skill area(s) recommended"
              variant="outlined"
              multiple
              chips
              closable-chips
            />
            <PortalRichText
              v-if="readonly"
              :value="form.training_contributions"
              label="How training will contribute to the staff member's development and the department's work"
            />
            <PortalRichTextEditor
              v-else
              v-model="form.training_contributions"
              label="How training will contribute to the staff member's development and the department's work"
              :min-rows="4"
            />
            <PortalRichText
              v-if="readonly"
              :value="form.recommended_trainings"
              label="Recommended course(s) from the AUC L&D Catalogue"
            />
            <PortalRichTextEditor
              v-else
              v-model="form.recommended_trainings"
              label="Recommended course(s) from the AUC L&D Catalogue"
              hint="Separate multiple courses with semicolons."
              :min-rows="4"
            />
            <PortalRichText
              v-if="readonly"
              :value="form.recommended_trainings_details"
              label="Other recommendable course(s)"
            />
            <PortalRichTextEditor
              v-else
              v-model="form.recommended_trainings_details"
              label="Other recommendable course(s)"
              :min-rows="4"
            />
          </div>
        </v-expand-transition>
      </v-card-text>
    </v-card>
  </div>
</template>
