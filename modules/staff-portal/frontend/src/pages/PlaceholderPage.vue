<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'

const route = useRoute()
const title = computed(() => (route.meta.title as string) ?? 'Page not found')
const isRiskRegisterMistake = computed(() => {
  const p = route.path.replace(/\/+$/, '')
  return p === '/risk-register' || p.startsWith('/risk-register/') || p === '/backend/risk-register'
})
const legacyPath = computed(() => {
  const base = (import.meta.env.VITE_STAFF_PORTAL_API_BASE_URL as string) || '/staff/backend'
  return `${base.replace(/\/$/, '')}${route.path}`
})
const riskRegisterUrl = '/staff/risk-register/'
</script>

<template>
  <div>
    <CbpPageHeading
      :title="isRiskRegisterMistake ? 'Risk Register lives in a separate app' : title"
      :subtitle="
        isRiskRegisterMistake
          ? 'This Staff Portal URL is not the Risk Register. Use the sibling app (or launch from CBP Modules).'
          : 'This path is not a Staff Portal page.'
      "
    />
    <div v-if="isRiskRegisterMistake" class="ph-alert">
      <p>
        Open
        <a :href="riskRegisterUrl"><code>{{ riskRegisterUrl }}</code></a>
        or launch <strong>Risk Register</strong> from CBP Modules.
      </p>
      <p class="text-medium-emphasis">
        If SSO failed, you should see an error on the Risk Register access page — not this placeholder.
      </p>
    </div>
    <p v-else class="text-medium-emphasis">
      Requested path:
      <code>{{ route.fullPath }}</code>
      <br />
      Legacy API hint:
      <code>{{ legacyPath }}</code>
    </p>
  </div>
</template>

<style scoped>
.ph-alert {
  margin-top: 1rem;
  padding: 1rem 1.1rem;
  background: #fff8e6;
  border: 1px solid #f0d78c;
  border-radius: 8px;
  max-width: 40rem;
}
.ph-alert a {
  color: #0b6e4f;
  font-weight: 600;
}
</style>
