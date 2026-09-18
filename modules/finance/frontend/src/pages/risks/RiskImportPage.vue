<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type UnmatchedBu = { business_unit: string; count: number }
type Division = {
  division_id: number
  division_short_name: string
  division_name: string
  directorate_id: number | null
}

const locale = useLocaleStore()
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const okMessage = ref<string | null>(null)
const importEnabled = ref(true)
const batchId = ref<number | null>(null)
const mappingBatchId = ref<number | null>(null)
const preview = ref<{ matched: number; unmatched: number; skipped: number; unmatched_bus: UnmatchedBu[] } | null>(null)
const commitResult = ref<{ imported: number; staged: number } | null>(null)
const pending = ref<UnmatchedBu[]>([])
const unmappedRisks = ref<UnmatchedBu[]>([])
const divisions = ref<Division[]>([])
const mappings = reactive<Record<string, number | null>>({})
const riskMappings = reactive<Record<string, number | null>>({})
const fileInput = ref<HTMLInputElement | null>(null)

const divisionItems = computed(() => [
  { label: locale.t('rr.select_division', 'Select division…'), value: null as number | null },
  ...divisions.value.map((d) => ({
    label: d.division_short_name
      ? `${d.division_name} (${d.division_short_name})`
      : d.division_name,
    value: d.division_id,
  })),
])

const hasPending = computed(() => pending.value.length > 0)
const hasUnmappedRisks = computed(() => unmappedRisks.value.length > 0)
const pendingCount = computed(() => pending.value.reduce((n, r) => n + r.count, 0))
const unmappedRiskCount = computed(() => unmappedRisks.value.reduce((n, r) => n + r.count, 0))

function directorateFor(divisionId: number | null | undefined): number | null {
  if (divisionId == null || divisionId < 1) return null
  return divisions.value.find((d) => d.division_id === divisionId)?.directorate_id ?? null
}

async function loadStatus() {
  loading.value = true
  error.value = null
  try {
    const [{ data: status }, { data: org }] = await Promise.all([
      api.get<{
        import_enabled: boolean
        pending_unmatched: UnmatchedBu[]
        unmapped_risks?: UnmatchedBu[]
        mapping_batch_id?: number | null
        latest_batch: { id: number; status: string } | null
      }>('/api/v1/import/status'),
      api.get<{ data: { divisions: Division[] } }>('/api/v1/org'),
    ])
    importEnabled.value = status.import_enabled
    pending.value = status.pending_unmatched || []
    unmappedRisks.value = status.unmapped_risks || []
    mappingBatchId.value = status.mapping_batch_id ?? null
    if (status.latest_batch && status.latest_batch.status === 'matched_committed') {
      batchId.value = status.latest_batch.id
    }
    if (mappingBatchId.value) {
      batchId.value = mappingBatchId.value
    }
    divisions.value = org.data.divisions || []
    for (const row of pending.value) {
      if (!(row.business_unit in mappings)) mappings[row.business_unit] = null
    }
    for (const row of unmappedRisks.value) {
      if (!(row.business_unit in riskMappings)) riskMappings[row.business_unit] = null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.import_status_error', 'Could not load import status'))
  } finally {
    loading.value = false
  }
}

async function onFileChange(ev: Event) {
  const input = ev.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  busy.value = true
  error.value = null
  okMessage.value = null
  preview.value = null
  commitResult.value = null
  try {
    const form = new FormData()
    form.append('file', file)
    const { data } = await api.post<{
      data: { batch_id: number; matched: number; unmatched: number; skipped: number; unmatched_bus: UnmatchedBu[] }
    }>('/api/v1/import/preview', form, { headers: { 'Content-Type': 'multipart/form-data' } })
    batchId.value = data.data.batch_id
    preview.value = data.data
    for (const row of data.data.unmatched_bus) {
      mappings[row.business_unit] = null
    }
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.preview_failed', 'Preview failed'))
  } finally {
    busy.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

async function commitMatched() {
  if (batchId.value == null) return
  busy.value = true
  error.value = null
  okMessage.value = null
  try {
    const { data } = await api.post<{ data: { imported: number; staged: number } }>(
      `/api/v1/import/${batchId.value}/commit-matched`,
    )
    commitResult.value = data.data
    okMessage.value = locale.t(
      'rr.imported_matched',
      'Imported {imported} matched · staged {staged} unmatched for manual division mapping',
      { imported: data.data.imported, staged: data.data.staged },
    )
    preview.value = null
    await loadStatus()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.commit_failed', 'Commit failed'))
  } finally {
    busy.value = false
  }
}

async function applyMappings() {
  const targetBatch = mappingBatchId.value ?? batchId.value
  if (targetBatch == null) {
    error.value = locale.t('rr.no_mapping_batch', 'No unmatched import batch is ready for mapping.')
    return
  }
  const payload = Object.entries(mappings)
    .filter(([, div]) => div != null && div > 0)
    .map(([business_unit, division_id]) => ({
      business_unit,
      division_id: division_id as number,
      directorate_id: directorateFor(division_id),
    }))
  if (payload.length === 0) {
    error.value = locale.t('rr.select_mapping', 'Select at least one division mapping.')
    return
  }
  busy.value = true
  error.value = null
  okMessage.value = null
  try {
    const { data } = await api.post<{ data: { imported: number; remaining: number } }>(
      `/api/v1/import/${targetBatch}/apply-mappings`,
      { mappings: payload },
    )
    okMessage.value = locale.t(
      'rr.mapped_imported',
      'Imported {imported} mapped rows · {remaining} unmatched remaining',
      { imported: data.data.imported, remaining: data.data.remaining },
    )
    commitResult.value = null
    await loadStatus()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.apply_mappings_failed', 'Apply mappings failed'))
  } finally {
    busy.value = false
  }
}

async function applyRiskRemaps() {
  const payload = Object.entries(riskMappings)
    .filter(([, div]) => div != null && div > 0)
    .map(([business_unit, division_id]) => ({
      business_unit,
      division_id: division_id as number,
      directorate_id: directorateFor(division_id),
    }))
  if (payload.length === 0) {
    error.value = locale.t('rr.select_mapping', 'Select at least one division mapping.')
    return
  }
  busy.value = true
  error.value = null
  okMessage.value = null
  try {
    const { data } = await api.post<{ data: { updated: number; remaining: number } }>(
      '/api/v1/import/remap-unmapped',
      { mappings: payload },
    )
    okMessage.value = locale.t(
      'rr.remapped_risks',
      'Updated {updated} risks · {remaining} still unmatched',
      { updated: data.data.updated, remaining: data.data.remaining },
    )
    await loadStatus()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.remap_failed', 'Could not remap unmatched risks'))
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  void loadStatus()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.import_title', 'Excel import') }}</h1>
        <p class="rr-page__sub">
          {{
            locale.t(
              'rr.import_sub',
              'Upload a workbook, import matched rows, then manually map unmatched business units to divisions.',
            )
          }}
        </p>
      </div>
    </header>

    <RrSkeleton v-if="loading" variant="cards" :rows="2" />
    <p v-else-if="!importEnabled" class="rr-warn">
      {{ locale.t('rr.import_disabled', 'Import is disabled in settings.') }}
    </p>
    <template v-else>
      <p v-if="error" class="rr-error">{{ error }}</p>
      <p v-if="okMessage" class="rr-ok">{{ okMessage }}</p>

      <section v-if="hasPending" class="rr-card rr-map-card">
        <div class="rr-map-card__head">
          <h2>{{ locale.t('rr.manage_unmatched', 'Manage unmatched business units') }}</h2>
          <span class="rr-map-card__badge">
            {{ locale.t('rr.pending_rows', '{count} staged rows', { count: pendingCount }) }}
          </span>
        </div>
        <p class="rr-muted">
          {{
            locale.t(
              'rr.manage_unmatched_help',
              'These Excel business units did not auto-match a division. Choose the correct division, then import the staged rows.',
            )
          }}
        </p>
        <div class="rr-table-wrap">
          <table class="rr-table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.business_unit', 'Business unit') }}</th>
                <th class="rr-table__num">{{ locale.t('rr.col_count', 'Rows') }}</th>
                <th>{{ locale.t('rr.map_to_division', 'Map to division') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in pending" :key="`pending-${u.business_unit}`">
                <td class="rr-bu-name">{{ u.business_unit }}</td>
                <td class="rr-table__num">{{ u.count }}</td>
                <td class="rr-map-select">
                  <USelect
                    v-model="mappings[u.business_unit]"
                    :items="divisionItems"
                    :clearable="false"
                    hide-details
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="rr-map-actions">
          <button type="button" class="rr-btn rr-btn--primary" :disabled="busy" @click="applyMappings">
            {{
              busy
                ? locale.t('rr.importing', 'Importing…')
                : locale.t('rr.commit_mapped', 'Import mapped rows')
            }}
          </button>
        </div>
      </section>

      <section v-if="hasUnmappedRisks" class="rr-card rr-map-card">
        <div class="rr-map-card__head">
          <h2>{{ locale.t('rr.manage_unmapped_risks', 'Unmatched risks already in the register') }}</h2>
          <span class="rr-map-card__badge rr-map-card__badge--warn">
            {{ locale.t('rr.unmapped_risk_rows', '{count} risks', { count: unmappedRiskCount }) }}
          </span>
        </div>
        <p class="rr-muted">
          {{
            locale.t(
              'rr.manage_unmapped_risks_help',
              'These risks were imported without a division. Map each business unit to a division to fix them.',
            )
          }}
        </p>
        <div class="rr-table-wrap">
          <table class="rr-table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.business_unit', 'Business unit') }}</th>
                <th class="rr-table__num">{{ locale.t('rr.col_count', 'Risks') }}</th>
                <th>{{ locale.t('rr.map_to_division', 'Map to division') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in unmappedRisks" :key="`risk-${u.business_unit}`">
                <td class="rr-bu-name">{{ u.business_unit }}</td>
                <td class="rr-table__num">{{ u.count }}</td>
                <td class="rr-map-select">
                  <USelect
                    v-model="riskMappings[u.business_unit]"
                    :items="divisionItems"
                    :clearable="false"
                    hide-details
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="rr-map-actions">
          <button type="button" class="rr-btn rr-btn--primary" :disabled="busy" @click="applyRiskRemaps">
            {{
              busy
                ? locale.t('rr.saving', 'Saving…')
                : locale.t('rr.apply_division_maps', 'Apply division mappings')
            }}
          </button>
        </div>
      </section>

      <section class="rr-card">
        <h2>{{ locale.t('rr.step_upload', 'Upload workbook') }}</h2>
        <p class="rr-muted">
          {{
            locale.t(
              'rr.upload_help',
              'Matched rows import immediately. Unmatched business units are staged here for manual division mapping.',
            )
          }}
        </p>
        <input ref="fileInput" type="file" accept=".xlsx,.xls" :disabled="busy" @change="onFileChange" />
      </section>

      <section v-if="preview" class="rr-card">
        <h2>{{ locale.t('rr.step_preview', 'Preview') }}</h2>
        <p>
          {{ locale.t('rr.matched', 'Matched') }}: <strong>{{ preview.matched }}</strong>
          · {{ locale.t('rr.unmatched', 'Unmatched') }}: <strong>{{ preview.unmatched }}</strong>
          · {{ locale.t('rr.skipped_empty', 'Skipped empty') }}: {{ preview.skipped }}
        </p>
        <div v-if="preview.unmatched_bus.length" class="rr-preview-unmatched">
          <h3>{{ locale.t('rr.will_need_mapping', 'Will need manual division mapping') }}</h3>
          <ul>
            <li v-for="u in preview.unmatched_bus" :key="u.business_unit">
              <strong>{{ u.business_unit }}</strong>
              <span class="rr-muted">({{ u.count }})</span>
            </li>
          </ul>
        </div>
        <button type="button" class="rr-btn rr-btn--primary" :disabled="busy" @click="commitMatched">
          {{
            busy
              ? locale.t('rr.importing', 'Importing…')
              : locale.t('rr.commit_matched', 'Import matched rows')
          }}
        </button>
      </section>

      <section v-if="commitResult" class="rr-card">
        <p>
          {{
            locale.t('rr.imported_matched', 'Imported {imported} matched · staged {staged} unmatched', {
              imported: commitResult.imported,
              staged: commitResult.staged,
            })
          }}
        </p>
      </section>

      <section v-if="!hasPending && !hasUnmappedRisks && !preview" class="rr-card rr-empty">
        <p class="rr-muted">
          {{
            locale.t(
              'rr.no_unmatched_waiting',
              'No unmatched business units waiting for division mapping. Upload a workbook to start a new import.',
            )
          }}
        </p>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-map-card__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.65rem;
  margin-bottom: 0.35rem;
}
.rr-map-card__head h2 {
  margin: 0;
}
.rr-map-card__badge {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #dbeafe;
  color: #1e3a8a;
  font-size: 0.75rem;
  font-weight: 700;
}
.rr-map-card__badge--warn {
  background: #ffedd5;
  color: #9a3412;
}
.rr-table-wrap {
  overflow: auto;
  margin-top: 0.75rem;
}
.rr-bu-name {
  font-weight: 600;
  color: #0f172a;
}
.rr-map-select {
  min-width: 16rem;
}
.rr-map-actions {
  margin-top: 0.85rem;
}
.rr-preview-unmatched {
  margin: 0.75rem 0;
  padding: 0.75rem 0.9rem;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  border-radius: 8px;
}
.rr-preview-unmatched h3 {
  margin: 0 0 0.45rem;
  font-size: 0.88rem;
}
.rr-preview-unmatched ul {
  margin: 0;
  padding-left: 1.1rem;
}
.rr-ok {
  color: #166534;
  background: #dcfce7;
  padding: 0.75rem 1rem;
  border-radius: 6px;
}
.rr-warn {
  color: #9a6700;
  background: #fff8e6;
  padding: 0.75rem 1rem;
  border-radius: 6px;
}
.rr-empty {
  text-align: center;
}
.rr-table__num {
  text-align: center;
  font-variant-numeric: tabular-nums;
}
</style>
