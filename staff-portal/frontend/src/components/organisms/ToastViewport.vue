<script setup lang="ts">
import { storeToRefs } from 'pinia'
import ToastAlert from '@/components/molecules/ToastAlert.vue'
import { useToastStore } from '@/features/toast'

const store = useToastStore()
const { items } = storeToRefs(store)
</script>

<template>
  <div
    v-if="items.length"
    id="customToastContainer"
    class="toast-viewport"
    aria-label="Notifications"
  >
    <ToastAlert
      v-for="item in items"
      :key="item.id"
      :toast="item"
      @dismiss="store.dismiss"
    />
  </div>
</template>
