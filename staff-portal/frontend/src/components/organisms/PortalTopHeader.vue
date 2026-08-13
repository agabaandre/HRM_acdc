<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import CbpAvatar from '@cbp/common/CbpAvatar.vue'
import { fetchCbpModules, type CbpModuleLink, type CbpNavPayload } from '@/lib/cbpModules'
import { launchCbpModule, moduleLaunchKey } from '@/lib/cbpLaunch'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useAuthStore } from '@/stores/auth'

defineProps<{
  userName: string | null
  avatarUrl?: string | null
  theme?: 'dark' | 'light'
}>()

const auth = useAuthStore()
const route = useRoute()

const staffBase = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff`
  }
  return '/staff'
})

const logoUrl = computed(() => `${staffBase.value}/assets/images/AU_CDC_Logo-800.png`)
const passwordLoginAvailable = computed(() => auth.passwordLoginAvailable)

const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref<string | null>(null)

const portalHomeLabel = computed(() => nav.value?.home?.label ?? 'CBP Home')
const systems = computed(() => nav.value?.modules ?? [])

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
      exclude: 'staff_portal',
      path,
    })
    navError.value = null
  } catch (e) {
    nav.value = null
    navError.value = apiErrorMessage(e, 'Could not load CBP modules')
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
      <RouterLink to="/" class="cbp-topbar-logo" title="Staff Portal">
        <img
          :src="logoUrl"
          width="200"
          alt="Africa CDC"
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
            title="CBP Modules"
            @click.stop="togglePortal"
          >
            <i class="bx bx-category cbp-topbar-dd-icon" aria-hidden="true" />
            <span class="cbp-topbar-dd-toggle-label ms-2">CBP Modules</span>
            <span class="cbp-topbar-dd-caret" aria-hidden="true">▼</span>
          </button>
          <div class="cbp-topbar-dd-panel" role="menu">
            <RouterLink
              to="/"
              class="cbp-topbar-dd-primary"
              :class="{ 'is-active': route.path === '/' || nav?.home?.is_active }"
              role="menuitem"
              @click="closeMenus"
            >
              <span class="cbp-topbar-dd-primary-title">{{ portalHomeLabel }}</span>
            </RouterLink>
            <template v-if="navLoading">
              <p class="cbp-topbar-dd-empty" role="status">Loading modules…</p>
            </template>
            <template v-else-if="navError">
              <p class="cbp-topbar-dd-empty" role="alert">{{ navError }}</p>
            </template>
            <template v-else-if="systems.length > 0">
              <p class="cbp-topbar-dd-section">Systems</p>
              <a
                v-for="sys in systems"
                :key="sys.module_key || sys.id || sys.href"
                :href="sys.href || '#'"
                class="cbp-topbar-dd-item cbp-topbar-dd-item--with-icon"
                :class="{ 'is-active': sys.is_active }"
                role="menuitem"
                :target="moduleTarget(sys)"
                :rel="moduleRel(sys)"
                @click="onModuleNav(sys, $event)"
              >
                <i
                  v-if="sys.icon"
                  :class="sys.icon"
                  class="cbp-topbar-dd-module-icon"
                  aria-hidden="true"
                />
                <span class="cbp-topbar-dd-item-text">
                  <span class="cbp-topbar-dd-item-label">{{ sys.label }}</span>
                </span>
              </a>
            </template>
            <p v-else class="cbp-topbar-dd-empty" role="status">
              No other CBP systems are assigned to your account.
            </p>
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
            <span class="cbp-topbar-dd-item-label">Home</span>
            <span class="cbp-topbar-dd-item-sub">Staff portal overview</span>
          </RouterLink>
          <RouterLink
            to="/profile"
            class="cbp-topbar-dd-item"
            :class="{ 'is-active': route.path === '/profile' || route.path.startsWith('/profile/') }"
            role="menuitem"
            @click="closeMenus"
          >
            <span class="cbp-topbar-dd-item-label">Profile</span>
            <span class="cbp-topbar-dd-item-sub">Staff account</span>
          </RouterLink>
          <RouterLink
            v-if="passwordLoginAvailable"
            to="/profile/password"
            class="cbp-topbar-dd-item"
            :class="{ 'is-active': route.path === '/profile/password' }"
            role="menuitem"
            @click="closeMenus"
          >
            <span class="cbp-topbar-dd-item-label">Change password</span>
            <span class="cbp-topbar-dd-item-sub">Email sign-in password</span>
          </RouterLink>
          <button type="button" class="cbp-topbar-dd-item cbp-topbar-dd-logout" role="menuitem" @click="onLogout">
            <span class="cbp-topbar-dd-item-label">Log out</span>
            <span class="cbp-topbar-dd-item-sub">End session</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
