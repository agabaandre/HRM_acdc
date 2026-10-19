<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { fetchCbpModules, type CbpModuleLink, type CbpNavPayload } from '@/lib/cbpModules'
import { CBP_PROGRAM_COUNT, CBP_PROGRAM_GROUPS, moduleMenuIcon } from '@/lib/cbpProgramMenu'
import { launchCbpModule, moduleLaunchKey } from '@/lib/cbpLaunch'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { useLocaleStore } from '@/stores/locale'

defineProps<{
  userName: string | null
  avatarUrl?: string | null
  theme?: 'dark' | 'light'
}>()

const auth = useAuthStore()
const branding = useBrandingStore()
const locale = useLocaleStore()
const route = useRoute()

function cbpHomeHref(): string {
  if (typeof window === 'undefined') return '/staff/'
  const path = window.location.pathname.replace(
    /\/(finance|apm|helpdesk|it|registry|risk-register|devtrack)(\/.*)?$/,
    '',
  )
  const base = path && path !== '/' ? path.replace(/\/$/, '') : '/staff'
  return `${window.location.origin}${base}/`
}

const staffBase = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff`
  }
  return '/staff'
})

const logoUrl = computed(() => branding.logoUrl || `${staffBase.value}/assets/images/AU_CDC_Logo-800.png`)
const passwordLoginAvailable = computed(() => auth.passwordLoginAvailable)

const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref<string | null>(null)

const systems = computed(() => nav.value?.modules ?? [])
const moduleScope = ref<'operations' | 'programs'>('operations')
const programGroups = CBP_PROGRAM_GROUPS
const programCount = CBP_PROGRAM_COUNT
const operationsCount = computed(() => systems.value.length)
const homeHref = computed(() => nav.value?.home?.href || cbpHomeHref())

const portalToggleActive = computed(() => {
  if (nav.value?.home?.is_active) {
    return true
  }
  return systems.value.some((m) => m.is_active)
})

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
    const path = route.path === '/' ? 'home' : route.path.replace(/^\//, '')
    nav.value = await fetchCbpModules({
      active: 'risk_register',
      path,
      fresh: true,
    })
    navError.value = null
  } catch (e) {
    nav.value = null
    navError.value = apiErrorMessage(e, locale.t('chrome.load_modules_error', 'Could not load CBP modules'))
  } finally {
    navLoading.value = false
  }
}

function moduleTarget(mod: CbpModuleLink): string | undefined {
  if (mod.sso_launch) return undefined
  return mod.opens_in_new_tab ? '_blank' : undefined
}

function moduleRel(mod: CbpModuleLink): string | undefined {
  if (mod.sso_launch) return undefined
  return mod.opens_in_new_tab ? 'noopener noreferrer' : undefined
}

function onModuleNav(mod: CbpModuleLink, e: Event) {
  closeMenus()
  if (mod.sso_launch) {
    e.preventDefault()
    const key = moduleLaunchKey(mod)
    if (key) {
      void launchCbpModule(key, !!mod.opens_in_new_tab)
    }
  }
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

function onLogout() {
  closeMenus()
  auth.logout()
}

function onLogoError(e: Event) {
  const img = e.target as HTMLImageElement | null
  if (!img) return
  // Abort/reassign while loading can fire error even when the asset is fine —
  // never permanently hide the logo.
  if (img.naturalWidth > 0) return
  const fallback = `${staffBase.value}/assets/images/AU_CDC_Logo-800.png`
  const current = img.currentSrc || img.src || ''
  if (img.dataset.fallbackApplied === '1') return
  if (current === fallback || current.endsWith('/assets/images/AU_CDC_Logo-800.png')) return
  img.dataset.fallbackApplied = '1'
  img.src = fallback
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  void loadCbpModules()
  // Branding is bootstrapped by PortalAppShell — avoid a second fetch race.
})

watch(
  () => auth.isAuthenticated,
  (ok) => {
    if (ok) {
      void loadCbpModules()
    } else {
      nav.value = null
    }
  },
)

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <header class="cbp-topbar">
    <div class="cbp-topbar-inner">
      <a :href="`${staffBase}/`" class="cbp-topbar-logo" :title="locale.t('chrome.staff_portal', 'Staff Portal')">
        <img
          :src="logoUrl"
          width="200"
          :alt="branding.companyName || locale.t('chrome.africa_cdc', 'Africa CDC')"
          @error="onLogoError"
        />
      </a>
      <div class="cbp-topbar-spacer" />
      <div class="cbp-topbar-menu">
        <div
          v-if="userName"
          ref="portalDdRef"
          class="cbp-topbar-portal-dd"
          :class="{ 'is-open': portalOpen }"
        >
          <button
            type="button"
            class="cbp-topbar-dd-toggle"
            :class="{ 'is-active': portalToggleActive }"
            aria-haspopup="true"
            :aria-expanded="portalOpen"
            :title="locale.t('chrome.cbp_modules', 'CBP Modules')"
            @click.stop="togglePortal"
          >
            <i class="bx bx-category cbp-topbar-dd-icon" aria-hidden="true" />
            <span class="cbp-topbar-dd-toggle-label ms-2">{{ locale.t('chrome.cbp_modules', 'CBP Modules') }}</span>
            <span class="cbp-topbar-dd-caret" aria-hidden="true">▼</span>
          </button>
          <div class="cbp-topbar-dd-panel cbp-modules-panel" role="menu">
            <a :href="homeHref" class="cbp-topbar-dd-home" role="menuitem">CBP HOME</a>
            <div class="cbp-topbar-dd-scope" role="tablist" aria-label="CBP module groups">
              <button
                type="button"
                role="tab"
                :class="{ 'is-active': moduleScope === 'operations' }"
                :aria-selected="moduleScope === 'operations'"
                @click.stop="moduleScope = 'operations'"
              >
                CBP Operations
                <span class="cbp-topbar-dd-count">{{ navLoading ? '…' : operationsCount }}</span>
              </button>
              <button
                type="button"
                role="tab"
                :class="{ 'is-active': moduleScope === 'programs' }"
                :aria-selected="moduleScope === 'programs'"
                @click.stop="moduleScope = 'programs'"
              >
                CBP Programs
                <span class="cbp-topbar-dd-count">{{ programCount }}</span>
              </button>
            </div>
            <template v-if="moduleScope === 'operations'">
              <template v-if="navLoading">
                <p class="cbp-topbar-dd-empty" role="status">{{ locale.t('chrome.loading_modules', 'Loading modules…') }}</p>
              </template>
              <template v-else-if="navError">
                <p class="cbp-topbar-dd-empty" role="alert">{{ navError }}</p>
              </template>
              <template v-else-if="systems.length > 0">
                <a
                  v-for="sys in systems"
                  :key="sys.module_key || sys.id || sys.href"
                  :href="sys.sso_launch ? '#' : (sys.href || '#')"
                  class="cbp-topbar-dd-item cbp-topbar-dd-item--with-icon"
                  :class="{ 'is-active': sys.is_active }"
                  role="menuitem"
                  :target="moduleTarget(sys)"
                  :rel="moduleRel(sys)"
                  @click="onModuleNav(sys, $event)"
                >
                  <i :class="moduleMenuIcon(sys.module_key || sys.id, sys.icon)" class="cbp-topbar-dd-module-icon" aria-hidden="true" />
                  <span class="cbp-topbar-dd-item-text">
                    <span class="cbp-topbar-dd-item-label">{{ sys.label }}</span>
                  </span>
                </a>
              </template>
              <p v-else class="cbp-topbar-dd-empty" role="status">
                {{ locale.t('chrome.no_systems', 'No other CBP systems are assigned to your account.') }}
              </p>
            </template>
            <template v-else>
              <template v-for="group in programGroups" :key="group.title">
                <p class="cbp-topbar-dd-section">{{ group.title }} <span class="cbp-topbar-dd-count">{{ group.items.length }}</span></p>
                <span
                  v-for="item in group.items"
                  :key="item.label"
                  class="cbp-topbar-dd-item cbp-topbar-dd-item--with-icon is-soon"
                >
                  <i :class="moduleMenuIcon(null, item.icon)" class="cbp-topbar-dd-module-icon" aria-hidden="true" />
                  <span class="cbp-topbar-dd-item-text">
                    <span class="cbp-topbar-dd-item-label">{{ item.label }}</span>
                  </span>
                </span>
              </template>
            </template>
          </div>
        </div>
        <slot name="extra" />
      </div>
      <div v-if="userName" ref="userDdRef" class="cbp-topbar-user-dd" :class="{ 'is-open': userOpen }">
        <button
          type="button"
          class="cbp-topbar-user-trigger"
          aria-haspopup="true"
          :aria-expanded="userOpen"
          @click.stop="toggleUser"
        >
          <CbpAvatar class="cbp-topbar-avatar" :name="userName" :image-url="avatarUrl" size="md" />
          <span class="cbp-topbar-user-name">{{ userName }}</span>
          <span class="cbp-topbar-dd-caret" aria-hidden="true">▼</span>
        </button>
        <div class="cbp-topbar-dd-panel cbp-topbar-user-panel" role="menu">
          <RouterLink
            to="/"
            class="cbp-topbar-dd-item"
            :class="{ 'is-active': route.path === '/' }"
            role="menuitem"
            @click="closeMenus"
          >
            <span class="cbp-topbar-dd-item-label">{{ locale.t('chrome.home', 'Home') }}</span>
            <span class="cbp-topbar-dd-item-sub">{{ locale.t('chrome.home_sub', 'Staff portal overview') }}</span>
          </RouterLink>
          <RouterLink
            to="/profile"
            class="cbp-topbar-dd-item"
            :class="{ 'is-active': route.path === '/profile' || route.path.startsWith('/profile/') }"
            role="menuitem"
            @click="closeMenus"
          >
            <span class="cbp-topbar-dd-item-label">{{ locale.t('chrome.profile', 'Profile') }}</span>
            <span class="cbp-topbar-dd-item-sub">{{ locale.t('chrome.profile_sub', 'Staff account') }}</span>
          </RouterLink>
          <RouterLink
            v-if="passwordLoginAvailable"
            to="/profile/password"
            class="cbp-topbar-dd-item"
            :class="{ 'is-active': route.path === '/profile/password' }"
            role="menuitem"
            @click="closeMenus"
          >
            <span class="cbp-topbar-dd-item-label">{{ locale.t('chrome.change_password', 'Change password') }}</span>
            <span class="cbp-topbar-dd-item-sub">{{ locale.t('chrome.change_password_sub', 'Email sign-in password') }}</span>
          </RouterLink>
          <button type="button" class="cbp-topbar-dd-item cbp-topbar-dd-logout" role="menuitem" @click="onLogout">
            <span class="cbp-topbar-dd-item-label">{{ locale.t('chrome.log_out', 'Log out') }}</span>
            <span class="cbp-topbar-dd-item-sub">{{ locale.t('chrome.log_out_sub', 'End session') }}</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
