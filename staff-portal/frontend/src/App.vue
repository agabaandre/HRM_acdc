<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import PortalTopHeader from './components/layout/PortalTopHeader.vue'
import PortalPrimaryNav from './components/layout/PortalPrimaryNav.vue'
import CbpPageFooter from '@cbp/layout/CbpPageFooter.vue'
import CbpThemeSwitch from '@cbp/layout/CbpThemeSwitch.vue'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const route = useRoute()
const vuetifyTheme = useTheme()
const theme = ref<'dark' | 'light'>('light')
const THEME_KEY = 'staff-portal.theme'

const displayName = computed(() => (auth.isAuthenticated ? auth.me?.name ?? 'Staff' : null))
const showChrome = computed(() => route.meta.chrome !== false)

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
    <div v-if="showChrome" class="cbp-wrapper">
      <PortalTopHeader
        :user-name="displayName"
        :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
        :theme="theme"
      >
        <template v-if="auth.isAuthenticated" #extra>
          <CbpThemeSwitch :theme="theme" @update:theme="onThemeChange" />
        </template>
      </PortalTopHeader>
      <PortalPrimaryNav />
      <div class="cbp-page-wrapper">
        <div class="cbp-page-content hd-content-frame">
          <div class="hd-content-frame__body">
            <RouterView />
          </div>
        </div>
      </div>
      <CbpPageFooter />
    </div>
    <div v-else class="hd-content-frame hd-content-frame--full">
      <div class="hd-content-frame__body">
        <RouterView />
      </div>
    </div>
  </UApp>
</template>
