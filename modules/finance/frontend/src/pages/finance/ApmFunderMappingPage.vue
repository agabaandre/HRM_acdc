<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import FinSkeleton from '@/components/finance/FinSkeleton.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type Funder = { id: number; name: string; is_active: boolean }
type FunderAlias = {
  label: string
  funder_id: number | null
  funder_name: string | null
  match_source: string
}

const loading = ref(true)
const fetching = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const apmReady = ref(false)
const funders = ref<Funder[]>([])
const funderAliases = ref<FunderAlias[]>([])

const funderMapped = computed(() => funderAliases.value.filter((r) => r.funder_id).length)

async function applyPayload(data: any) {
  apmReady.value = !!data.apm_ready
  funders.value = data.funders || []
  funderAliases.value = (data.funder_aliases || []).map((r: FunderAlias) => ({
    ...r,
    funder_id: r.funder_id ?? null,
  }))
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/apm-mapping')
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load APM funder mappings.')
  } finally {
    loading.value = false
  }
}

async function fetchApm() {
  fetching.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/apm-mapping/fetch')
    message.value = data.message || 'Fetched from APM.'
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Fetch from APM failed.')
  } finally {
    fetching.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/apm-mapping', {
      funder_aliases: funderAliases.value.map((r) => {
        const f = funders.value.find((x) => x.id === Number(r.funder_id))
        return {
          ...r,
          funder_name: f?.name || r.funder_name,
          match_source: r.funder_id ? (r.match_source === 'auto' ? 'auto' : 'manual') : 'unmatched',
        }
      }),
    })
    message.value = data.message || 'Funder mappings saved.'
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Save failed.')
  } finally {
    saving.value = false
  }
}

function onFunderPick(row: FunderAlias) {
  const f = funders.value.find((x) => x.id === Number(row.funder_id))
  row.funder_name = f?.name || null
  row.match_source = row.funder_id ? 'manual' : 'unmatched'
}

onMounted(load)
</script>

<template>
  <div class="map">
    <header class="map__header">
      <div class="map__title-block">
        <div class="map__title-row">
          <h1>APM funder mapping</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="map__sub">
          Map Funding Portfolio workbook funder labels to APM funders
          (<code>/staff/apm/funders</code>. Used by Extramural and Indirect Cost entry.
        </p>
      </div>
      <div class="map__actions">
        <RouterLink class="map__link" :to="{ name: 'settings-apm-divisions' }">Division mapping</RouterLink>
        <button type="button" class="btn" :disabled="fetching || loading" @click="fetchApm">
          {{ fetching ? 'Fetching…' : 'Fetch from APM' }}
        </button>
        <button type="button" class="btn btn-primary" :disabled="saving || loading" @click="save">
          {{ saving ? 'Saving…' : 'Save mappings' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="map__error">{{ error }}</p>
    <p v-if="message" class="map__ok">{{ message }}</p>
    <FinSkeleton v-if="loading" variant="table" :rows="6" />

    <template v-else>
      <p class="map__status">
        Status:
        <strong>{{ apmReady ? 'APM database reachable' : 'APM database not configured' }}</strong>
        · {{ funders.length }} funder(s)
        · {{ funderMapped }}/{{ funderAliases.length }} mapped
      </p>

      <section class="map__card">
        <div class="map__table-wrap">
          <table class="map__table">
            <thead>
              <tr>
                <th>Portfolio label</th>
                <th>APM funder</th>
                <th>Match</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in funderAliases" :key="idx">
                <td>{{ row.label }}</td>
                <td>
                  <select v-model="row.funder_id" @change="onFunderPick(row)">
                    <option :value="null">— Unmatched —</option>
                    <option v-for="f in funders" :key="f.id" :value="f.id">
                      {{ f.name }}{{ f.is_active ? '' : ' (inactive)' }}
                    </option>
                  </select>
                </td>
                <td><span class="map__chip">{{ row.match_source }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.map { width: 100%; max-width: none; margin: 0; padding-bottom: 2rem; }
.map__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.map__title-block { min-width: 0; flex: 1; }
.map__title-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem;
}
.map__header h1 { margin: 0; font-size: 1.45rem; }
.map__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 52rem; line-height: 1.45; }
.map__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.map__link { color: #0b3b5c; font-weight: 600; text-decoration: none; }
.map__status { color: #64748b; margin: 0 0 0.85rem; font-size: 0.92rem; }
.map__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.12);
  border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 0.9rem;
}
.map__muted { color: #64748b; margin: 0 0 0.75rem; }
.map__error { color: #b42318; }
.map__ok { color: #027a48; }
.map__table-wrap { overflow-x: auto; }
.map__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.map__table th, .map__table td {
  text-align: left; padding: 0.5rem 0.45rem; border-bottom: 1px solid rgba(0,0,0,0.06); vertical-align: middle;
}
.map__table select {
  width: 100%; min-width: 220px; border: 1px solid rgba(0,0,0,0.16); border-radius: 8px; padding: 0.4rem 0.5rem;
}
.map__chip {
  display: inline-block; background: #e8f1f7; color: #0b3b5c; border-radius: 999px;
  padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
}
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: 8px;
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
@media (max-width: 720px) {
  .map__header { flex-direction: column; }
}
</style>
