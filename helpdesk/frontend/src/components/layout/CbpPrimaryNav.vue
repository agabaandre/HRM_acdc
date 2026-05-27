<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { SETTINGS_NAV_DROPDOWN_ITEMS } from '../../settings/settingsSections'

const auth = useAuthStore()
const route = useRoute()
const navOpen = ref(false)
const settingsOpen = ref(false)

const isAdmin = computed(() => auth.me?.profile?.role === 'admin')
const staffRole = computed(() => auth.me?.profile?.role ?? '')

const showDesk = computed(() => ['agent', 'supervisor', 'admin', 'auditor'].includes(staffRole.value))
const canManageKb = computed(() => isAdmin.value || !!auth.me?.profile?.can_manage_kb)

function closeAll() {
  navOpen.value = false
  settingsOpen.value = false
}

function toggleNav() {
  navOpen.value = !navOpen.value
  if (navOpen.value) {
    settingsOpen.value = false
  }
}

function toggleSettings() {
  settingsOpen.value = !settingsOpen.value
}

watch(
  () => route.fullPath,
  () => {
    closeAll()
  },
)

function onDocClick(e: MouseEvent) {
  const t = e.target as Node
  if (!(t instanceof Node)) {
    return
  }
  const el = document.querySelector('.cbp-primary-nav')
  if (el && !el.contains(t)) {
    closeAll()
  }
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})

const settingsAreaActive = computed(() => route.path.startsWith('/settings'))
</script>

<template>
  <nav class="cbp-primary-nav" aria-label="Primary">
    <div class="cbp-primary-nav-inner">
      <button type="button" class="cbp-nav-toggle" aria-label="Toggle menu" @click.stop="toggleNav">☰</button>
      <div class="cbp-nav-links" :class="{ 'is-open': navOpen }">
        <template v-if="auth.isAuthenticated">
          <RouterLink to="/" class="cbp-nav-link" @click="closeAll">Overview</RouterLink>
          <RouterLink to="/tickets" class="cbp-nav-link" @click="closeAll">Tickets</RouterLink>
          <RouterLink to="/tickets/new" class="cbp-nav-link" @click="closeAll">New request</RouterLink>
          <RouterLink v-if="showDesk" to="/desk/agent" class="cbp-nav-link" @click="closeAll">Agent desk</RouterLink>
          <RouterLink v-if="canManageKb" to="/knowledge-base/manage" class="cbp-nav-link" @click="closeAll">Knowledge base</RouterLink>
          <RouterLink to="/reports" class="cbp-nav-link" @click="closeAll">Reports</RouterLink>

          <div v-if="isAdmin" class="cbp-nav-item-dropdown" :class="{ 'is-open': settingsOpen }">
            <button
              type="button"
              class="cbp-nav-link cbp-nav-dd-toggle"
              :class="{ 'router-link-active': settingsAreaActive }"
              aria-haspopup="true"
              :aria-expanded="settingsOpen"
              @click.stop="toggleSettings"
            >
              Settings<span class="cbp-nav-dd-caret">▼</span>
            </button>
            <div class="cbp-nav-dd-menu" role="menu">
              <RouterLink
                v-for="item in SETTINGS_NAV_DROPDOWN_ITEMS"
                :key="item.path"
                :to="item.path"
                class="cbp-nav-dd-item"
                role="menuitem"
                @click="closeAll"
              >
                {{ item.label }}
              </RouterLink>
            </div>
          </div>
        </template>
      </div>
    </div>
  </nav>
</template>
