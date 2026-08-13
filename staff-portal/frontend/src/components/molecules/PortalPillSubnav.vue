<script setup lang="ts">
import { RouterLink } from 'vue-router'

export type PortalPillNavItem = {
  key: string
  label: string
  icon?: string
  /** Route location for link-style pills */
  to?: string | Record<string, unknown>
  active?: boolean
  badge?: string | number | null
  disabled?: boolean
}

defineProps<{
  items: PortalPillNavItem[]
  ariaLabel?: string
}>()

const emit = defineEmits<{
  select: [key: string]
}>()
</script>

<template>
  <nav class="portal-pill-subnav" :aria-label="ariaLabel || 'Section navigation'">
    <component
      :is="item.to ? RouterLink : 'button'"
      v-for="item in items"
      :key="item.key"
      v-bind="item.to ? { to: item.to } : { type: 'button' }"
      class="portal-pill-subnav__btn"
      :class="{ 'is-active': item.active, 'is-disabled': item.disabled }"
      :aria-current="item.active ? 'page' : undefined"
      :disabled="!item.to && item.disabled"
      @click="!item.to && !item.disabled && emit('select', item.key)"
    >
      <i v-if="item.icon" :class="[item.icon, 'portal-pill-subnav__icon']" aria-hidden="true" />
      <span>{{ item.label }}</span>
      <span v-if="item.badge != null && item.badge !== ''" class="portal-pill-subnav__badge">
        {{ item.badge }}
      </span>
    </component>
  </nav>
</template>

<style scoped>
.portal-pill-subnav {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  padding: 0.35rem;
  border-radius: 0.65rem;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(58, 71, 82, 0.1);
  box-shadow: 0 1px 2px rgba(58, 71, 82, 0.04);
  width: fit-content;
  max-width: 100%;
}

.portal-pill-subnav__btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 2.15rem;
  padding: 0.35rem 0.85rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: #3a4752 !important;
  font: inherit;
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  text-decoration: none !important;
  cursor: pointer;
  transition:
    background 0.15s ease,
    color 0.15s ease;
}

.portal-pill-subnav__btn:hover:not(.is-active):not(.is-disabled) {
  background: rgba(58, 71, 82, 0.06);
  color: #3a4752 !important;
}

.portal-pill-subnav__btn.is-active,
a.portal-pill-subnav__btn.is-active,
a.portal-pill-subnav__btn.is-active:visited,
a.portal-pill-subnav__btn.is-active:hover {
  background: #119a48 !important;
  color: #ffffff !important;
}

.portal-pill-subnav__btn.is-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.portal-pill-subnav__icon {
  font-size: 0.82rem;
  opacity: 0.9;
  color: inherit !important;
}

.portal-pill-subnav__btn.is-active .portal-pill-subnav__icon {
  color: #ffffff !important;
  opacity: 1;
}

.portal-pill-subnav__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.15rem;
  height: 1.15rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  font-size: 0.68rem;
  font-weight: 700;
  background: rgba(58, 71, 82, 0.12);
  color: inherit !important;
}

.portal-pill-subnav__btn.is-active .portal-pill-subnav__badge {
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff !important;
}
</style>
