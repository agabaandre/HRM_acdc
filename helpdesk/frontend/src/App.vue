<script setup lang="ts">
import { computed } from 'vue'
import { RouterView } from 'vue-router'
import CbpTopHeader from './components/layout/CbpTopHeader.vue'
import CbpPrimaryNav from './components/layout/CbpPrimaryNav.vue'
import CbpPageFooter from './components/layout/CbpPageFooter.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()

const displayName = computed(() => (auth.isAuthenticated ? auth.me?.name ?? 'Staff' : null))

const roleLine = computed(() => {
  if (!auth.me?.profile?.role) {
    return null
  }
  const r = auth.me.profile.role
  const sap = (auth.me.profile.sap_no ?? '').trim()
  if (sap) {
    return `${r} · SAP ${sap}`
  }
  const sid = auth.me.profile.staff_id
  return sid != null ? `${r} · Staff ID ${sid}` : r
})
</script>

<template>
  <div class="cbp-wrapper">
    <CbpTopHeader
      :user-name="displayName"
      :user-subtitle="roleLine"
      :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
    >
      <template v-if="auth.isAuthenticated" #extra>
        <button type="button" class="cbp-topbar-logout" @click="auth.logout()">Leave desk</button>
      </template>
    </CbpTopHeader>
    <CbpPrimaryNav />
    <div class="cbp-page-wrapper">
      <div class="cbp-page-content">
        <RouterView />
      </div>
    </div>
    <CbpPageFooter />
  </div>
</template>

<style>
.cbp-topbar-logout {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.35);
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 0.4rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
}
.cbp-topbar-logout:hover {
  background: rgba(255, 255, 255, 0.25);
}
</style>
