<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { configModuleFromPath } from '@/lib/configurationModules'

const route = useRoute()
const current = computed(() => configModuleFromPath(route.path))
</script>

<template>
  <div v-if="current" class="cfg-module">
    <div class="cfg-module-head">
      <div>
        <div class="cfg-module-kicker">Configuration</div>
        <h1>{{ current.label }} ({{ current.code }})</h1>
      </div>
    </div>
    <RouterView />
  </div>
</template>

<style scoped>
.cfg-module-head {
  margin: 0 0 0.75rem;
}

.cfg-module-kicker {
  color: #119a48;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.cfg-module-head h1 {
  margin: 0.15rem 0 0;
  color: #1b2744;
  font-size: 1.35rem;
}
</style>
