<script setup lang="ts">
import ToastCloseButton from '@/components/atoms/ToastCloseButton.vue'
import ToastTypeIcon from '@/components/atoms/ToastTypeIcon.vue'
import type { ToastRecord } from '@/features/toast/types'

defineProps<{
  toast: ToastRecord
}>()

const emit = defineEmits<{
  dismiss: [id: string]
}>()
</script>

<template>
  <div
    role="status"
    aria-live="polite"
    class="toast-item"
    :class="[toast.type, { show: toast.visible }]"
  >
    <ToastTypeIcon :type="toast.type" />
    <div class="toast-content">
      <p class="toast-title">{{ toast.title }}</p>
      <p class="toast-message">{{ toast.message }}</p>
    </div>
    <ToastCloseButton @click="emit('dismiss', toast.id)" />
  </div>
</template>
