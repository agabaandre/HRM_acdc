<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { fetchCbpModules, type CbpNavPayload } from '@/lib/cbpModules'
import { readStaffSsoToken, appendSsoTokenToUrl } from '@/lib/cbpSystems'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { logoutUrl } from '@/lib/auth'

defineProps<{
  userName: string | null
  avatarUrl?: string | null
  theme?: 'dark' | 'light'
}>()

const emblemUrl = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff/apm/assets/images/au_emblem.png`
  }
  return '/staff/apm/assets/images/au_emblem.png'
})

const auth = useAuthStore()
const route = useRoute()

const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref<string | null>(null)

const portalHome = computed(() => nav.value?.home?.href ?? '/')
const portalHomeLabel = computed(() => nav.value?.home?.label ?? 'CBP Home')
const systems = computed(() => nav.value?.modules ?? [])

const portalToggleActive = computed(() => route.path === '/')

const portalDdRef = ref<HTMLElement | null>(null)
const userDdRef = ref<HTMLElement | null>(null)
const portalOpen = ref(false)
const userOpen = ref(false)

async function loadCbpModules() {
  if (!auth.isAuthenticated) {
    nav.value = null
    return
  }
  navLoading.value = true
  navError.value = null
  try {
    nav.value = await fetchCbpModules()
  } catch (e) {
    nav.value = null
    navError.value = apiErrorMessage(e, 'Could not load CBP modules')
  } finally {
    navLoading.value = false
  }
}

function moduleHref(href: string): string {
  return appendSsoTokenToUrl(href, readStaffSsoToken())
}

function togglePortal() {
  userOpen.value = false
  portalOpen.value = !portalOpen.value
}

function toggleUser() {
  portalOpen.value = false
  userOpen.value = !userOpen.value
}

function closeMenus() {
  portalOpen.value = false
  userOpen.value = false
}

function onDocClick(e: MouseEvent) {
  const t = e.target as Node
  if (portalDdRef.value?.contains(t) || userDdRef.value?.contains(t)) {
    return
  }
  closeMenus()
}

watch(
  () => auth.isAuthenticated,
  () => {
    void loadCbpModules()
  },
  { immediate: true },
)

watch(
  () => route.fullPath,
  () => closeMenus(),
)

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <header class="cbp-top-header">
    <div class="cbp-top-header__inner">
      <div class="cbp-top-header__brand">
        <RouterLink to="/" class="cbp-top-header__logo-link">
          <img
            :src="emblemUrl"
            alt="Africa CDC"
            class="cbp-top-header__logo"
          />
          <span class="cbp-top-header__title">Staff Portal</span>
        </RouterLink>
      </div>

      <div class="cbp-top-header__actions">
        <div ref="portalDdRef" class="cbp-dropdown">
          <button
            type="button"
            class="cbp-dropdown__toggle"
            :class="{ active: portalToggleActive || portalOpen }"
            @click="togglePortal"
          >
            <i class="fa-solid fa-grid-2" aria-hidden="true" />
            CBP Modules
          </button>
          <div v-show="portalOpen" class="cbp-dropdown__menu cbp-dropdown__menu--wide">
            <p v-if="navLoading" class="cbp-dropdown__hint">Loading modules…</p>
            <p v-else-if="navError" class="cbp-dropdown__hint text-danger">{{ navError }}</p>
            <template v-else>
              <a :href="portalHome" class="cbp-dropdown__item" @click="closeMenus">
                <i class="fa-solid fa-house" aria-hidden="true" />
                {{ portalHomeLabel }}
              </a>
              <a
                v-for="mod in systems"
                :key="mod.href"
                :href="moduleHref(mod.href)"
                class="cbp-dropdown__item"
                :target="mod.opens_in_new_tab ? '_blank' : undefined"
                :rel="mod.opens_in_new_tab ? 'noopener noreferrer' : undefined"
                @click="closeMenus"
              >
                <i :class="['fa-solid', mod.icon || 'fa-th']" aria-hidden="true" />
                {{ mod.label }}
              </a>
            </template>
          </div>
        </div>

        <slot name="extra" />

        <div ref="userDdRef" class="cbp-dropdown cbp-dropdown--user">
          <button type="button" class="cbp-dropdown__toggle cbp-user-toggle" @click="toggleUser">
            <CbpAvatar :name="userName ?? 'Staff'" :src="avatarUrl ?? null" size="sm" />
            <span class="cbp-user-toggle__name">{{ userName ?? 'Staff' }}</span>
            <i class="fa-solid fa-chevron-down" aria-hidden="true" />
          </button>
          <div v-show="userOpen" class="cbp-dropdown__menu">
            <a :href="logoutUrl()" class="cbp-dropdown__item" @click="closeMenus">
              <i class="fa-solid fa-right-from-bracket" aria-hidden="true" />
              Sign out
            </a>
          </div>
        </div>
      </div>
    </div>
  </header>
</template>
