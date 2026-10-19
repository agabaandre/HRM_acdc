<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '@/lib/api'
import { configModuleFromPath } from '@/lib/configurationModules'

const route = useRoute()
const current = computed(() => configModuleFromPath(route.path))
const summary = ref<Record<string, number>>({})
const error = ref('')

function num(key: string): string {
  const value = summary.value[key]
  return Number.isFinite(value) ? value.toLocaleString() : '—'
}

onMounted(async () => {
  try {
    const { data } = await api.get<{ data: Record<string, Record<string, number>> }>('/api/v1/configuration/summary')
    const key = current.value?.summaryKey
    summary.value = key ? data.data[key] || {} : {}
  } catch {
    error.value = 'Could not load this dashboard.'
  }
})
</script>

<template>
  <div>
    <p class="cfg-dash-lede">A summary of the sections in this module.</p>
    <p v-if="error" class="cfg-dash-error">{{ error }}</p>
    <div v-else class="cfg-dash-grid">
      <RouterLink
        v-for="section in current?.sections || []"
        :key="section.path"
        class="cfg-dash-card"
        :to="`${current?.portalPath}/${section.path}`"
      >
        <div class="cfg-dash-card-title">
          <i :class="`fa-solid ${section.icon}`" aria-hidden="true" />
          {{ section.label }}
        </div>
        <div class="cfg-dash-stats">
          <div v-for="stat in section.stats" :key="stat.key">
            <strong>{{ num(stat.key) }}</strong>
            <span>{{ stat.label }}</span>
          </div>
        </div>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.cfg-dash-lede {
  margin: 0 0 1rem;
  color: #5c6b80;
}

.cfg-dash-error {
  color: #9b2c2c;
}

.cfg-dash-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.85rem;
}

.cfg-dash-card {
  display: block;
  padding: 1rem 1.05rem;
  border: 2px solid #b7c3d4;
  border-radius: 12px;
  background: #fff;
  color: #1b2744;
  text-decoration: none;
}

.cfg-dash-card:hover {
  border-color: #119a48;
}

.cfg-dash-card-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 700;
}

.cfg-dash-card-title i {
  color: #119a48;
}

.cfg-dash-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.85rem 1.25rem;
  margin-top: 0.85rem;
}

.cfg-dash-stats strong {
  display: block;
  font-size: 1.35rem;
  line-height: 1.1;
}

.cfg-dash-stats span {
  color: #5c6b80;
  font-size: 0.8rem;
}
</style>
