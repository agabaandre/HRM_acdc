<script setup lang="ts">
import { computed } from 'vue'
import ModuleCard from '@/components/molecules/ModuleCard.vue'
import type { CbpModuleLink } from '@/lib/cbpModules'

const props = defineProps<{
  modules: CbpModuleLink[]
  query?: string
  onModuleClick?: (mod: CbpModuleLink, e: Event) => void
}>()

const filtered = computed(() => {
  const q = (props.query || '').trim().toLowerCase()
  if (!q) return props.modules
  return props.modules.filter((m) => {
    const hay = `${m.label} ${m.description || ''} ${m.module_key || ''}`.toLowerCase()
    return hay.includes(q)
  })
})

function handleClick(mod: CbpModuleLink, e: Event) {
  props.onModuleClick?.(mod, e)
}
</script>

<template>
  <div>
    <p v-if="query && filtered.length === 0" class="cbp-home-search-empty">
      No modules match your search.
    </p>
    <div class="cbp-home-grid">
      <div
        v-for="(mod, index) in filtered"
        :key="mod.id || mod.module_key || mod.href"
        class="setting-card-item"
        :style="{ animationDelay: `${0.05 * (index + 1)}s` }"
      >
        <ModuleCard
          :label="mod.label"
          :description="mod.description"
          :icon="mod.icon"
          :href="mod.href"
          :opens-in-new-tab="mod.opens_in_new_tab && !mod.sso_launch"
          @click="handleClick(mod, $event)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.cbp-home-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1.5rem;
  justify-content: center;
}

@media (min-width: 768px) {
  .cbp-home-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.cbp-home-search-empty {
  text-align: center;
  color: #6c757d;
  font-size: 0.875rem;
  margin: 0 0 1rem;
}
</style>
