<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { fetchCbpModules, type CbpModuleLink } from '@/lib/cbpModules'
import { appendSsoTokenToUrl, readStaffSsoToken } from '@/lib/cbpSystems'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const modules = ref<CbpModuleLink[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const ssoToken = computed(() => readStaffSsoToken())

function hrefFor(mod: CbpModuleLink): string {
  return appendSsoTokenToUrl(mod.href, ssoToken.value)
}

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const payload = await fetchCbpModules()
    modules.value = payload.modules
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load modules')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="portal-home">
    <CbpPageHeading
      title="CBP Staff Portal"
      subtitle="Africa CDC business platform — launch HR modules and connected systems."
    />

    <p v-if="loading" class="text-medium-emphasis">Loading modules…</p>
    <p v-else-if="error" class="text-error">{{ error }}</p>

    <div v-else class="portal-module-grid">
      <a
        v-for="mod in modules"
        :key="mod.href"
        :href="hrefFor(mod)"
        class="portal-module-card"
        :target="mod.opens_in_new_tab ? '_blank' : undefined"
        :rel="mod.opens_in_new_tab ? 'noopener noreferrer' : undefined"
      >
        <i :class="['fa-solid', mod.icon || 'fa-th']" aria-hidden="true" />
        <span>{{ mod.label }}</span>
      </a>
    </div>
  </div>
</template>

<style scoped>
.portal-module-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
  gap: 1rem;
  margin-top: 1.5rem;
}

.portal-module-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 1.25rem 1rem;
  border: 1px solid var(--ny-border, #e2e8f0);
  border-radius: 0.75rem;
  background: #fff;
  color: inherit;
  text-decoration: none;
  font-weight: 600;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.portal-module-card:hover {
  border-color: var(--cdc-green, #0d7a3a);
  box-shadow: 0 4px 14px rgba(13, 122, 58, 0.12);
}

.portal-module-card i {
  font-size: 1.5rem;
  color: var(--cdc-green, #0d7a3a);
}
</style>
