<script setup lang="ts">
import { RouterView } from 'vue-router'
import CbpTopHeader from './CbpTopHeader.vue'
import CbpThemeSwitch from './CbpThemeSwitch.vue'
import CbpPrimaryNav from './CbpPrimaryNav.vue'
import CbpPageFooter from './CbpPageFooter.vue'
import { routePreloaderVisible } from '../../lib/appPreloader'

defineProps<{
  displayName: string | null
  avatarUrl: string | null
  theme: 'light' | 'dark'
}>()

defineEmits<{
  'update:theme': [value: 'light' | 'dark']
}>()
</script>

<template>
  <div class="mp-helpdesk-shell cbp-wrapper">
    <CbpTopHeader
      :user-name="displayName"
      :avatar-url="avatarUrl"
      :theme="theme"
    >
      <template v-if="displayName" #extra>
        <CbpThemeSwitch :theme="theme" @update:theme="$emit('update:theme', $event)" />
      </template>
    </CbpTopHeader>

    <CbpPrimaryNav />

    <div class="cbp-page-wrapper">
      <div
        class="cbp-page-content hd-content-frame mp-content-frame"
        :class="{ 'hd-content-loading': routePreloaderVisible }"
      >
        <div class="hd-content-frame__body mp-max-width">
          <RouterView />
        </div>
      </div>
    </div>

    <CbpPageFooter />
  </div>
</template>
