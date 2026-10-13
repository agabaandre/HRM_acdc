<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import DivisionContextSwitcher from '@/components/organisms/DivisionContextSwitcher.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

type Region = { id: number; name: string }
type Country = { id: number; name: string; region_id: number | null; iso2: string | null }
type Location = {
  id: number
  name: string
  country: string | null
  country_iso2: string | null
  city: string | null
}
type RegionAlias = { label: string; region_id: number | null; match_source: string }
type TabKey = 'regions' | 'countries' | 'locations' | 'aliases'

const tab = ref<TabKey>('regions')
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const message = ref<string | null>(null)
const canEdit = ref(false)
const regions = ref<Region[]>([])
const countries = ref<Country[]>([])
const locations = ref<Location[]>([])
const regionAliases = ref<RegionAlias[]>([])

const newRegion = ref('')
const newCountry = ref({ name: '', region_id: null as number | null, iso2: '' })
const newLocation = ref({ name: '', country: '', city: '' })

const aliasMapped = computed(() => regionAliases.value.filter((r) => r.region_id).length)

async function applyPayload(data: any) {
  regions.value = data.regions || []
  countries.value = data.countries || []
  locations.value = data.locations || []
  regionAliases.value = (data.region_aliases || []).map((r: RegionAlias) => ({
    ...r,
    region_id: r.region_id ?? null,
  }))
  canEdit.value = !!data.can_edit
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get('/api/v1/settings/geography')
    await applyPayload(data.data)
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load geography lists.')
  } finally {
    loading.value = false
  }
}

async function saveAliases() {
  if (!canEdit.value) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.put('/api/v1/settings/geography/aliases', {
      region_aliases: regionAliases.value.map((r) => ({
        ...r,
        match_source: r.region_id ? (r.match_source === 'auto' ? 'auto' : 'manual') : 'unmatched',
      })),
    })
    message.value = data.message || 'Aliases saved.'
    regionAliases.value = (data.data?.region_aliases || regionAliases.value).map((r: RegionAlias) => ({
      ...r,
      region_id: r.region_id ?? null,
    }))
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save aliases.')
  } finally {
    saving.value = false
  }
}

async function addRegion() {
  if (!canEdit.value || !newRegion.value.trim()) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/geography/regions', { name: newRegion.value.trim() })
    message.value = data.message || 'Region added.'
    newRegion.value = ''
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not add region.')
  } finally {
    saving.value = false
  }
}

async function updateRegion(row: Region) {
  if (!canEdit.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await api.put(`/api/v1/settings/geography/regions/${row.id}`, { name: row.name })
    message.value = data.message || 'Region updated.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update region.')
  } finally {
    saving.value = false
  }
}

async function addCountry() {
  if (!canEdit.value || !newCountry.value.name.trim()) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/geography/countries', {
      name: newCountry.value.name.trim(),
      region_id: newCountry.value.region_id,
      iso2: newCountry.value.iso2 || null,
    })
    message.value = data.message || 'Country added.'
    newCountry.value = { name: '', region_id: null, iso2: '' }
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not add country.')
  } finally {
    saving.value = false
  }
}

async function updateCountry(row: Country) {
  if (!canEdit.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await api.put(`/api/v1/settings/geography/countries/${row.id}`, {
      name: row.name,
      region_id: row.region_id,
      iso2: row.iso2,
    })
    message.value = data.message || 'Country updated.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update country.')
  } finally {
    saving.value = false
  }
}

async function addLocation() {
  if (!canEdit.value || !newLocation.value.name.trim()) return
  saving.value = true
  error.value = null
  message.value = null
  try {
    const { data } = await api.post('/api/v1/settings/geography/locations', {
      name: newLocation.value.name.trim(),
      country: newLocation.value.country || null,
      city: newLocation.value.city || null,
    })
    message.value = data.message || 'Location added.'
    newLocation.value = { name: '', country: '', city: '' }
    await load()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not add location.')
  } finally {
    saving.value = false
  }
}

async function updateLocation(row: Location) {
  if (!canEdit.value) return
  saving.value = true
  error.value = null
  try {
    const { data } = await api.put(`/api/v1/settings/geography/locations/${row.id}`, {
      name: row.name,
      country: row.country,
      city: row.city,
      country_iso2: row.country_iso2,
    })
    message.value = data.message || 'Location updated.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update location.')
  } finally {
    saving.value = false
  }
}

function regionName(id: number | null): string {
  if (!id) return '—'
  return regions.value.find((r) => r.id === id)?.name || `#${id}`
}

onMounted(load)
</script>

<template>
  <div class="geo">
    <header class="geo__header">
      <div class="geo__title-block">
        <div class="geo__title-row">
          <h1>Geography</h1>
          <DivisionContextSwitcher />
        </div>
        <p class="geo__sub">
          Standard lists from Staff Portal — regions, countries (nationalities), and locations
          (duty stations). Portfolio Investments &amp; Bank maps workbook labels to these lists.
        </p>
      </div>
      <div class="geo__actions">
        <RouterLink class="geo__link" :to="{ name: 'portfolio-entry' }">Portfolio entry</RouterLink>
        <RouterLink class="geo__link" :to="{ name: 'settings-apm-divisions' }">APM divisions</RouterLink>
        <RouterLink class="geo__link" :to="{ name: 'settings-apm-funders' }">APM funders</RouterLink>
        <button
          v-if="tab === 'aliases' && canEdit"
          type="button"
          class="btn btn-primary"
          :disabled="saving || loading"
          @click="saveAliases"
        >
          {{ saving ? 'Saving…' : 'Save aliases' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="geo__error">{{ error }}</p>
    <p v-if="message" class="geo__ok">{{ message }}</p>
    <p v-if="loading" class="geo__muted">Loading…</p>

    <template v-else>
      <p class="geo__status">
        {{ regions.length }} region(s) · {{ countries.length }} countr{{ countries.length === 1 ? 'y' : 'ies' }} ·
        {{ locations.length }} location(s)
        <span v-if="!canEdit"> · view only</span>
      </p>

      <div class="geo__tabs" role="tablist" aria-label="Geography lists">
        <button
          type="button"
          role="tab"
          class="geo__tab"
          :class="{ 'geo__tab--active': tab === 'regions' }"
          :aria-selected="tab === 'regions'"
          @click="tab = 'regions'"
        >
          <i class="fa-solid fa-compass" aria-hidden="true" />
          Regions
          <span class="geo__tab-count">{{ regions.length }}</span>
        </button>
        <button
          type="button"
          role="tab"
          class="geo__tab"
          :class="{ 'geo__tab--active': tab === 'countries' }"
          :aria-selected="tab === 'countries'"
          @click="tab = 'countries'"
        >
          <i class="fa-solid fa-globe" aria-hidden="true" />
          Countries
          <span class="geo__tab-count">{{ countries.length }}</span>
        </button>
        <button
          type="button"
          role="tab"
          class="geo__tab"
          :class="{ 'geo__tab--active': tab === 'locations' }"
          :aria-selected="tab === 'locations'"
          @click="tab = 'locations'"
        >
          <i class="fa-solid fa-location-dot" aria-hidden="true" />
          Locations
          <span class="geo__tab-count">{{ locations.length }}</span>
        </button>
        <button
          type="button"
          role="tab"
          class="geo__tab"
          :class="{ 'geo__tab--active': tab === 'aliases' }"
          :aria-selected="tab === 'aliases'"
          @click="tab = 'aliases'"
        >
          <i class="fa-solid fa-link" aria-hidden="true" />
          Region aliases
          <span class="geo__tab-count">{{ aliasMapped }}/{{ regionAliases.length }}</span>
        </button>
      </div>

      <section v-show="tab === 'regions'" class="geo__card" role="tabpanel">
        <p class="geo__muted">Staff Portal <code>regions</code> (AU Eastern / Southern / …).</p>
        <div v-if="canEdit" class="geo__add">
          <input v-model="newRegion" type="text" placeholder="New region name" @keyup.enter="addRegion" />
          <button type="button" class="btn btn-primary" :disabled="saving || !newRegion.trim()" @click="addRegion">
            Add region
          </button>
        </div>
        <div class="geo__table-wrap">
          <table class="geo__table">
            <thead>
              <tr>
                <th style="width: 4rem">ID</th>
                <th>Name</th>
                <th v-if="canEdit" style="width: 7rem" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in regions" :key="row.id">
                <td><code>{{ row.id }}</code></td>
                <td>
                  <input v-if="canEdit" v-model="row.name" type="text" />
                  <span v-else>{{ row.name }}</span>
                </td>
                <td v-if="canEdit">
                  <button type="button" class="btn" :disabled="saving" @click="updateRegion(row)">Save</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-show="tab === 'countries'" class="geo__card" role="tabpanel">
        <p class="geo__muted">Staff Portal <code>nationalities</code> — used as country list for Investments.</p>
        <div v-if="canEdit" class="geo__add geo__add--grid">
          <input v-model="newCountry.name" type="text" placeholder="Country name" />
          <select v-model="newCountry.region_id">
            <option :value="null">— Region —</option>
            <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
          <input v-model="newCountry.iso2" type="text" placeholder="ISO2" maxlength="2" />
          <button type="button" class="btn btn-primary" :disabled="saving || !newCountry.name.trim()" @click="addCountry">
            Add country
          </button>
        </div>
        <div class="geo__table-wrap">
          <table class="geo__table">
            <thead>
              <tr>
                <th>Country</th>
                <th>Region</th>
                <th>ISO2</th>
                <th v-if="canEdit" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in countries" :key="row.id">
                <td>
                  <input v-if="canEdit" v-model="row.name" type="text" />
                  <span v-else>{{ row.name }}</span>
                </td>
                <td>
                  <select v-if="canEdit" v-model="row.region_id">
                    <option :value="null">—</option>
                    <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                  </select>
                  <span v-else>{{ regionName(row.region_id) }}</span>
                </td>
                <td>
                  <input v-if="canEdit" v-model="row.iso2" type="text" maxlength="2" class="geo__iso" />
                  <span v-else>{{ row.iso2 || '—' }}</span>
                </td>
                <td v-if="canEdit">
                  <button type="button" class="btn" :disabled="saving" @click="updateCountry(row)">Save</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-show="tab === 'locations'" class="geo__card" role="tabpanel">
        <p class="geo__muted">Staff Portal <code>duty_stations</code> — cities / duty stations.</p>
        <div v-if="canEdit" class="geo__add geo__add--grid">
          <input v-model="newLocation.name" type="text" placeholder="Duty station name" />
          <input v-model="newLocation.country" type="text" placeholder="Country" />
          <input v-model="newLocation.city" type="text" placeholder="City" />
          <button type="button" class="btn btn-primary" :disabled="saving || !newLocation.name.trim()" @click="addLocation">
            Add location
          </button>
        </div>
        <div class="geo__table-wrap">
          <table class="geo__table">
            <thead>
              <tr>
                <th>Location</th>
                <th>Country</th>
                <th>City</th>
                <th v-if="canEdit" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in locations" :key="row.id">
                <td>
                  <input v-if="canEdit" v-model="row.name" type="text" />
                  <span v-else>{{ row.name }}</span>
                </td>
                <td>
                  <input v-if="canEdit" v-model="row.country" type="text" />
                  <span v-else>{{ row.country || '—' }}</span>
                </td>
                <td>
                  <input v-if="canEdit" v-model="row.city" type="text" />
                  <span v-else>{{ row.city || '—' }}</span>
                </td>
                <td v-if="canEdit">
                  <button type="button" class="btn" :disabled="saving" @click="updateLocation(row)">Save</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section v-show="tab === 'aliases'" class="geo__card" role="tabpanel">
        <p class="geo__muted">
          Workbook region labels (East Africa, Southern Africa, …) → Staff Portal regions.
          Used when importing Investments &amp; Bank.
        </p>
        <div class="geo__table-wrap">
          <table class="geo__table">
            <thead>
              <tr>
                <th>Workbook label</th>
                <th>Staff Portal region</th>
                <th>Match</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in regionAliases" :key="idx">
                <td>{{ row.label }}</td>
                <td>
                  <select
                    v-model="row.region_id"
                    :disabled="!canEdit"
                    @change="row.match_source = row.region_id ? 'manual' : 'unmatched'"
                  >
                    <option :value="null">— Unmatched —</option>
                    <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                  </select>
                </td>
                <td><span class="geo__chip">{{ row.match_source }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>
  </div>
</template>

<style scoped>
.geo { width: 100%; max-width: none; margin: 0; padding-bottom: 2rem; }
.geo__header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: 1rem; }
.geo__title-block { min-width: 0; flex: 1; }
.geo__title-row {
  display: flex; flex-wrap: wrap; align-items: center; gap: 0.65rem 0.85rem;
}
.geo__header h1 { margin: 0; font-size: 1.45rem; }
.geo__sub { color: #64748b; margin: 0.4rem 0 0; max-width: 48rem; line-height: 1.45; }
.geo__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
.geo__link { color: #0b3b5c; font-weight: 600; text-decoration: none; }
.geo__status { color: #64748b; margin: 0 0 0.85rem; font-size: 0.92rem; }
.geo__tabs {
  display: flex; gap: 0.25rem; margin-bottom: 0; flex-wrap: wrap;
  border-bottom: 1px solid rgba(11,59,92,0.14);
}
.geo__tab {
  appearance: none; border: none; background: transparent; cursor: pointer;
  font: inherit; font-weight: 650; color: #64748b;
  padding: 0.65rem 0.9rem 0.7rem; border-bottom: 2px solid transparent;
  margin-bottom: -1px; display: inline-flex; align-items: center; gap: 0.4rem;
}
.geo__tab:hover { color: #0b3b5c; }
.geo__tab--active { color: #0b3b5c; border-bottom-color: #0b3b5c; }
.geo__tab-count {
  font-size: 0.72rem; font-weight: 700; background: #e8f1f7; color: #0b3b5c;
  border-radius: 999px; padding: 0.12rem 0.45rem;
}
.geo__tab--active .geo__tab-count { background: #0b3b5c; color: #fff; }
.geo__card {
  background: #fff; border: 1px solid rgba(11,59,92,0.12); border-top: none;
  border-radius: 0 0 14px 14px; padding: 1rem 1.1rem; margin-bottom: 0.9rem;
}
.geo__muted { color: #64748b; margin: 0 0 0.75rem; }
.geo__error { color: #b42318; }
.geo__ok { color: #027a48; }
.geo__add {
  display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.85rem; align-items: center;
}
.geo__add--grid { display: grid; grid-template-columns: 1.4fr 1fr 5rem auto; gap: 0.5rem; }
.geo__add input, .geo__add select,
.geo__table input, .geo__table select {
  width: 100%; border: 1px solid rgba(0,0,0,0.16); border-radius: 8px; padding: 0.4rem 0.5rem; font: inherit;
}
.geo__iso { max-width: 4rem; text-transform: uppercase; }
.geo__table-wrap { overflow-x: auto; }
.geo__table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.geo__table th, .geo__table td {
  text-align: left; padding: 0.5rem 0.45rem; border-bottom: 1px solid rgba(0,0,0,0.06); vertical-align: middle;
}
.geo__chip {
  display: inline-block; background: #e8f1f7; color: #0b3b5c; border-radius: 999px;
  padding: 0.15rem 0.55rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
}
.btn {
  border: 1px solid rgba(0,0,0,0.15); background: #fff; border-radius: 8px;
  padding: 0.45rem 0.8rem; cursor: pointer; font-weight: 600; white-space: nowrap;
}
.btn-primary { background: #0b3b5c; color: #fff; border-color: #0b3b5c; }
@media (max-width: 720px) {
  .geo__header { flex-direction: column; }
  .geo__add--grid { grid-template-columns: 1fr; }
}
</style>
