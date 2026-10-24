<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { fetchCbpModules, type CbpModuleLink, type CbpNavPayload } from '@/lib/cbpModules'
import { CBP_PROGRAM_GROUPS, CBP_PROGRAM_KEYS, moduleMenuIcon } from '@/lib/cbpProgramMenu'
import { launchCbpModule, moduleLaunchKey } from '@/lib/cbpLaunch'
import { staffWebRoot } from '@/lib/staffWebRoot'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

defineProps<{
  userName: string | null
  avatarUrl?: string | null
  theme?: 'dark' | 'light'
}>()

const auth = useAuthStore()
const locale = useLocaleStore()
const route = useRoute()

const staffBase = computed(() => staffWebRoot())

const logoUrl = computed(() => `${staffBase.value}/assets/images/AU_CDC_Logo-800.png`)
const passwordLoginAvailable = computed(() => auth.passwordLoginAvailable)

const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref<string | null>(null)

const systems = computed(() => nav.value?.modules ?? [])

function homeAreaOf(mod: CbpModuleLink): 'operations' | 'programs' | 'configuration' {
  const area = (mod.home_area || '').trim()
  if (area === 'operations' || area === 'programs' || area === 'configuration') return area
  const key = (mod.module_key || mod.id || '').trim()
  return CBP_PROGRAM_KEYS.has(key) ? 'programs' : 'operations'
}

const operationSystems = computed(() => systems.value.filter((mod) => homeAreaOf(mod) === 'operations'))
const programGroups = computed(() => {
  const order = CBP_PROGRAM_GROUPS.map((group) => group.title)
  const catalog = new Map<string, { label: string; icon: string; title: string }>()
  for (const group of CBP_PROGRAM_GROUPS) {
    for (const item of group.items) {
      catalog.set(item.moduleKey, { label: item.label, icon: item.icon, title: group.title })
    }
  }
  const byTitle = new Map<string, { moduleKey: string; label: string; icon: string; live: CbpModuleLink }[]>()
  for (const mod of systems.value) {
    if (homeAreaOf(mod) !== 'programs') continue
    const key = (mod.module_key || mod.id || '').trim()
    if (!key) continue
    const known = catalog.get(key)
    const title = (mod.primary_tab || '').trim() || known?.title || 'Programs'
    const list = byTitle.get(title) || []
    list.push({
      moduleKey: key,
      label: known?.label || mod.label,
      icon: known?.icon || mod.icon || 'fa-th',
      live: mod,
    })
    byTitle.set(title, list)
  }
  const titles = [
    ...order.filter((title) => byTitle.has(title)),
    ...[...byTitle.keys()].filter((title) => !order.includes(title)),
  ]
  return titles.map((title) => ({ title, items: byTitle.get(title) || [] }))
})
const moduleScope = ref<'operations' | 'programs'>('operations')
const programCount = computed(() => programGroups.value.reduce((sum, group) => sum + group.items.length, 0))
const operationsCount = computed(() => operationSystems.value.length)

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
      path,
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
  if (img) {
    img.style.display = 'none'
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  void loadCbpModules()
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
      <RouterLink to="/" class="cbp-topbar-logo" :title="locale.t('chrome.staff_portal', 'Staff Portal')">
        <img
          :src="logoUrl"
          width="200"
          :alt="locale.t('chrome.africa_cdc', 'Africa CDC')"
          @error="onLogoError"
        />
      </RouterLink>
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
            <RouterLink to="/" class="cbp-topbar-dd-home" role="menuitem" @click="portalOpen = false">
              CBP HOME
            </RouterLink>
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
              <template v-else-if="operationSystems.length > 0">
                <a
                  v-for="sys in operationSystems"
                  :key="sys.module_key || sys.id || sys.href"
                  :href="sys.href || '#'"
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
              <p v-if="programGroups.length === 0" class="cbp-topbar-dd-empty" role="status">
                No programs are available.
              </p>
              <template v-for="group in programGroups" :key="group.title">
                <p class="cbp-topbar-dd-section">{{ group.title }} <span class="cbp-topbar-dd-count">{{ group.items.length }}</span></p>
                <a
                  v-for="item in group.items"
                  :key="item.moduleKey"
                  :href="item.live?.href || '#'"
                  class="cbp-topbar-dd-item cbp-topbar-dd-item--with-icon"
                  role="menuitem"
                  @click="item.live && onModuleNav(item.live, $event)"
                >
                  <i :class="moduleMenuIcon(item.moduleKey, item.icon)" class="cbp-topbar-dd-module-icon" aria-hidden="true" />
                  <span class="cbp-topbar-dd-item-text">
                    <span class="cbp-topbar-dd-item-label">{{ item.label }}</span>
                  </span>
                </a>
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
