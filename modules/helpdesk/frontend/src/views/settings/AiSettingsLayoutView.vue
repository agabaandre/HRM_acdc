<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()
const onProvider = computed(() => route.name === 'settings-ai')
const onFaqSources = computed(() => route.name === 'settings-ai-faq-sources')
</script>

<template>
  <div class="ai-settings-layout">
    <nav class="ai-subtabs" aria-label="AI settings sections">
      <RouterLink to="/settings/ai" class="ai-subtab" :class="{ active: onProvider }">
        AI provider &amp; models
      </RouterLink>
      <RouterLink to="/settings/ai/faq-sources" class="ai-subtab" :class="{ active: onFaqSources }">
        FAQ sources
      </RouterLink>
    </nav>
    <RouterView />
  </div>
</template>

<style scoped>
.ai-settings-layout {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.ai-subtabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding-bottom: 0.35rem;
  border-bottom: 1px solid #e2e8f0;
}

.ai-subtab {
  padding: 0.45rem 0.85rem;
  border-radius: 4px;
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
  color: #475569;
  background: transparent;
}

.ai-subtab:hover {
  background: rgba(17, 154, 72, 0.08);
  color: #0d7a3a;
}

.ai-subtab.active {
  background: rgba(17, 154, 72, 0.14);
  color: #0d7a3a;
}
</style>
