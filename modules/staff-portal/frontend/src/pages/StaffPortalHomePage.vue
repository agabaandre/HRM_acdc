<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import { PORTAL_NAV_ITEMS, type PortalNavItem } from '@/lib/portalNav'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const auth = useAuthStore()
const locale = useLocaleStore()

function navLabel(item: PortalNavItem): string {
  if (!item.i18nKey) return item.label
  return locale.t(`nav.${item.i18nKey}`, item.label)
}

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

const sections = computed(() => PORTAL_NAV_ITEMS.filter(canSee))
</script>

<template>
  <div>
    <PortalPageChrome
      icon="fa-solid fa-id-badge"
      title="Staff Portal"
      lede="Staff records, leave, payroll, performance, and the rest of the original staff portal."
    />
    <v-row>
      <v-col v-for="item in sections" :key="item.to" cols="12" sm="6" md="4">
        <RouterLink :to="item.to" class="staff-portal-link">
          <v-card variant="outlined" class="h-100" hover>
            <v-card-text class="d-flex align-center ga-3">
              <i v-if="item.icon" :class="[item.icon, 'text-h5 text-primary']" aria-hidden="true" />
              <div class="font-weight-medium">{{ navLabel(item) }}</div>
            </v-card-text>
          </v-card>
        </RouterLink>
      </v-col>
    </v-row>
  </div>
</template>

<style scoped>
.staff-portal-link {
  text-decoration: none;
  color: inherit;
  display: block;
  height: 100%;
}
</style>
