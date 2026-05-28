<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import CbpTopHeader from './components/layout/CbpTopHeader.vue'
import CbpPrimaryNav from './components/layout/CbpPrimaryNav.vue'
import CbpPageFooter from './components/layout/CbpPageFooter.vue'
import { useAuthStore } from './stores/auth'
import { ref } from 'vue'

const auth = useAuthStore()
const route = useRoute()
const theme = ref<'dark' | 'light'>('dark')
const THEME_KEY = 'helpdesk.theme'

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

// Routes (e.g. /screen) opt out of the standard portal chrome by setting
// meta.chrome === false. Default is "chrome on".
const showChrome = computed(() => route.meta.chrome !== false)

function applyTheme(next: 'dark' | 'light') {
  theme.value = next
  document.documentElement.classList.toggle('helpdesk-theme-dark', next === 'dark')
  document.documentElement.classList.toggle('helpdesk-theme-light', next === 'light')
}

function toggleTheme() {
  const next = theme.value === 'dark' ? 'light' : 'dark'
  applyTheme(next)
  window.localStorage.setItem(THEME_KEY, next)
}

const stored = window.localStorage.getItem(THEME_KEY)
if (stored === 'light' || stored === 'dark') {
  applyTheme(stored)
} else {
  // Project default mode.
  applyTheme('dark')
  window.localStorage.setItem(THEME_KEY, 'dark')
}
</script>

<template>
  <div v-if="showChrome" class="cbp-wrapper">
    <CbpTopHeader
      :user-name="displayName"
      :user-subtitle="roleLine"
      :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
      :theme="theme"
    >
      <template v-if="auth.isAuthenticated" #extra>
        <button type="button" class="cbp-topbar-theme" @click="toggleTheme()">
          {{ theme === 'dark' ? 'Light mode' : 'Dark mode' }}
        </button>
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
  <RouterView v-else />
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
.cbp-topbar-theme {
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.35);
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  padding: 0.35rem 0.65rem;
  border-radius: 4px;
  cursor: pointer;
  font-family: inherit;
}
.cbp-topbar-theme:hover {
  background: rgba(255, 255, 255, 0.25);
}
</style>
