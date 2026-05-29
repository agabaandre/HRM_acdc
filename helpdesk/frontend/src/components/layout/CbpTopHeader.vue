<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../common/CbpAvatar.vue'
import { fetchCbpModules, type CbpNavPayload } from '../../lib/cbpModules'
import { staffPortalBaseUrl, staffPortalProfileUrl } from '../../lib/sso'
import { useAuthStore } from '../../stores/auth'

defineProps<{
  userName: string | null
  avatarUrl?: string | null
  theme?: 'dark' | 'light'
}>()

const auth = useAuthStore()

const base = computed(() => staffPortalBaseUrl())
const profileUrl = computed(() => staffPortalProfileUrl())

const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref<string | null>(null)

const portalHome = computed(() => nav.value?.home?.href ?? `${base.value}/home/index`)
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
    nav.value = await fetchCbpModules()
  } catch (e) {
    navError.value = e instanceof Error ? e.message : 'Could not load CBP modules'
    nav.value = null
  } finally {
    navLoading.value = false
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

function moduleTarget(mod: { opens_in_new_tab?: boolean }): string | undefined {
  return mod.opens_in_new_tab ? '_blank' : undefined
}

function moduleRel(mod: { opens_in_new_tab?: boolean }): string | undefined {
  return mod.opens_in_new_tab ? 'noopener noreferrer' : undefined
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

function onLogoError(e: Event) {
  const img = e.target as HTMLImageElement | null
  if (img) {
    img.style.display = 'none'
  }
}
</script>

<template>
  <header class="cbp-topbar">
    <div class="cbp-topbar-inner">
      <RouterLink to="/" class="cbp-topbar-logo" title="IT Service Desk — Overview">
        <img
          :src="`${base}/assets/images/AU_CDC_Logo-800.png`"
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
            <a
              :href="portalHome"
              class="cbp-topbar-dd-primary"
              :class="{ 'is-active': nav?.home?.is_active }"
              role="menuitem"
              @click="closeMenus"
            >
              <span class="cbp-topbar-dd-primary-title">{{ portalHomeLabel }}</span>
            </a>
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
                :key="sys.id"
                :href="sys.href"
                class="cbp-topbar-dd-item cbp-topbar-dd-item--with-icon"
                :class="{ 'is-active': sys.is_active }"
                role="menuitem"
                :target="moduleTarget(sys)"
                :rel="moduleRel(sys)"
                @click="closeMenus"
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
          <a :href="profileUrl" class="cbp-topbar-dd-item" role="menuitem" @click="closeMenus">
            <span class="cbp-topbar-dd-item-label">Profile</span>
            <span class="cbp-topbar-dd-item-sub">Staff portal account</span>
          </a>
          <button type="button" class="cbp-topbar-dd-item cbp-topbar-dd-logout" role="menuitem" @click="onLogout">
            <span class="cbp-topbar-dd-item-label">Log out</span>
            <span class="cbp-topbar-dd-item-sub">Return to Staff portal</span>
          </button>
        </div>
      </div>
    </div>
  </header>
</template>
