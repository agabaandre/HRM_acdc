<script setup lang="ts">
import { computed, onMounted, provide } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { createHelpdeskAdminSettings, helpdeskAdminSettingsKey } from '../../composables/useHelpdeskAdminSettings'

const ctx = createHelpdeskAdminSettings()
provide(helpdeskAdminSettingsKey, ctx)

const route = useRoute()
const sectionTitle = computed(() => (route.meta.settingsTitle as string) ?? 'Settings')

onMounted(() => {
  void ctx.load()
})
</script>

<template>
  <div>
    <nav class="cbp-breadcrumb" aria-label="Breadcrumb">
      <RouterLink to="/">Overview</RouterLink>
      <span class="cbp-bc-sep">/</span>
      <RouterLink to="/settings/general">Settings</RouterLink>
      <span class="cbp-bc-sep">/</span>
      <span>{{ sectionTitle }}</span>
    </nav>
    <h1 class="cbp-settings-page-title">{{ sectionTitle }}</h1>
    <p v-if="ctx.err" class="cbp-flash cbp-flash-err">{{ ctx.err }}</p>
    <p v-if="ctx.ok" class="cbp-flash cbp-flash-ok">{{ ctx.ok }}</p>
    <div class="cbp-card">
      <RouterView />
    </div>
  </div>
</template>
