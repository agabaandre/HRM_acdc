<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { sidebarItems, type SidebarItem } from '@/config/sidebar'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()
const openMenu = ref('')

function visible(items: SidebarItem[]): SidebarItem[] {
  const isAdmin = !!auth.user?.is_admin
  return items.filter((item) => !item.adminOnly || isAdmin)
}

const sections = computed(() => {
  const groups: { header: string; items: SidebarItem[] }[] = []
  let current = { header: 'Delivery', items: [] as SidebarItem[] }
  for (const item of sidebarItems) {
    if (item.header) {
      if (current.items.length || current.header !== 'Delivery') groups.push(current)
      current = { header: item.header, items: [] }
      continue
    }
    current.items.push(item)
  }
  groups.push(current)
  return groups
    .map((group) => ({ ...group, items: visible(group.items).filter((item) => item.to) }))
    .filter((group) => group.items.length > 0)
})

const delivery = computed(() => sections.value.find((group) => group.header === 'Delivery')?.items ?? [])
const menus = computed(() => sections.value.filter((group) => group.header !== 'Delivery'))

function isActive(item: SidebarItem): boolean {
  return typeof item.to === 'object' && item.to.name === route.name
}

function menuActive(items: SidebarItem[]): boolean {
  return items.some((item) => isActive(item))
}

function toggleMenu(header: string) {
  openMenu.value = openMenu.value === header ? '' : header
}

function closeMenu() {
  openMenu.value = ''
}

function onDocClick(event: MouseEvent) {
  const target = event.target
  if (!(target instanceof Node)) return
  const nav = document.querySelector('.devtrack-topnav')
  if (nav && !nav.contains(target)) closeMenu()
}

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <nav class="devtrack-topnav" aria-label="DevTrack">
    <RouterLink
      v-for="item in delivery"
      :key="item.title"
      class="devtrack-topnav-link"
      :class="{ 'is-active': isActive(item) }"
      :to="item.to!"
    >
      <v-icon :icon="item.icon" size="20" />
      <span>{{ item.title }}</span>
    </RouterLink>

    <div v-for="menu in menus" :key="menu.header" class="devtrack-topnav-drop" :class="{ 'is-open': openMenu === menu.header }">
      <button
        type="button"
        class="devtrack-topnav-link"
        :class="{ 'is-active': menuActive(menu.items) || openMenu === menu.header }"
        @click.stop="toggleMenu(menu.header)"
      >
        <span>{{ menu.header }}</span>
        <span class="devtrack-topnav-caret" aria-hidden="true">▼</span>
      </button>
      <div class="devtrack-topnav-menu" role="menu">
        <RouterLink
          v-for="item in menu.items"
          :key="item.title"
          class="devtrack-topnav-item"
          :to="item.to!"
          @click="closeMenu"
        >
          {{ item.title }}
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.devtrack-topnav {
  display: flex;
  flex-wrap: wrap;
  align-items: stretch;
  gap: 0.15rem;
  width: 100%;
  min-height: 60px;
  padding: 0 24px;
  background: #2c3e50;
  border-bottom: 1px solid rgba(17, 154, 72, 0.2);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.devtrack-topnav-link {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.8);
  font: inherit;
  font-size: 12px;
  font-weight: 500;
  line-height: 1.2;
  padding: 0 1rem;
  min-height: 60px;
  text-decoration: none;
  cursor: pointer;
  border-radius: 4px;
  white-space: nowrap;
}

.devtrack-topnav-link:hover,
.devtrack-topnav-link.is-active {
  color: #fff;
  background: rgba(17, 154, 72, 0.2);
}

.devtrack-topnav-link :deep(.v-icon) {
  color: inherit;
  opacity: 0.92;
}

.devtrack-topnav-caret {
  font-size: 0.65rem;
  opacity: 0.85;
}

.devtrack-topnav-drop {
  position: relative;
}

.devtrack-topnav-menu {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  min-width: 220px;
  background: #fff;
  border: 1px solid #e9ecef;
  border-radius: 4px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  padding: 0.35rem 0;
  z-index: 30;
}

.devtrack-topnav-drop.is-open .devtrack-topnav-menu {
  display: block;
}

.devtrack-topnav-item {
  display: block;
  padding: 0.55rem 1rem;
  color: #2c3e50;
  font-size: 12px;
  font-weight: 500;
  text-decoration: none;
}

.devtrack-topnav-item:hover {
  background: #e8f5ee;
  color: #0d7a3a;
}
</style>
