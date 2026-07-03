<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import MaterialProAppLayout from './components/layout/MaterialProAppLayout.vue'
import CbpRoutePreloader from './components/common/CbpRoutePreloader.vue'
import { routePreloaderVisible } from './lib/appPreloader'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const route = useRoute()
const vuetifyTheme = useTheme()
const theme = ref<'dark' | 'light'>('light')
const THEME_KEY = 'helpdesk.theme'

const displayName = computed(() => (auth.isAuthenticated ? auth.me?.name ?? 'Staff' : null))

const showChrome = computed(() => route.meta.chrome !== false)

const contentLoadingClass = computed(() =>
  routePreloaderVisible.value ? 'hd-content-loading' : '',
)

function applyTheme(next: 'dark' | 'light') {
  theme.value = next
  vuetifyTheme.global.name.value = next
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
  applyTheme('light')
}
</script>

<template>
  <UApp>
    <MaterialProAppLayout
      v-if="showChrome"
      :display-name="displayName"
      :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
      :theme="theme"
      @update:theme="onThemeChange"
    />
    <div v-else class="hd-content-frame hd-content-frame--full" :class="contentLoadingClass">
      <div class="hd-content-frame__body">
        <RouterView />
      </div>
    </div>
    <CbpRoutePreloader />
  </UApp>
</template>
