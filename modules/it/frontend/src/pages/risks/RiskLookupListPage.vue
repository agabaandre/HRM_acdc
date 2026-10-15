<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useRiskLookups } from '@/composables/useRiskLookups'
import { lookupDefByKey, type LookupDef } from '@/lib/riskLookupDefs'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type LookupRow = Record<string, unknown> & { id?: number }

const route = useRoute()
const router = useRouter()
const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()

const def = computed<LookupDef | undefined>(() => lookupDefByKey(String(route.params.key || '')))
const rows = ref<LookupRow[]>([])
const canManage = ref(false)
const canDelete = ref(false)
const loading = ref(true)
const busy = ref(false)
const error = ref<string | null>(null)
const message = ref('')
const dialogOpen = ref(false)
const draft = ref<LookupRow | null>(null)

const LOOKUP_I18N: Record<string, { title: string; titleFb: string; desc: string; descFb: string }> = {
  likelihoods: {
    title: 'rr.lookup_likelihoods',
    titleFb: 'Likelihoods',
    desc: 'rr.lookup_likelihoods_desc',
    descFb: 'Likelihood scale used for inherent and residual scoring.',
  },
  impacts: {
    title: 'rr.lookup_impacts',
    titleFb: 'Impacts',
    desc: 'rr.lookup_impacts_desc',
    descFb: 'Impact scale used for inherent and residual scoring.',
  },
  'risk-types': {
    title: 'rr.lookup_risk_types',
    titleFb: 'Risk types',
    desc: 'rr.lookup_risk_types_desc',
    descFb: 'Categories used when classifying each risk.',
  },
  'enterprise-themes': {
    title: 'rr.lookup_themes',
    titleFb: 'Enterprise themes',
    desc: 'rr.lookup_themes_desc',
    descFb: 'Enterprise risk themes aligned to organisational priorities.',
  },
  statuses: {
    title: 'rr.lookup_statuses',
    titleFb: 'Statuses',
    desc: 'rr.lookup_statuses_desc',
    descFb: 'Workflow statuses for risk action tracking.',
  },
  'mitigation-effectiveness': {
    title: 'rr.lookup_effectiveness',
    titleFb: 'Mitigation effectiveness',
    desc: 'rr.lookup_effectiveness_desc',
    descFb: 'How strongly mitigation reduces residual likelihood.',
  },
}

const COL_I18N: Record<string, { key: string; fb: string }> = {
  label: { key: 'rr.label', fb: 'Label' },
  score: { key: 'rr.col_score', fb: 'Score' },
  name: { key: 'rr.col_name', fb: 'Name' },
  likelihood_reduction: { key: 'rr.col_l_reduction', fb: 'L reduction' },
  is_assessed: { key: 'rr.col_assessed', fb: 'Assessed' },
  sort_order: { key: 'rr.col_order', fb: 'Order' },
  code: { key: 'rr.col_code', fb: 'Code' },
}

function lookupTitle(d: LookupDef): string {
  const map = LOOKUP_I18N[d.key]
  return map ? locale.t(map.title, d.title) : d.title
}

function lookupDesc(d: LookupDef): string {
  const map = LOOKUP_I18N[d.key]
  return map ? locale.t(map.desc, d.description) : d.description
}

function columnLabel(field: string, fallback: string): string {
  const map = COL_I18N[field]
  return map ? locale.t(map.key, fallback) : fallback
}

const displayTitle = computed(() => (def.value ? lookupTitle(def.value) : locale.t('rr.lookup_fallback_title', 'Lookup')))
const displayDesc = computed(() => (def.value ? lookupDesc(def.value) : ''))

const dialogTitle = computed(() => {
  if (!def.value) return locale.t('rr.edit', 'Edit')
  const base = displayTitle.value
  const singular = base.endsWith('s') ? base.slice(0, -1) : base
  return draft.value?.id
    ? `${locale.t('rr.edit', 'Edit')} ${singular}`
    : `${locale.t('rr.add', 'Add')} ${singular}`
})

async function load() {
  if (!def.value) {
    error.value = locale.t('rr.lookup_unknown', 'Unknown lookup list.')
    loading.value = false
    return
  }
  error.value = null
  loading.value = true
  try {
    const { data: settings } = await api.get<{
      lookups?: Record<string, LookupRow[]>
      can_manage: boolean
      can_delete_lookups?: boolean
    }>('/api/v1/settings')
    canManage.value = settings.can_manage
    canDelete.value = Boolean(settings.can_delete_lookups)
    rows.value = (settings.lookups?.[def.value.table] || []).map((r) => ({ ...r }))
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.lookup_load_error', 'Could not load lookup list'))
  } finally {
    loading.value = false
  }
}

function cellValue(row: LookupRow, field: string): string {
  const v = row[field]
  if (v === null || v === undefined) return ''
  return String(v)
}

function openCreate() {
  if (!def.value || !canManage.value) return
  draft.value = { ...def.value.blank() }
  dialogOpen.value = true
}

function openEdit(row: LookupRow) {
  if (!canManage.value) return
  draft.value = { ...row }
  dialogOpen.value = true
}

function closeDialog() {
  dialogOpen.value = false
  draft.value = null
}

async function saveDraft() {
  if (!def.value || !draft.value || !canManage.value) return
  busy.value = true
  error.value = null
  message.value = ''
  try {
    const fields: Record<string, unknown> = {}
    for (const col of def.value.columns) {
      let v = draft.value[col.field]
      if (col.type === 'number') v = Number(v)
      if (col.type === 'checkbox') v = Boolean(v)
      fields[col.field] = v
    }
    await api.post(`/api/v1/settings/lookups/${def.value.table}`, {
      id: draft.value.id || undefined,
      fields,
    })
    message.value = draft.value.id
      ? locale.t('rr.lookup_updated', 'Updated.')
      : locale.t('rr.lookup_created', 'Created.')
    closeDialog()
    await loadLookups(true)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.lookup_save_failed', 'Save failed'))
  } finally {
    busy.value = false
  }
}

async function deleteRow(row: LookupRow) {
  if (!def.value || !canDelete.value || !row.id) return
  const label = String(row.name || row.label || row.id)
  if (!window.confirm(locale.t('rr.delete_confirm', 'Delete “{label}”? This cannot be undone.', { label }))) return
  busy.value = true
  error.value = null
  message.value = ''
  try {
    await api.delete(`/api/v1/settings/lookups/${def.value.table}/${row.id}`)
    message.value = locale.t('rr.lookup_deleted', 'Deleted.')
    await loadLookups(true)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.lookup_delete_blocked', 'Delete blocked or failed'))
  } finally {
    busy.value = false
  }
}

onMounted(() => {
  if (!def.value) {
    void router.replace({ name: 'risk-settings' })
    return
  }
  void load()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <p class="rr-breadcrumb">
          <RouterLink :to="{ name: 'risk-settings' }">{{ locale.t('rr.back_settings', 'Risk settings') }}</RouterLink>
          <span>/</span>
          <span>{{ displayTitle }}</span>
        </p>
        <h1>{{ displayTitle }}</h1>
        <p class="rr-page__sub">{{ displayDesc }}</p>
      </div>
      <button v-if="canManage" type="button" class="rr-btn rr-btn--primary" @click="openCreate">
        {{ locale.t('rr.add', 'Add') }}
      </button>
    </header>

    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>
    <p v-if="canManage && !canDelete" class="rr-muted">
      {{ locale.t('rr.delete_disabled_note', 'Delete is disabled unless your Staff Portal account has the delete_risk_lookups permission (Admin).') }}
    </p>

    <RrSkeleton v-if="loading" variant="table" :rows="6" />
    <template v-else-if="def">
      <section class="rr-card rr-table-wrap">
        <table class="rr-table">
          <thead>
            <tr>
              <th v-for="col in def.columns" :key="col.field">{{ columnLabel(col.field, col.label) }}</th>
              <th v-if="canManage" class="rr-table__actions">{{ locale.t('rr.actions', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td v-for="col in def.columns" :key="col.field">
                <template v-if="col.type === 'checkbox'">
                  {{ row[col.field] ? locale.t('rr.yes', 'Yes') : locale.t('rr.no', 'No') }}
                </template>
                <template v-else>{{ cellValue(row, col.field) }}</template>
              </td>
              <td v-if="canManage" class="rr-lookup__row-actions">
                <button type="button" class="rr-btn" :disabled="busy" @click="openEdit(row)">
                  {{ locale.t('rr.edit', 'Edit') }}
                </button>
                <button
                  v-if="canDelete"
                  type="button"
                  class="rr-btn rr-btn--danger"
                  :disabled="busy"
                  @click="deleteRow(row)"
                >
                  {{ locale.t('rr.delete', 'Delete') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="rows.length === 0" class="rr-muted">{{ locale.t('rr.no_values_yet', 'No values yet.') }}</p>
      </section>
    </template>

    <v-dialog v-model="dialogOpen" max-width="520" persistent>
      <v-card v-if="def && draft">
        <v-card-title>{{ dialogTitle }}</v-card-title>
        <v-card-text>
          <div class="rr-lookup__fields">
            <label v-for="col in def.columns" :key="col.field" class="rr-lookup__field">
              <span>{{ columnLabel(col.field, col.label) }}</span>
              <input
                v-if="col.type !== 'checkbox'"
                v-model="draft[col.field]"
                class="rr-input"
                :type="col.type === 'number' ? 'number' : 'text'"
                :disabled="busy"
              />
              <input v-else v-model="draft[col.field]" type="checkbox" :disabled="busy" />
            </label>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <button type="button" class="rr-btn" :disabled="busy" @click="closeDialog">
            {{ locale.t('rr.cancel', 'Cancel') }}
          </button>
          <button type="button" class="rr-btn rr-btn--primary" :disabled="busy" @click="saveDraft">
            {{ busy ? locale.t('rr.saving', 'Saving…') : locale.t('rr.save', 'Save') }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.rr-breadcrumb {
  display: flex;
  gap: 0.4rem;
  align-items: center;
  margin: 0 0 0.35rem;
  font-size: 0.85rem;
  color: #64748b;
}
.rr-breadcrumb a { color: #1d4ed8; text-decoration: none; }
.rr-page__header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}
.rr-table__actions { width: 1%; white-space: nowrap; }
.rr-lookup__row-actions {
  white-space: nowrap;
  display: flex;
  gap: 0.35rem;
}
.rr-lookup__fields {
  display: grid;
  gap: 0.75rem;
}
.rr-lookup__field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  font-size: 0.85rem;
  font-weight: 600;
}
.rr-input {
  width: 100%;
  padding: 0.45rem 0.55rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #fff;
}
.rr-btn--danger {
  background: #fff;
  color: #b91c1c;
  border-color: #fecaca;
}
.v-card-actions {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem 1rem 1rem;
}
</style>
