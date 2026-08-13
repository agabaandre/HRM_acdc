<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { PORTAL_NAV_ITEMS, isNavItemActive, type PortalNavItem } from '@/lib/portalNav'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const navOpen = ref(false)
const moreOpen = ref(false)

function canSee(item: PortalNavItem): boolean {
  if (item.module && !auth.isModuleEnabled(item.module)) return false
  const isHr = !!auth.me?.profile?.is_hr || auth.me?.profile?.role_id === 20
  const hasStaff = Number(auth.me?.profile?.staff_id || 0) > 0
  if (item.to === '/leave' && (isHr || hasStaff)) return true
  if (item.anyPermission?.length) {
    return item.anyPermission.some((p) => auth.hasPermission(p))
  }
  if (!item.permission) return true
  return auth.hasPermission(item.permission)
}

const primaryItems = computed(() =>
  PORTAL_NAV_ITEMS.filter((item) => (item.group ?? 'primary') === 'primary' && canSee(item)),
)

const moreItems = computed(() =>
  PORTAL_NAV_ITEMS.filter((item) => item.group === 'more' && canSee(item)),
)

const moreActive = computed(() => moreItems.value.some((item) => isNavItemActive(item, route.path)))

function closeAll() {
  navOpen.value = false
  moreOpen.value = false
}

function toggleNav() {
  navOpen.value = !navOpen.value
  if (navOpen.value) moreOpen.value = false
}

function toggleMore() {
  moreOpen.value = !moreOpen.value
}

watch(
  () => route.fullPath,
  () => closeAll(),
)

function onDocClick(e: MouseEvent) {
  const t = e.target as Node
  if (!(t instanceof Node)) return
  const el = document.querySelector('.cbp-primary-nav')
  if (el && !el.contains(t)) closeAll()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <nav class="cbp-primary-nav" aria-label="Primary">
    <div class="cbp-primary-nav-inner">
      <button type="button" class="cbp-nav-toggle" aria-label="Toggle menu" @click.stop="toggleNav">
        <i class="bx bx-menu" aria-hidden="true" />
      </button>
      <div class="cbp-nav-links" :class="{ 'is-open': navOpen }">
        <template v-if="auth.isAuthenticated">
          <RouterLink
            v-for="item in primaryItems"
            :key="item.to"
            :to="item.to"
            class="cbp-nav-link"
            :class="{ 'router-link-active': isNavItemActive(item, route.path) }"
            @click="closeAll"
          >
            <i v-if="item.icon" :class="[item.icon, 'cbp-nav-link-icon']" aria-hidden="true" />
            <span>{{ item.label }}</span>
          </RouterLink>

          <div
            v-if="moreItems.length"
            class="cbp-nav-item-dropdown"
            :class="{ 'is-open': moreOpen }"
          >
            <button
              type="button"
              class="cbp-nav-link cbp-nav-dd-toggle"
              :class="{ 'router-link-active': moreActive }"
              aria-haspopup="true"
              :aria-expanded="moreOpen"
              aria-label="More navigation"
              @click.stop="toggleMore"
            >
              <i class="fa-solid fa-ellipsis cbp-nav-link-icon" aria-hidden="true" />
              <span>More</span>
              <span class="cbp-nav-dd-caret" aria-hidden="true">▼</span>
            </button>
            <div class="cbp-nav-dd-menu" role="menu">
              <RouterLink
                v-for="item in moreItems"
                :key="item.to"
                :to="item.to"
                class="cbp-nav-dd-item"
                role="menuitem"
                :class="{ 'router-link-active': isNavItemActive(item, route.path) }"
                @click="closeAll"
              >
                <i v-if="item.icon" :class="[item.icon, 'cbp-nav-dd-item-icon']" aria-hidden="true" />
                <span>{{ item.label }}</span>
              </RouterLink>
            </div>
          </div>
        </template>
      </div>
    </div>
  </nav>
</template>
