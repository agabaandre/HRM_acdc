<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useTheme } from 'vuetify'
import ImpersonationBanner from '@/components/organisms/ImpersonationBanner.vue'
import PortalTopHeader from '@/components/organisms/PortalTopHeader.vue'
import PortalPrimaryNav from '@/components/organisms/PortalPrimaryNav.vue'
import CbpPageFooter from '@cbp/layout/CbpPageFooter.vue'
import CbpThemeSwitch from '@cbp/layout/CbpThemeSwitch.vue'
import { useAuthStore } from '@/stores/auth'
import { apiDocsUrl } from '@/lib/auth'

defineProps<{
  showChrome?: boolean
}>()

const auth = useAuthStore()
const route = useRoute()
const vuetifyTheme = useTheme()
const theme = ref<'dark' | 'light'>('light')
const THEME_KEY = 'staff-portal.theme'

const displayName = computed(() => (auth.isAuthenticated ? auth.me?.name ?? 'Staff' : null))
const apiHref = computed(() => apiDocsUrl())

/** CBP launcher home matches CI3 `/home/index` — top bar only, no staff primary nav. */
const isCbpHome = computed(() => route.name === 'home' || route.path === '/')

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
  <div v-if="showChrome !== false" class="cbp-wrapper" :class="{ 'cbp-wrapper--home': isCbpHome }">
    <ImpersonationBanner />
    <PortalTopHeader
      :user-name="displayName"
      :avatar-url="auth.isAuthenticated ? (auth.me?.avatar_url ?? null) : null"
      :theme="theme"
    >
      <template v-if="auth.isAuthenticated" #extra>
        <CbpThemeSwitch :theme="theme" @update:theme="onThemeChange" />
      </template>
    </PortalTopHeader>
    <PortalPrimaryNav v-if="!isCbpHome" />
    <div class="cbp-page-wrapper" :class="{ 'cbp-page-wrapper--home': isCbpHome }">
      <div v-if="isCbpHome" class="cbp-home-page">
        <slot />
      </div>
      <div v-else class="cbp-page-content hd-content-frame">
        <div class="hd-content-frame__body">
          <slot />
        </div>
      </div>
    </div>
    <CbpPageFooter v-if="!isCbpHome" product="" :api-href="apiHref" />
  </div>
  <div v-else class="hd-content-frame hd-content-frame--full">
    <div class="hd-content-frame__body">
      <slot />
    </div>
  </div>
</template>
