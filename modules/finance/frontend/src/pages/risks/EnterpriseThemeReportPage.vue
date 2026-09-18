<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type ThemeRow = {
  enterprise_theme_id: number
  ref: string
  theme_name: string
  risk_count: number
  division_count: number
  strategic_risk_statement: string | null
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

const router = useRouter()
const locale = useLocaleStore()
const loading = ref(true)
const error = ref<string | null>(null)
const rows = ref<ThemeRow[]>([])

const year = ref<number | null>(2026)
const quarter = ref<number | null>(2)
const divisionId = ref<number | null>(null)
const directorateId = ref<number | null>(null)
const divisions = ref<Array<{ division_id: number | null; division_name: string }>>([])
const directorates = ref<Array<{ directorate_id: number; directorate_name: string }>>([])

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
  ...divisions.value
    .filter((d) => d.division_id != null)
    .map((d) => ({ label: d.division_name, value: d.division_id as number })),
])
const directorateOptions = computed(() => [
  { label: locale.t('rr.all_directorates', 'All directorates'), value: null as number | null },
  ...directorates.value.map((d) => ({ label: d.directorate_name, value: d.directorate_id })),
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

const OWNER_PREVIEW = 5
const ownersModalOpen = ref(false)
const ownersModalTitle = ref('')
const ownersModalList = ref<string[]>([])

function previewOwners(owners: string[]): string[] {
  return owners.slice(0, OWNER_PREVIEW)
}

function hiddenOwnerCount(owners: string[]): number {
  return Math.max(0, owners.length - OWNER_PREVIEW)
}

function openOwnersModal(row: ThemeRow, event?: Event) {
  event?.stopPropagation()
  ownersModalTitle.value = `${row.ref} · ${row.theme_name}`
  ownersModalList.value = row.primary_owners || []
  ownersModalOpen.value = true
}

function closeOwnersModal() {
  ownersModalOpen.value = false
}

async function loadReport() {
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
      data: ThemeRow[]
      divisions?: Array<{ division_id: number | null; division_name: string }>
      directorates?: Array<{ directorate_id: number; directorate_name: string }>
    }>('/api/v1/reports/enterprise-themes', { params })
    rows.value = data.data || []
    if (data.divisions?.length) divisions.value = data.divisions
    if (data.directorates?.length) directorates.value = data.directorates
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_theme_report_error', 'Could not load theme report'))
  } finally {
    loading.value = false
  }
}

function openTheme(id: number) {
  const query: Record<string, string> = {}
  if (year.value != null) query.year = String(year.value)
  if (quarter.value != null) query.quarter = String(quarter.value)
  if (divisionId.value != null) query.division_id = String(divisionId.value)
  if (directorateId.value != null) query.directorate_id = String(directorateId.value)
  void router.push({ name: 'report-enterprise-theme', params: { id }, query })
}

watch([year, quarter], () => {
  if ((year.value == null) !== (quarter.value == null)) return
  void loadReport()
})

onMounted(() => {
  void loadReport()
})
</script>

<template>
  <div class="rr-page rr-theme-report">
    <header class="rr-page__header">
      <div>
        <p class="rr-page__crumb">
          <button type="button" class="rr-linkish" @click="router.push({ name: 'reports' })">
            {{ locale.t('rr.back_reports', '← Reports') }}
          </button>
        </p>
        <h1>{{ locale.t('rr.theme_report_title', 'Enterprise Risk Register by Theme') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.theme_report_sub', 'High-level risks as of selected period') }}
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
      <p v-if="year != null && quarter == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_quarter', 'Select a quarter to filter by review period.') }}
      </p>
      <p v-else-if="quarter != null && year == null" class="rr-muted rr-filters__hint">
        {{ locale.t('rr.hint_select_year', 'Select a year to filter by review period.') }}
      </p>
    </section>

    <RrSkeleton v-if="loading" variant="table" :rows="8" />
    <p v-else-if="error" class="rr-error">{{ error }}</p>
    <div v-else class="rr-card rr-report-table-wrap">
      <v-table density="compact" hover class="rr-v-table rr-theme-table">
        <thead>
          <tr>
            <th>{{ locale.t('rr.col_ref', 'Ref') }}</th>
            <th>{{ locale.t('rr.col_theme', 'Enterprise risk theme') }}</th>
            <th>{{ locale.t('rr.col_statement', 'Strategic risk statement') }}</th>
            <th>{{ locale.t('rr.col_assurance', 'Existing assurance / controls') }}</th>
            <th>{{ locale.t('rr.col_planned', 'Planned management actions') }}</th>
            <th>{{ locale.t('rr.col_owners', 'Primary owner(s)') }}</th>
            <th>{{ locale.t('rr.col_inherent', 'Inherent') }}</th>
            <th>{{ locale.t('rr.col_residual', 'Residual') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.enterprise_theme_id"
            class="rr-theme-row"
            @click="openTheme(row.enterprise_theme_id)"
          >
            <td class="rr-theme-table__ref">{{ row.ref }}</td>
            <td>
              <button type="button" class="rr-theme-link" @click.stop="openTheme(row.enterprise_theme_id)">
                {{ row.theme_name }}
              </button>
              <div class="rr-muted rr-theme-meta">
                {{
                  locale.t('rr.theme_meta', '{risks} risks · {divisions} divisions', {
                    risks: row.risk_count,
                    divisions: row.division_count,
                  })
                }}
              </div>
            </td>
            <td class="rr-theme-table__text">{{ row.strategic_risk_statement || locale.t('rr.none_dash', '—') }}</td>
            <td class="rr-theme-table__text">{{ row.existing_assurance || locale.t('rr.none_dash', '—') }}</td>
            <td class="rr-theme-table__text">{{ row.planned_management_actions || locale.t('rr.none_dash', '—') }}</td>
            <td class="rr-theme-table__text rr-theme-table__owners" @click.stop>
              <template v-if="row.primary_owners.length">
                <ul class="rr-owners-preview">
                  <li v-for="(owner, idx) in previewOwners(row.primary_owners)" :key="`${row.enterprise_theme_id}-${idx}`">
                    {{ owner }}
                  </li>
                </ul>
                <button
                  v-if="hiddenOwnerCount(row.primary_owners) > 0"
                  type="button"
                  class="rr-owners-more"
                  @click="openOwnersModal(row, $event)"
                >
                  {{
                    locale.t('rr.owners_more', '+{count} more', {
                      count: hiddenOwnerCount(row.primary_owners),
                    })
                  }}
                </button>
              </template>
              <template v-else>{{ locale.t('rr.none_dash', '—') }}</template>
            </td>
            <td class="rr-theme-table__score" :style="scoreStyle(row.inherent_fill_color, row.inherent_text_color)">
              {{ row.inherent_score ?? locale.t('rr.none_dash', '—') }}
            </td>
            <td class="rr-theme-table__score" :style="scoreStyle(row.residual_fill_color, row.residual_text_color)">
              {{ row.residual_score ?? locale.t('rr.none_dash', '—') }}
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="8" class="rr-muted">{{ locale.t('rr.no_theme_rows', 'No themes match the current filters.') }}</td>
          </tr>
        </tbody>
      </v-table>
    </div>

    <v-dialog v-model="ownersModalOpen" max-width="520" scrollable>
      <v-card class="rr-owners-modal">
        <v-card-title class="rr-owners-modal__title">
          {{ locale.t('rr.col_owners', 'Primary owner(s)') }}
        </v-card-title>
        <v-card-subtitle v-if="ownersModalTitle" class="rr-owners-modal__sub">
          {{ ownersModalTitle }}
        </v-card-subtitle>
        <v-card-text>
          <ol v-if="ownersModalList.length" class="rr-owners-modal__list">
            <li v-for="(owner, idx) in ownersModalList" :key="`${idx}-${owner}`">{{ owner }}</li>
          </ol>
          <p v-else class="rr-muted">{{ locale.t('rr.none_dash', '—') }}</p>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="closeOwnersModal">
            {{ locale.t('rr.close', 'Close') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
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
.rr-filters__hint { margin: 0.65rem 0 0; }
.rr-report-table-wrap { padding: 0; overflow: auto; border-radius: 0; }
.rr-theme-row { cursor: pointer; }
.rr-theme-table__ref { font-weight: 700; white-space: nowrap; }
.rr-theme-table__text { white-space: pre-wrap; max-width: 18rem; }
.rr-theme-table__owners { max-width: 16rem; white-space: normal; }
.rr-owners-preview {
  margin: 0;
  padding-left: 1.1rem;
}
.rr-owners-preview li {
  margin: 0.1rem 0;
}
.rr-owners-more {
  display: inline-block;
  margin-top: 0.35rem;
  border: 0;
  background: transparent;
  color: #1e3a8a;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0;
  cursor: pointer;
  text-decoration: underline;
}
.rr-owners-modal__title {
  font-size: 1.05rem;
  font-weight: 700;
}
.rr-owners-modal__sub {
  opacity: 1;
  white-space: normal;
}
.rr-owners-modal__list {
  margin: 0;
  padding-left: 1.25rem;
  line-height: 1.55;
}
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
.rr-theme-meta { margin-top: 0.25rem; font-size: 0.75rem; }
</style>
