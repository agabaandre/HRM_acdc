<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import CbpTopHeader from './components/layout/CbpTopHeader.vue'
import CbpThemeSwitch from './components/layout/CbpThemeSwitch.vue'
import CbpPrimaryNav from './components/layout/CbpPrimaryNav.vue'
import CbpPageFooter from './components/layout/CbpPageFooter.vue'
import { useAuthStore } from './stores/auth'
import { ref } from 'vue'

const auth = useAuthStore()
const route = useRoute()
const theme = ref<'dark' | 'light'>('dark')
const THEME_KEY = 'helpdesk.theme'

const displayName = computed(() => (auth.isAuthenticated ? auth.me?.name ?? 'Staff' : null))

// Routes (e.g. /screen) opt out of the standard portal chrome by setting
// meta.chrome === false. Default is "chrome on".
const showChrome = computed(() => route.meta.chrome !== false)

function applyTheme(next: 'dark' | 'light') {
  theme.value = next
  document.documentElement.classList.toggle('helpdesk-theme-dark', next === 'dark')
  document.documentElement.classList.toggle('helpdesk-theme-light', next === 'light')
}

function onThemeChange(next: 'dark' | 'light') {
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
      :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
      :theme="theme"
    >
      <template v-if="auth.isAuthenticated" #extra>
        <CbpThemeSwitch :theme="theme" @update:theme="onThemeChange" />
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
