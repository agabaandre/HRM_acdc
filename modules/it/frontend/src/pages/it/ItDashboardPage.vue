<script setup lang="ts">
import { onMounted, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { api } from '@/lib/api'

interface LicenseSummary {
  license_count: number
  expiring_soon: number
  expired: number
}

interface AssetSummary {
  asset_count: number
  deployed: number
  in_stock: number
}

interface SystemsSummary {
  systems_total: number
  modules_total: number
}

interface ServerSummary {
  total: number
  running: number
}

interface AreaCard {
  label: string
  to: string
  icon: string
  stats: Array<{ label: string; value: string }>
  error: string | null
}

const loading = ref(true)
const cards = ref<AreaCard[]>([])

function num(value: unknown): string {
  const n = Number(value)
  return Number.isFinite(n) ? n.toLocaleString() : '—'
}

async function loadOne<T>(url: string): Promise<{ data: T | null; error: string | null }> {
  try {
    const res = await api.get<{ data: T }>(url)
    return { data: res.data.data, error: null }
  } catch {
    return { data: null, error: 'Could not load' }
  }
}

async function load() {
  loading.value = true
  const [licenses, assets, systems, servers] = await Promise.all([
    loadOne<LicenseSummary>('/api/v1/tools/licenses/summary'),
    loadOne<AssetSummary>('/api/v1/tools/it-assets/summary'),
    loadOne<SystemsSummary>('/api/v1/tools/information-systems/summary'),
    loadOne<ServerSummary>('/api/v1/tools/servers/summary'),
  ])

  cards.value = [
    {
      label: 'Licenses',
      to: '/licenses',
      icon: 'fa-solid fa-key',
      error: licenses.error,
      stats: [
        { label: 'Total', value: num(licenses.data?.license_count) },
        { label: 'Expiring soon', value: num(licenses.data?.expiring_soon) },
        { label: 'Expired', value: num(licenses.data?.expired) },
      ],
    },
    {
      label: 'ITSM',
      to: '/itsm',
      icon: 'fa-solid fa-laptop',
      error: assets.error,
      stats: [
        { label: 'Assets', value: num(assets.data?.asset_count) },
        { label: 'Deployed', value: num(assets.data?.deployed) },
        { label: 'In stock', value: num(assets.data?.in_stock) },
      ],
    },
    {
      label: 'Information Systems',
      to: '/information-systems',
      icon: 'fa-solid fa-server',
      error: systems.error,
      stats: [
        { label: 'Systems', value: num(systems.data?.systems_total) },
        { label: 'Modules', value: num(systems.data?.modules_total) },
      ],
    },
    {
      label: 'Servers',
      to: '/servers',
      icon: 'fa-solid fa-cloud',
      error: servers.error,
      stats: [
        { label: 'Total', value: num(servers.data?.total) },
        { label: 'Running', value: num(servers.data?.running) },
      ],
    },
  ]
  loading.value = false
}

onMounted(() => void load())
</script>

<template>
  <div>
    <CbpPageHeading title="Dashboard">
      <template #lede>
        Licenses, ITSM, information systems, and servers.
      </template>
    </CbpPageHeading>

    <p v-if="loading" class="dash-status">Loading dashboard…</p>

    <div v-else class="dash-grid">
      <router-link v-for="card in cards" :key="card.to" :to="card.to" class="dash-card">
        <div class="dash-card-head">
          <i :class="card.icon" aria-hidden="true" />
          <h2>{{ card.label }}</h2>
        </div>
        <p v-if="card.error" class="dash-error">{{ card.error }}</p>
        <dl v-else class="dash-stats">
          <div v-for="stat in card.stats" :key="stat.label">
            <dt>{{ stat.label }}</dt>
            <dd>{{ stat.value }}</dd>
          </div>
        </dl>
      </router-link>
    </div>
  </div>
</template>

<style scoped>
.dash-status {
  margin: 0;
  color: #64748b;
  font-size: 0.9rem;
}

.dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.85rem;
}

.dash-card {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1rem 1.05rem 1.1rem;
  text-decoration: none;
  color: inherit;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.dash-card:hover {
  border-color: #119a48;
}

.dash-card-head {
  display: flex;
  align-items: center;
  gap: 0.55rem;
}

.dash-card-head i {
  color: #0d7a3a;
  font-size: 1rem;
}

.dash-card-head h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: #0f172a;
}

.dash-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(72px, 1fr));
  gap: 0.65rem;
  margin: 0;
}

.dash-stats dt {
  font-size: 0.68rem;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-weight: 700;
}

.dash-stats dd {
  margin: 0.15rem 0 0;
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
}

.dash-error {
  margin: 0;
  color: #b45309;
  font-size: 0.85rem;
}
</style>
