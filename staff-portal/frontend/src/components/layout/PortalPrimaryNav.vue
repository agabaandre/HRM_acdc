<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { PORTAL_NAV_ITEMS } from '../../lib/portalNav'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const navOpen = ref(false)

const items = computed(() =>
  PORTAL_NAV_ITEMS.filter((item) => {
    if (!item.permission) {
      return true
    }
    return auth.hasPermission(item.permission)
  }),
)

function isActive(item: (typeof PORTAL_NAV_ITEMS)[number]): boolean {
  const paths = item.match ?? [item.to]
  return paths.some((p) => (p === '/' ? route.path === '/' : route.path.startsWith(p)))
}

function closeNav() {
  navOpen.value = false
}

function toggleNav() {
  navOpen.value = !navOpen.value
}

watch(
  () => route.fullPath,
  () => closeNav(),
)

function onDocClick(e: MouseEvent) {
  const el = document.querySelector('.portal-primary-nav')
  const t = e.target as Node
  if (el && !el.contains(t)) {
    closeNav()
  }
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <nav class="cbp-primary-nav portal-primary-nav" aria-label="Staff portal">
    <div class="cbp-primary-nav__inner">
      <button type="button" class="cbp-primary-nav__toggle d-lg-none" @click="toggleNav">
        <i class="fa-solid fa-bars" aria-hidden="true" />
        <span class="visually-hidden">Menu</span>
      </button>
      <ul class="cbp-primary-nav__list" :class="{ 'is-open': navOpen }">
        <li v-for="item in items" :key="item.to">
          <RouterLink
            :to="item.to"
            class="cbp-primary-nav__link"
            :class="{ active: isActive(item) }"
          >
            {{ item.label }}
          </RouterLink>
        </li>
      </ul>
    </div>
  </nav>
</template>
