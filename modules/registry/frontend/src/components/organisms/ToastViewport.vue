<script setup lang="ts">
import { storeToRefs } from 'pinia'
import ToastAlert from '@/components/molecules/ToastAlert.vue'
import { useToastStore } from '@/features/toast'
import { useLocaleStore } from '@/stores/locale'

const store = useToastStore()
const locale = useLocaleStore()
const { items } = storeToRefs(store)
</script>

<template>
  <div
    v-if="items.length"
    id="customToastContainer"
    class="toast-viewport"
    :aria-label="locale.t('chrome.notifications', 'Notifications')"
  >
    <ToastAlert
      v-for="item in items"
      :key="item.id"
      :toast="item"
      @dismiss="store.dismiss"
    />
  </div>
</template>
