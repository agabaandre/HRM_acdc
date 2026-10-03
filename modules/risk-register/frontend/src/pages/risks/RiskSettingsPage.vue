<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { api } from '@/lib/api'
import { useRiskLookups } from '@/composables/useRiskLookups'
import { RISK_LOOKUP_DEFS } from '@/lib/riskLookupDefs'
import { useLocaleStore } from '@/stores/locale'
import RrSkeleton from '@/components/risks/RrSkeleton.vue'

type BandDraft = {
  id?: number
  rating: string
  band_key: string
  min_score: number
  max_score: number
  fill_color: string
  text_color: string
  sort_order: number
}

type LookupRow = Record<string, unknown> & { id?: number }

const locale = useLocaleStore()
const { loadLookups } = useRiskLookups()
const importEnabled = ref(true)
const canManage = ref(false)
const canDelete = ref(false)
const error = ref<string | null>(null)
const loading = ref(true)
const saving = ref(false)
const publishing = ref(false)
const activating = ref(false)
const message = ref('')
const activeVersion = ref<string>('—')
const versions = ref<Array<{ version: number; label: string | null; created_at: string }>>([])
const bands = ref<BandDraft[]>([])
const bandUsage = ref<Record<string, boolean>>({})
const publishLabel = ref('')
const lookupCounts = ref<Record<string, number>>({})

const usedBandKeys = computed(() => new Set(Object.entries(bandUsage.value).filter(([, used]) => used).map(([k]) => k)))

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

function lookupTitle(key: string, fallback: string): string {
  const map = LOOKUP_I18N[key]
  return map ? locale.t(map.title, fallback) : fallback
}

function lookupDesc(key: string, fallback: string): string {
  const map = LOOKUP_I18N[key]
  return map ? locale.t(map.desc, fallback) : fallback
}

function defaultBands(): BandDraft[] {
  return [
    { rating: 'Low', band_key: 'low', min_score: 1, max_score: 4, fill_color: '#00B050', text_color: '#FFFFFF', sort_order: 1 },
    { rating: 'Medium', band_key: 'medium', min_score: 5, max_score: 9, fill_color: '#FFFF00', text_color: '#1A1A1A', sort_order: 2 },
    { rating: 'High', band_key: 'high', min_score: 10, max_score: 15, fill_color: '#FFC000', text_color: '#1A1A1A', sort_order: 3 },
    { rating: 'Critical', band_key: 'critical', min_score: 16, max_score: 25, fill_color: '#C00000', text_color: '#FFFFFF', sort_order: 4 },
  ]
}

function mapBands(rows: BandDraft[]): BandDraft[] {
  return rows.map((b, i) => ({
    id: b.id,
    rating: b.rating,
    band_key: b.band_key || b.rating.toLowerCase(),
    min_score: Number(b.min_score),
    max_score: Number(b.max_score),
    fill_color: b.fill_color || '#94A3B8',
    text_color: b.text_color || '#0F172A',
    sort_order: b.sort_order ?? i + 1,
  }))
}

async function load() {
  error.value = null
  loading.value = true
  try {
    const { data: settings } = await api.get<{
      data: { import_enabled?: string; active_rating_key_version?: string }
      rating_bands?: BandDraft[]
      rating_band_usage?: Record<string, boolean>
      rating_key_versions?: Array<{ version: number; label: string | null; created_at: string }>
      lookups?: Record<string, LookupRow[]>
      can_manage: boolean
      can_delete_lookups?: boolean
    }>('/api/v1/settings')
    importEnabled.value = settings.data.import_enabled !== '0'
    canManage.value = settings.can_manage
    canDelete.value = Boolean(settings.can_delete_lookups)
    activeVersion.value = settings.data.active_rating_key_version || locale.t('rr.none_dash', '—')
    versions.value = settings.rating_key_versions || []
    bandUsage.value = settings.rating_band_usage || {}
    bands.value = mapBands(settings.rating_bands?.length ? settings.rating_bands : defaultBands())
    const counts: Record<string, number> = {}
    for (const def of RISK_LOOKUP_DEFS) {
      counts[def.key] = (settings.lookups?.[def.table] || []).length
    }
    lookupCounts.value = counts
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.load_settings_error', 'Could not load settings'))
  } finally {
    loading.value = false
  }
}

async function saveImportToggle() {
  if (!canManage.value) return
  saving.value = true
  message.value = ''
  try {
    await api.put('/api/v1/settings', { import_enabled: importEnabled.value })
    message.value = locale.t('rr.saved', 'Saved.')
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.save_failed_short', 'Save failed'))
  } finally {
    saving.value = false
  }
}

function addBand() {
  const next = bands.value.length + 1
  bands.value.push({
    rating: locale.t('rr.band_default_name', 'Band {n}', { n: next }),
    band_key: `band_${next}`,
    min_score: 1,
    max_score: 1,
    fill_color: '#94A3B8',
    text_color: '#0F172A',
    sort_order: next,
  })
}

function removeBand(idx: number) {
  const b = bands.value[idx]
  if (!b) return
  if (!canDelete.value) {
    error.value = locale.t('rr.delete_band_perm', 'Removing bands requires the delete_risk_lookups permission (Admin).')
    return
  }
  if (usedBandKeys.value.has(b.band_key)) {
    error.value = locale.t('rr.band_in_use', 'Cannot remove "{rating}" — it is used on existing risks.', { rating: b.rating })
    return
  }
  bands.value.splice(idx, 1)
}

function bandInUse(b: BandDraft): boolean {
  return usedBandKeys.value.has(b.band_key)
}

async function publishBands() {
  if (!canManage.value) return
  publishing.value = true
  message.value = ''
  error.value = null
  try {
    const { data } = await api.post<{
      data: { version: number; bands: BandDraft[]; active_rating_key_version: string }
    }>('/api/v1/settings/rating-bands/publish', {
      label: publishLabel.value || undefined,
      bands: bands.value,
    })
    activeVersion.value = String(data.data.version)
    bands.value = mapBands(data.data.bands)
    publishLabel.value = ''
    message.value = locale.t(
      'rr.published_msg',
      'Published rating key version {version}. Existing risks keep their stored keys.',
      { version: data.data.version },
    )
    await loadLookups(true)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.publish_failed', 'Publish failed'))
  } finally {
    publishing.value = false
  }
}

async function activateVersion(version: number) {
  if (!canManage.value || activating.value) return
  if (String(version) === activeVersion.value) return
  activating.value = true
  message.value = ''
  error.value = null
  try {
    const { data } = await api.post<{
      data: { active_rating_key_version: string; bands: BandDraft[] }
    }>('/api/v1/settings/rating-bands/activate', { version })
    activeVersion.value = data.data.active_rating_key_version
    bands.value = mapBands(data.data.bands)
    message.value = locale.t(
      'rr.activated_msg',
      'Version {version} is now the default for new risk profiling.',
      { version },
    )
    await loadLookups(true)
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('rr.activate_failed', 'Could not set default version'))
  } finally {
    activating.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="rr-page">
    <header class="rr-page__header">
      <div>
        <h1>{{ locale.t('rr.settings_title', 'Risk settings') }}</h1>
        <p class="rr-page__sub">
          {{ locale.t('rr.settings_sub', 'Configure import, rating keys, and lookup lists. Open a list to add or edit values.') }}
        </p>
      </div>
    </header>
    <p v-if="error" class="rr-error">{{ error }}</p>
    <p v-if="message" class="rr-ok">{{ message }}</p>

    <RrSkeleton v-if="loading" variant="cards" :rows="3" />
    <template v-else>
      <section class="rr-card">
        <h2>Staff API</h2>
        <p class="rr-muted">Share API credentials (DB overrides env) and connection test.</p>
        <RouterLink class="rr-btn" :to="{ name: 'risk-settings-staff-api' }">Open Staff API settings</RouterLink>
      </section>

      <section class="rr-card">
        <h2>Email settings</h2>
        <p class="rr-muted">Outbound mail dispatch (portal hub / local transport) and test send.</p>
        <RouterLink class="rr-btn" :to="{ name: 'risk-settings-email' }">Open email settings</RouterLink>
      </section>

      <section class="rr-card">
        <h2>{{ locale.t('rr.excel_import', 'Excel import') }}</h2>
        <label class="rr-toggle">
          <input v-model="importEnabled" type="checkbox" :disabled="!canManage || saving" @change="saveImportToggle" />
          {{ locale.t('rr.import_enabled', 'Import module enabled') }}
        </label>
        <p class="rr-muted">{{ locale.t('rr.import_enabled_help', 'When off, the Import page is hidden and uploads are blocked.') }}</p>
      </section>

      <section class="rr-card">
        <h2>{{ locale.t('rr.lookup_lists', 'Lookup lists') }}</h2>
        <p class="rr-muted">
          {{ locale.t('rr.lookup_lists_help', 'Each list opens on its own page. Edit opens in a modal.') }}
          <span v-if="!canDelete"> {{ locale.t('rr.delete_perm_note', 'Delete requires Staff Portal permission delete_risk_lookups (Admin).') }}</span>
        </p>
        <div class="rr-lookup-grid">
          <RouterLink
            v-for="def in RISK_LOOKUP_DEFS"
            :key="def.key"
            class="rr-lookup-card"
            :to="{ name: 'risk-settings-lookup', params: { key: def.key } }"
          >
            <div class="rr-lookup-card__title">{{ lookupTitle(def.key, def.title) }}</div>
            <div class="rr-lookup-card__count">
              {{ locale.t('rr.values_count', '{n} values', { n: lookupCounts[def.key] ?? 0 }) }}
            </div>
            <p class="rr-lookup-card__desc">{{ lookupDesc(def.key, def.description) }}</p>
          </RouterLink>
        </div>
      </section>

      <section class="rr-card">
        <div class="rr-bands__head">
          <div>
            <h2>{{ locale.t('rr.rating_bands', 'Rating key bands') }}</h2>
            <p class="rr-muted">
              {{ locale.t('rr.rating_bands_help', 'Active version v{version} is used for new risk profiling. Bands already stored on risks cannot be removed.', { version: activeVersion }) }}
            </p>
          </div>
          <button v-if="canManage" type="button" class="rr-btn" :disabled="publishing" @click="addBand">
            {{ locale.t('rr.add_band', 'Add band') }}
          </button>
        </div>

        <div class="rr-bands__legend">
          <span
            v-for="b in bands"
            :key="b.band_key + b.rating"
            class="rr-bands__chip"
            :style="{ background: b.fill_color, color: b.text_color }"
          >
            {{ b.rating }} {{ b.min_score }}–{{ b.max_score }}
            <em v-if="bandInUse(b)" class="rr-bands__used">{{ locale.t('rr.in_use', 'in use') }}</em>
          </span>
        </div>

        <div class="rr-table-wrap">
          <table class="rr-table rr-bands__table">
            <thead>
              <tr>
                <th>{{ locale.t('rr.key', 'Key') }}</th>
                <th>{{ locale.t('rr.label', 'Label') }}</th>
                <th>{{ locale.t('rr.min', 'Min') }}</th>
                <th>{{ locale.t('rr.max', 'Max') }}</th>
                <th>{{ locale.t('rr.fill', 'Fill') }}</th>
                <th>{{ locale.t('rr.text', 'Text') }}</th>
                <th>{{ locale.t('rr.preview', 'Preview') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(b, idx) in bands" :key="idx">
                <td><input v-model="b.band_key" class="rr-input" :disabled="!canManage || bandInUse(b)" /></td>
                <td><input v-model="b.rating" class="rr-input" :disabled="!canManage" /></td>
                <td><input v-model.number="b.min_score" type="number" min="1" max="25" class="rr-input rr-input--num" :disabled="!canManage" /></td>
                <td><input v-model.number="b.max_score" type="number" min="1" max="25" class="rr-input rr-input--num" :disabled="!canManage" /></td>
                <td><input v-model="b.fill_color" type="color" class="rr-input--color" :disabled="!canManage" /></td>
                <td><input v-model="b.text_color" type="color" class="rr-input--color" :disabled="!canManage" /></td>
                <td>
                  <span class="rr-bands__preview" :style="{ background: b.fill_color, color: b.text_color }">
                    {{ b.max_score }} {{ b.rating }}
                  </span>
                </td>
                <td>
                  <button
                    v-if="canManage && canDelete && bands.length > 1"
                    type="button"
                    class="rr-btn rr-btn--danger"
                    :disabled="bandInUse(b)"
                    :title="bandInUse(b) ? locale.t('rr.in_use_title', 'In use on existing risks') : locale.t('rr.remove_band_title', 'Remove band')"
                    @click="removeBand(idx)"
                  >
                    {{ bandInUse(b) ? locale.t('rr.in_use', 'in use') : locale.t('rr.remove', 'Remove') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="canManage" class="rr-bands__publish">
          <input
            v-model="publishLabel"
            class="rr-input"
            :placeholder="locale.t('rr.version_label_ph', 'Version label (optional)')"
            :disabled="publishing"
          />
          <button type="button" class="rr-btn rr-btn--primary" :disabled="publishing" @click="publishBands">
            {{ publishing ? locale.t('rr.publishing', 'Publishing…') : locale.t('rr.publish_version', 'Save & publish new version') }}
          </button>
        </div>

        <details v-if="versions.length" class="rr-bands__history" open>
          <summary>{{ locale.t('rr.published_versions', 'Published versions ({n})', { n: versions.length }) }}</summary>
          <ul class="rr-versions">
            <li v-for="v in versions" :key="v.version" class="rr-versions__item">
              <div>
                <strong>v{{ v.version }}</strong>
                <span v-if="String(v.version) === activeVersion" class="rr-versions__badge">
                  {{ locale.t('rr.default_badge', 'default') }}
                </span>
                — {{ v.label || locale.t('rr.untitled', 'Untitled') }}
                <span class="rr-muted">{{ v.created_at }}</span>
              </div>
              <button
                v-if="canManage && String(v.version) !== activeVersion"
                type="button"
                class="rr-btn"
                :disabled="activating"
                @click="activateVersion(v.version)"
              >
                {{ locale.t('rr.set_as_default', 'Set as default') }}
              </button>
            </li>
          </ul>
        </details>
      </section>
    </template>
  </div>
</template>

<style scoped>
.rr-toggle { display: flex; gap: 0.5rem; align-items: center; font-weight: 600; }
.rr-lookup-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: 0.75rem;
  margin-top: 0.85rem;
}
.rr-lookup-card {
  display: block;
  padding: 0.9rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  text-decoration: none;
  color: inherit;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.rr-lookup-card:hover {
  border-color: #93c5fd;
  box-shadow: 0 1px 4px rgba(15, 23, 42, 0.08);
}
.rr-lookup-card__title { font-weight: 700; color: #0f172a; }
.rr-lookup-card__count {
  margin-top: 0.2rem;
  font-size: 0.8rem;
  color: #64748b;
}
.rr-lookup-card__desc {
  margin: 0.45rem 0 0;
  font-size: 0.85rem;
  color: #475569;
  line-height: 1.35;
}
.rr-bands__head {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
  margin-bottom: 0.75rem;
}
.rr-bands__legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.85rem;
}
.rr-bands__chip,
.rr-bands__preview {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  font-size: 0.8rem;
  font-weight: 600;
  border-radius: 2px;
}
.rr-bands__used {
  font-style: normal;
  font-weight: 500;
  opacity: 0.85;
  margin-left: 0.35rem;
  font-size: 0.7rem;
  text-transform: uppercase;
}
.rr-bands__table th { white-space: nowrap; }
.rr-input {
  width: 100%;
  min-width: 4.5rem;
  padding: 0.35rem 0.45rem;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  background: #fff;
}
.rr-input--num { max-width: 4.5rem; }
.rr-input--color {
  width: 2.5rem;
  height: 2rem;
  padding: 0;
  border: 1px solid #cbd5e1;
  background: transparent;
}
.rr-bands__publish {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-top: 1rem;
  align-items: center;
}
.rr-bands__publish .rr-input { max-width: 18rem; }
.rr-bands__history { margin-top: 1rem; }
.rr-versions { list-style: none; padding: 0; margin: 0.5rem 0 0; }
.rr-versions__item {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  padding: 0.45rem 0;
  border-bottom: 1px solid #e2e8f0;
}
.rr-versions__badge {
  display: inline-block;
  margin-left: 0.35rem;
  padding: 0.05rem 0.4rem;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  background: #dbeafe;
  color: #1d4ed8;
  border-radius: 2px;
}
.rr-btn--danger {
  background: #fff;
  color: #b91c1c;
  border-color: #fecaca;
}
.rr-btn--danger:disabled {
  color: #94a3b8;
  border-color: #e2e8f0;
}
</style>
