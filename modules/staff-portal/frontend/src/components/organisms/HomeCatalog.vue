<script setup lang="ts">
import { computed, ref } from 'vue'
import ModuleCard from '@/components/molecules/ModuleCard.vue'
import {
  FREQUENT_MODULES,
  HOME_CATALOG,
  type HomeCatalogItem,
  type HomeCatalogTabId,
} from '@/lib/homeCatalog'
import type { CbpModuleLink } from '@/lib/cbpModules'
import { launchCbpModule } from '@/lib/cbpLaunch'
import { staffWebRoot } from '@/lib/staffWebRoot'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()

const props = defineProps<{
  modules: CbpModuleLink[]
  query?: string
  onModuleClick?: (mod: CbpModuleLink, e: Event) => void
}>()

const FREQUENT_DIVISION = 'Frequently Used'

const tab = ref<HomeCatalogTabId>('operations')
const division = ref(FREQUENT_DIVISION)
const moduleScope = ref<'total' | 'active'>('total')

function isActiveModule(item: HomeCatalogItem): boolean {
  return Boolean((item.moduleKey || '').trim() || (item.portalPath || '').trim() || (item.sitePath || '').trim())
}

const modulesByKey = computed(() => {
  const map = new Map<string, CbpModuleLink>()
  for (const mod of props.modules) {
    const key = (mod.module_key || '').trim()
    if (key && !map.has(key)) map.set(key, mod)
  }
  return map
})

function itemHref(item: HomeCatalogItem): string {
  if (item.portalPath) {
    return `${staffWebRoot()}${item.portalPath.startsWith('/') ? item.portalPath : `/${item.portalPath}`}`
  }
  if (item.sitePath && typeof window !== 'undefined') {
    return `${window.location.origin}${item.sitePath.startsWith('/') ? item.sitePath : `/${item.sitePath}`}`
  }
  return ''
}

function iconClass(icon?: string): string {
  const raw = (icon || 'fa-th').trim()
  if (raw.includes('fa-solid') || raw.includes('fas ')) return raw
  const name = raw.replace(/^(?:fa\s+)+/, '').replace(/^fa-/, '')
  return `fa-solid fa-${name}`
}

function resolved(item: HomeCatalogItem): {
  live: CbpModuleLink | null
  href: string
  description: string
  icon?: string
  available: boolean
} {
  const live = item.moduleKey ? modulesByKey.value.get(item.moduleKey) || null : null
  if (live) {
    return {
      live,
      href: live.href,
      description: live.description || '',
      icon: live.icon || item.icon,
      available: true,
    }
  }
  const href = itemHref(item)
  if (href) {
    return {
      live: null,
      href,
      description: '',
      icon: item.icon,
      available: true,
    }
  }
  if (item.moduleKey) {
    return { live: null, href: '', description: 'Not available yet', icon: item.icon, available: false }
  }
  return {
    live: null,
    href: '',
    description: 'Not available yet',
    icon: item.icon,
    available: false,
  }
}

const activeTab = computed(() => HOME_CATALOG.find((entry) => entry.id === tab.value) || HOME_CATALOG[0])

const HOME_AREAS = new Set<HomeCatalogTabId>(['operations', 'programs', 'configuration'])

function placementFor(tabId: HomeCatalogTabId, groupTitle: string, item: HomeCatalogItem): {
  tabId: HomeCatalogTabId
  groupTitle: string
} {
  const live = item.moduleKey ? modulesByKey.value.get(item.moduleKey) || null : null
  const wanted = (live?.primary_tab || '').trim()
  const area = (live?.home_area || '').trim() as HomeCatalogTabId
  const explicitTab = HOME_AREAS.has(area) ? area : null
  if (!wanted && !explicitTab) return { tabId, groupTitle }

  if (!explicitTab) {
    const sameTab = HOME_CATALOG.find((entry) => entry.id === tabId)
    if (wanted && sameTab?.groups.some((group) => group.title === wanted)) {
      return { tabId, groupTitle: wanted }
    }
    const other = HOME_CATALOG.find((entry) => entry.groups.some((group) => group.title === wanted))
    if (other && wanted) return { tabId: other.id, groupTitle: wanted }
    return { tabId, groupTitle }
  }

  if (wanted) return { tabId: explicitTab, groupTitle: wanted }
  const target = HOME_CATALOG.find((entry) => entry.id === explicitTab)
  if (target?.groups.some((group) => group.title === groupTitle)) {
    return { tabId: explicitTab, groupTitle }
  }
  return { tabId: explicitTab, groupTitle: target?.groups[0]?.title ?? groupTitle }
}

/** Catalog groups, with each live module filed under its saved primary tab. */
const activeGroups = computed(() => {
  const placed = new Map<string, HomeCatalogItem[]>()
  for (const entry of HOME_CATALOG) {
    for (const group of entry.groups) {
      for (const item of group.items) {
        const place = placementFor(entry.id, group.title, item)
        const key = `${place.tabId}::${place.groupTitle}`
        const list = placed.get(key) || []
        list.push(item)
        placed.set(key, list)
      }
    }
  }
  const groups = activeTab.value.groups.map((group) => ({
    ...group,
    items: placed.get(`${activeTab.value.id}::${group.title}`) || [],
  }))
  const known = new Set(activeTab.value.groups.map((group) => group.title))
  for (const [key, items] of placed) {
    const sep = key.indexOf('::')
    const tabId = key.slice(0, sep)
    const title = key.slice(sep + 2)
    if (tabId !== activeTab.value.id || known.has(title) || items.length === 0) continue
    groups.push({ title, items })
  }
  return groups
})

function isShown(item: HomeCatalogItem): boolean {
  return resolved(item).available
}

const scopedGroups = computed(() =>
  activeGroups.value.map((group) => ({
    ...group,
    items: group.items.filter(
      (item) => isShown(item) && (moduleScope.value === 'total' || isActiveModule(item)),
    ),
  })),
)

const moduleCounts = computed(() => {
  const items = activeGroups.value.flatMap((group) => group.items).filter(isShown)
  return {
    total: items.length,
    active: items.filter(isActiveModule).length,
  }
})

const frequentItems = computed(() =>
  FREQUENT_MODULES.filter(
    (item) => isShown(item) && (moduleScope.value === 'total' || isActiveModule(item)),
  ),
)

const divisionChips = computed(() => {
  const groups = scopedGroups.value
    .filter((group) => group.items.length > 0)
    .map((group) => ({
      title: group.title,
      count: group.items.length,
    }))
  const total = groups.reduce((sum, group) => sum + group.count, 0)
  return [
    { title: FREQUENT_DIVISION, count: frequentItems.value.length },
    ...groups.filter((group) => group.title),
    { title: 'All', count: total },
  ]
})

function selectTab(id: HomeCatalogTabId) {
  tab.value = id
  division.value = 'All'
}

function onCatalogClick(item: HomeCatalogItem, event: Event) {
  const next = (item.launchPath || '').trim()
  if (item.moduleKey && next) {
    event.preventDefault()
    void launchCbpModule(item.moduleKey, false, next)
    return
  }
  const live = resolved(item).live
  if (live) props.onModuleClick?.(live, event)
}

const visibleGroups = computed(() => {
  const q = (props.query || '').trim().toLowerCase()
  return scopedGroups.value
    .filter((group) => division.value === 'All' || group.title === division.value)
    .map((group) => {
      const items = group.items.filter((item) => {
        const card = resolved(item)
        if (!q) return true
        const hay = `${group.title} ${item.label} ${card.description}`.toLowerCase()
        return hay.includes(q)
      })
      return { ...group, items }
    })
    .filter((group) => group.items.length > 0)
})

/** All is one alphabetical grid. Frequently Used is the quick-link set. A division keeps its heading. */
const displayGroups = computed(() => {
  if (division.value === FREQUENT_DIVISION) {
    const q = (props.query || '').trim().toLowerCase()
    const items = frequentItems.value.filter((item) => {
      if (!q) return true
      const card = resolved(item)
      return `${item.label} ${card.description}`.toLowerCase().includes(q)
    })
    if (items.length === 0) return []
    return [{ title: '', items }]
  }
  if (division.value !== 'All') return visibleGroups.value
  const items = visibleGroups.value
    .flatMap((group) => group.items)
    .slice()
    .sort((a, b) => a.label.localeCompare(b.label, undefined, { sensitivity: 'base' }))
  if (items.length === 0) return []
  return [{ title: '', items }]
})
</script>

<template>
  <div>
    <div class="cbp-home-toolbar">
      <div class="cbp-home-tabs" role="tablist" aria-label="Module groups">
        <button
          v-for="entry in HOME_CATALOG"
          :key="entry.id"
          type="button"
          class="cbp-home-tab"
          role="tab"
          :class="{ 'is-active': tab === entry.id }"
          :aria-selected="tab === entry.id"
          @click="selectTab(entry.id)"
        >
          {{ entry.label }}
        </button>
      </div>
      <div class="cbp-home-toolbar-search">
        <slot name="search" />
      </div>
    </div>

    <div class="cbp-home-divisions" role="tablist" aria-label="Divisions">
      <button
        v-for="chip in divisionChips"
        :key="chip.title"
        type="button"
        class="cbp-home-division"
        role="tab"
        :class="{ 'is-active': division === chip.title }"
        :aria-selected="division === chip.title"
        @click="division = chip.title"
      >
        {{ chip.title }} ({{ chip.count }})
      </button>
      <button
        type="button"
        class="cbp-home-scope-tab"
        role="tab"
        :class="{ 'is-active': moduleScope === 'total' }"
        :aria-selected="moduleScope === 'total'"
        @click="moduleScope = 'total'"
      >
        {{ locale.t('home.total_modules', 'Total Modules') }} ({{ moduleCounts.total }})
      </button>
      <button
        type="button"
        class="cbp-home-scope-tab"
        role="tab"
        :class="{ 'is-active': moduleScope === 'active' }"
        :aria-selected="moduleScope === 'active'"
        @click="moduleScope = 'active'"
      >
        {{ locale.t('home.active_modules', 'Active modules') }} ({{ moduleCounts.active }})
      </button>
    </div>

    <p v-if="query && displayGroups.length === 0" class="cbp-home-search-empty">
      {{ locale.t('home.no_match', 'No modules match your search.') }}
    </p>

    <section v-for="group in displayGroups" :key="group.title || 'all'" class="cbp-home-group">
      <h2 v-if="group.title" class="cbp-home-group-title">{{ group.title }}</h2>
      <div class="cbp-home-grid">
        <div v-for="item in group.items" :key="`${group.title}-${item.label}`" class="setting-card-item">
          <ModuleCard
            v-if="resolved(item).available && resolved(item).live"
            :label="item.label"
            :description="resolved(item).description"
            :icon="resolved(item).icon"
            :href="resolved(item).href"
            :opens-in-new-tab="!!resolved(item).live?.opens_in_new_tab && !resolved(item).live?.sso_launch"
            @click="onCatalogClick(item, $event)"
          />
          <ModuleCard
            v-else-if="resolved(item).available"
            :label="item.label"
            :description="resolved(item).description"
            :icon="resolved(item).icon"
            :href="resolved(item).href"
            :opens-in-new-tab="!!item.sitePath"
          />
          <div v-else class="settings-card cbp-home-card-soon" aria-disabled="true">
            <div>
              <h6>{{ item.label }}</h6>
              <p>{{ resolved(item).description }}</p>
            </div>
            <div class="widgets-icons" aria-hidden="true">
              <i :class="iconClass(resolved(item).icon)" />
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.cbp-home-toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 0.75rem;
  margin: 0 0 0.85rem;
}

.cbp-home-tabs {
  grid-column: 2;
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin: 0;
}

.cbp-home-toolbar-search {
  grid-column: 3;
  justify-self: end;
  width: min(18rem, 100%);
}

.cbp-home-scope-tab {
  display: inline-flex;
  align-items: center;
  border: 1px solid #b7c3d4;
  background: #fff;
  color: #1b2744;
  font-weight: 700;
  font-size: 0.84rem;
  line-height: 1.2;
  padding: 0.4rem 0.75rem;
  border-radius: 8px;
  cursor: pointer;
}

.cbp-home-scope-tab.is-active {
  background: #1b2744;
  border-color: #1b2744;
  color: #fff;
}

.cbp-home-divisions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  align-items: center;
  gap: 0.55rem;
  margin: 0 auto 1.5rem;
  max-width: 72rem;
}

.cbp-home-division {
  border: 1px solid #d5dbe3;
  background: #fff;
  color: #243044;
  font-weight: 600;
  font-size: 0.92rem;
  line-height: 1.2;
  padding: 0.45rem 0.95rem;
  border-radius: 8px;
  cursor: pointer;
}

.cbp-home-division.is-active {
  background: #1b2744;
  border-color: #1b2744;
  color: #fff;
}

.cbp-home-tab {
  border: 1px solid #e9ecef;
  background: #fff;
  color: #2c3e50;
  font-weight: 700;
  font-size: 0.95rem;
  padding: 0.55rem 1.35rem;
  border-radius: 999px;
  cursor: pointer;
}

.cbp-home-tab.is-active {
  background: #119a48;
  border-color: #119a48;
  color: #fff;
}

.cbp-home-group {
  margin: 0 0 1.75rem;
}

.cbp-home-group-title {
  margin: 0 0 0.75rem;
  padding-bottom: 0.35rem;
  border-bottom: 2px solid rgba(17, 154, 72, 0.28);
  color: #119a48;
  font-size: 1.05rem;
  font-weight: 700;
}

.cbp-home-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 0.75rem;
}

.setting-card-item {
  display: flex;
  height: 100%;
  min-height: 0;
}

.setting-card-item :deep(.cbp-home-card-link),
.setting-card-item :deep(.settings-card) {
  display: flex;
  flex: 1 1 auto;
  width: 100%;
  height: 100%;
}

.cbp-home-card-soon {
  cursor: default;
}

.cbp-home-search-empty {
  text-align: center;
  color: #6c757d;
  font-size: 0.875rem;
  margin: 0 0 1rem;
}

@media (max-width: 720px) {
  .cbp-home-toolbar {
    grid-template-columns: 1fr;
  }

  .cbp-home-tabs,
  .cbp-home-toolbar-search {
    grid-column: 1;
    justify-self: center;
  }
}

@media (min-width: 640px) {
  .cbp-home-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 960px) {
  .cbp-home-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
</style>
