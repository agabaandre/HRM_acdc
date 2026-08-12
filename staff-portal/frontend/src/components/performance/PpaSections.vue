<script setup lang="ts">
import { computed } from 'vue'
import type {
  PerformanceContract,
  PerformanceFormState,
  PerformanceObjective,
  PerformanceSkillCatalogItem,
} from '@/lib/performanceApi'

const props = defineProps<{
  form: PerformanceFormState
  contract: PerformanceContract
  skills: PerformanceSkillCatalogItem[]
  periodLabel: string
  periodEndYear: number
  readonly: boolean
}>()

const objectiveIndexes = [1, 2, 3, 4, 5]

const totalWeight = computed(() =>
  objectiveIndexes.reduce((sum, index) => {
    const raw = props.form.objectives[index]?.weight
    const weight = typeof raw === 'number' ? raw : Number.parseFloat(String(raw || 0))
    return sum + (Number.isFinite(weight) ? weight : 0)
  }, 0),
)

function objectiveAt(index: number): PerformanceObjective {
  return props.form.objectives[index]
}
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-card variant="outlined">
      <v-card-title class="text-h6">A. Staff Details</v-card-title>
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
              <th class="perf-label">Initiation Date</th>
              <td>{{ contract.initiation_date || '—' }}</td>
            </tr>
            <tr>
              <th class="perf-label">Division/Directorate</th>
              <td>{{ contract.division_name || '—' }}</td>
              <th class="perf-label">Performance Period</th>
              <td>{{ periodLabel }}</td>
            </tr>
            <tr>
              <th class="perf-label">First Supervisor</th>
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
      <v-card-title class="text-h6">B. Performance Objectives</v-card-title>
      <v-card-subtitle>
        Individual objectives should be derived from the Departmental Work Plan. The first three rows are required.
      </v-card-subtitle>
      <v-card-text>
        <div class="perf-objectives">
          <div
            v-for="index in objectiveIndexes"
            :key="index"
            class="perf-objective-row"
          >
            <div class="perf-objective-row__heading">
              <span class="text-subtitle-2">Objective {{ index }}</span>
              <v-chip v-if="index <= 3" size="x-small" color="warning" variant="tonal">Required</v-chip>
            </div>
            <v-row dense>
              <v-col cols="12" md="5">
                <v-textarea
                  v-model="objectiveAt(index).objective"
                  :readonly="readonly"
                  :label="`Objective ${index}`"
                  rows="4"
                  auto-grow
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="2">
                <v-text-field
                  v-model="objectiveAt(index).timeline"
                  :readonly="readonly"
                  label="Timeline"
                  type="date"
                  variant="outlined"
                  :placeholder="index <= 3 ? `${periodEndYear}-12-31` : ''"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-textarea
                  v-model="objectiveAt(index).indicator"
                  :readonly="readonly"
                  label="Deliverables and KPI's"
                  rows="4"
                  auto-grow
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="1">
                <v-text-field
                  v-model="objectiveAt(index).weight"
                  :readonly="readonly"
                  label="Weight"
                  type="number"
                  min="0"
                  max="100"
                  variant="outlined"
                />
              </v-col>
            </v-row>
          </div>
        </div>

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
              v-model="form.required_skills"
              :items="skills.map((skill) => ({ title: skill.skill, value: skill.id }))"
              :disabled="readonly"
              label="Skill area(s) recommended"
              variant="outlined"
              multiple
              chips
              closable-chips
            />
            <v-textarea
              v-model="form.training_contributions"
              :readonly="readonly"
              label="How training will contribute to the staff member's development and the department's work"
              rows="4"
              auto-grow
              variant="outlined"
            />
            <v-textarea
              v-model="form.recommended_trainings"
              :readonly="readonly"
              label="Recommended course(s) from the AUC L&D Catalogue"
              rows="4"
              auto-grow
              variant="outlined"
              hint="Separate multiple courses with semicolons."
              persistent-hint
            />
            <v-textarea
              v-model="form.recommended_trainings_details"
              :readonly="readonly"
              label="Other recommendable course(s)"
              rows="4"
              auto-grow
              variant="outlined"
            />
          </div>
        </v-expand-transition>
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

.perf-objectives {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.perf-objective-row {
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 12px;
  padding: 1rem;
}

.perf-objective-row__heading {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-bottom: 0.75rem;
}
</style>
