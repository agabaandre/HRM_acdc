<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import CbpAvatar from '../common/CbpAvatar.vue'
import { staffPortalBaseUrl, staffPortalHomeUrl } from '../../lib/sso'

defineProps<{
  userName: string | null
  userSubtitle?: string | null
  avatarUrl?: string | null
}>()

const base = computed(() => staffPortalBaseUrl())
const portalHome = computed(() => staffPortalHomeUrl())
</script>

<template>
  <header class="cbp-topbar">
    <div class="cbp-topbar-inner">
      <RouterLink to="/" class="cbp-topbar-logo" title="IT Service Desk — Overview">
        <img
          :src="`${base}/assets/images/AU_CDC_Logo-800.png`"
          width="200"
          alt="Africa CDC"
          @error="($event.target as HTMLImageElement).style.display = 'none'"
        />
      </RouterLink>
      <div class="cbp-topbar-spacer" />
      <div class="cbp-topbar-menu">
        <a :href="portalHome" target="_self">Staff portal</a>
        <slot name="extra" />
      </div>
      <div v-if="userName" class="cbp-topbar-user">
        <CbpAvatar class="cbp-topbar-avatar" :name="userName" :image-url="avatarUrl" size="md" />
        <div class="cbp-topbar-user-text">
          <span class="cbp-topbar-user-name">{{ userName }}</span>
          <span v-if="userSubtitle" class="cbp-topbar-user-role">{{ userSubtitle }}</span>
        </div>
      </div>
    </div>
  </header>
</template>
