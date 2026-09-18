<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type RiskRow = {
  id: number
  name: string
  consequence?: string | null
  existing_assurance: string | null
  planned_management_actions: string | null
  primary_owners: string[]
  inherent_score: number | null
  residual_score: number | null
  inherent_fill_color?: string | null
  inherent_text_color?: string | null
  residual_fill_color?: string | null
  residual_text_color?: string | null
}

type DivisionBlock = {
  division_id: number | null
  division_name: string
  risks: RiskRow[]
}

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()
const loading = ref(true)
const error = ref<string | null>(null)
const theme = ref<{ id: number; ref: string; name: string } | null>(null)
const divisions = ref<DivisionBlock[]>([])
const filterDivisions = ref<Array<{ division_id: number | null; division_name: string }>>([])
const filterDirectorates = ref<Array<{ directorate_id: number; directorate_name: string }>>([])

const year = ref<number | null>(route.query.year ? Number(route.query.year) : 2026)
const quarter = ref<number | null>(route.query.quarter ? Number(route.query.quarter) : 2)
const divisionId = ref<number | null>(route.query.division_id ? Number(route.query.division_id) : null)
const directorateId = ref<number | null>(
  route.query.directorate_id ? Number(route.query.directorate_id) : null,
)

const themeId = computed(() => Number(route.params.id))

const yearOptions = computed(() => {
  const y = new Date().getFullYear()
  return [y + 1, y, y - 1, y - 2, y - 3].map((n) => ({ label: String(n), value: n }))
})
const quarterOptions = computed(() => [
  { label: locale.t('rr.quarter_q1', 'Q1'), value: 1 },
  { label: locale.t('rr.quarter_q2', 'Q2'), value: 2 },
  { label: locale.t('rr.quarter_q3', 'Q3'), value: 3 },
  { label: locale.t('rr.quarter_q4', 'Q4'), value: 4 },
])
const divisionOptions = computed(() => [
  { label: locale.t('rr.all_divisions', 'All divisions'), value: null as number | null },
  ...filterDivisions.value
    .filter((d) => d.division_id != null)
    .map((d) => ({ label: d.division_name, value: d.division_id as number })),
])
const directorateOptions = computed(() => [
  { label: locale.t('rr.all_directorates', 'All directorates'), value: null as number | null },
  ...filterDirectorates.value.map((d) => ({ label: d.directorate_name, value: d.directorate_id })),
])
const periodBadge = computed(() => {
  if (year.value != null && quarter.value != null) return `Q${quarter.value} ${year.value}`
  return locale.t('rr.all_periods', 'All periods')
})

function scoreStyle(fill?: string | null, text?: string | null): Record<string, string> | undefined {
  if (!fill) return undefined
  return {
    backgroundColor: fill,
    color: text || '#0F172A',
    fontWeight: '700',
    textAlign: 'center',
  }
}

function ownersText(owners: string[]): string {
  return owners.length ? owners.join('; ') : locale.t('rr.none_dash', '—')
}

async function loadReport() {
  if (!themeId.value || Number.isNaN(themeId.value)) {
    error.value = locale.t('rr.invalid_theme', 'Invalid theme.')
    loading.value = false
    return
  }
  if ((year.value == null) !== (quarter.value == null)) return
  loading.value = true
  error.value = null
  try {
    const params: Record<string, number> = {}
    if (year.value != null) params.year = year.value
    if (quarter.value != null) params.quarter = quarter.value
    if (divisionId.value != null) params.division_id = divisionId.value
    if (directorateId.value != null) params.directorate_id = directorateId.value
    const { data } = await api.get<{
      theme: { id: number; ref: string; name: string }
      divisions: DivisionBlock[]
      filter_divisions?: Array<{ division_id: number | null; division_name: string }>
      filter_directorates?: Array<{ directorate_id: number; directorate_name: string }>
    }>(`/api/v1/reports/enterprise-themes/${themeId.value}`, { params })
    theme.value = data.theme
    divisions.value = data.divisions || []
    if (data.filter_divisions?.length) filterDivisions.value = data.filter_divisions
    if (data.filter_directorates?.length) filterDirectorates.value = data.filter_directorates
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_theme_drill_error', 'Could not load theme detail'))
  } finally {
    loading.value = false
  }
}

function openRisk(id: number) {
  void router.push({ name: 'risk-show', params: { id } })
}

function backToThemes() {
  const query: Record<string, string> = {}
  if (year.value != null) query.year = String(year.value)
  if (quarter.value != null) query.quarter = String(quarter.value)
  if (divisionId.value != null) query.division_id = String(divisionId.value)
  if (directorateId.value != null) query.directorate_id = String(directorateId.value)
  void router.push({ name: 'report-enterprise-themes', query })
}

watch([year, quarter, themeId], () => {
  if ((year.value == null) !== (quarter.value == null)) return
  void loadReport()
})

onMounted(() => {
  void loadReport()
})
</script>

<template>
  <div class="rr-page rr-theme-drill">
    <header class="rr-page__header">
      <div>
        <p class="rr-page__crumb">
          <button type="button" class="rr-linkish" @click="backToThemes">
            {{ locale.t('rr.back_theme_report', '← Theme report') }}
          </button>
        </p>
        <h1>
          <span v-if="theme" class="rr-theme-ref">{{ theme.ref }}</span>
          {{ theme?.name || locale.t('rr.theme_drill_title', 'Theme detail') }}
        </h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.theme_drill_sub', 'Risks by division within this enterprise theme') }}
          ·
          <span class="rr-period-chip">{{ periodBadge }}</span>
        </p>
      </div>
    </header>

    <section class="rr-card rr-filters">
      <div class="rr-filters__grid">
        <UFormField :label="locale.t('rr.filter_year', 'Year')">
          <USelect v-model="year" :items="yearOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_quarter', 'Quarter')">
          <USelect v-model="quarter" :items="quarterOptions" hide-details />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_directorate', 'Directorate')">
          <USelectMenu
            v-model="directorateId"
            :items="directorateOptions"
            searchable
            hide-details
            @update:model-value="loadReport"
          />
        </UFormField>
        <UFormField :label="locale.t('rr.filter_division', 'Division')">
          <USelectMenu
            v-model="divisionId"
            :items="divisionOptions"
            searchable
            hide-details
            @update:model-value="loadReport"
          />
        </UFormField>
      </div>
    </section>

    <RrSkeleton v-if="loading" variant="table" :rows="6" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <template v-else>
      <section v-for="block in divisions" :key="String(block.division_id)" class="rr-card rr-div-block">
        <h2 class="rr-div-block__head">{{ block.division_name }}</h2>
        <div class="rr-report-table-wrap">
          <v-table density="compact" hover class="rr-v-table rr-theme-table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.col_risk', 'Risk') }}</th>
                <th>{{ locale.t('rr.col_assurance', 'Existing assurance / controls') }}</th>
                <th>{{ locale.t('rr.col_planned', 'Planned management actions') }}</th>
                <th>{{ locale.t('rr.col_owners', 'Primary owner(s)') }}</th>
                <th>{{ locale.t('rr.col_inherent', 'Inherent') }}</th>
                <th>{{ locale.t('rr.col_residual', 'Residual') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="risk in block.risks" :key="risk.id" class="rr-theme-row" @click="openRisk(risk.id)">
                <td class="rr-theme-table__text">
                  <button type="button" class="rr-theme-link" @click.stop="openRisk(risk.id)">
                    {{ risk.name }}
                  </button>
                </td>
                <td class="rr-theme-table__text">{{ risk.existing_assurance || locale.t('rr.none_dash', '—') }}</td>
                <td class="rr-theme-table__text">
                  {{ risk.planned_management_actions || locale.t('rr.none_dash', '—') }}
                </td>
                <td class="rr-theme-table__text">{{ ownersText(risk.primary_owners) }}</td>
                <td
                  class="rr-theme-table__score"
                  :style="scoreStyle(risk.inherent_fill_color, risk.inherent_text_color)"
                >
                  {{ risk.inherent_score ?? locale.t('rr.none_dash', '—') }}
                </td>
                <td
                  class="rr-theme-table__score"
                  :style="scoreStyle(risk.residual_fill_color, risk.residual_text_color)"
                >
                  {{ risk.residual_score ?? locale.t('rr.none_dash', '—') }}
                </td>
              </tr>
              <tr v-if="block.risks.length === 0">
                <td colspan="6" class="rr-muted">{{ locale.t('rr.no_risks_division', 'No risks in this division.') }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </section>
      <p v-if="divisions.length === 0" class="rr-muted">
        {{ locale.t('rr.no_theme_risks', 'No risks found for this theme with the current filters.') }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.rr-page__crumb { margin: 0 0 0.35rem; }
.rr-linkish {
  border: 0;
  background: transparent;
  color: #1e3a8a;
  font-weight: 600;
  padding: 0;
  cursor: pointer;
}
.rr-theme-ref {
  display: inline-block;
  margin-right: 0.4rem;
  color: #64748b;
  font-weight: 700;
}
.rr-period-chip {
  display: inline-block;
  padding: 0.1rem 0.45rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: #1e3a8a;
  background: #dbeafe;
  border-radius: 999px;
}
.rr-filters { margin-bottom: 1rem; }
.rr-filters__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}
.rr-div-block { margin-bottom: 1rem; padding: 0; overflow: hidden; border-radius: 0; }
.rr-div-block__head {
  margin: 0;
  padding: 0.7rem 0.9rem;
  background: #1e3a5f;
  color: #fff;
  font-size: 1rem;
  border-radius: 0;
}
.rr-report-table-wrap { overflow: auto; border-radius: 0; }
.rr-theme-row { cursor: pointer; }
.rr-theme-table__text { white-space: pre-wrap; max-width: 20rem; }
.rr-theme-table__score {
  text-align: center;
  font-weight: 700;
  min-width: 3.5rem;
}
.rr-theme-link {
  border: 0;
  background: transparent;
  color: #1e3a8a;
  font-weight: 700;
  text-align: left;
  padding: 0;
  cursor: pointer;
}
</style>
