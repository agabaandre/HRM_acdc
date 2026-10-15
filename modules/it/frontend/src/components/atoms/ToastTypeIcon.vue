<script setup lang="ts">
import type { ToastType } from '@/features/toast/types'
import { TOAST_TYPE_COLORS } from '@/features/toast/constants'

const props = defineProps<{
  type: ToastType
}>()

const ICONS: Record<ToastType, string> = {
  success:
    'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM241 337l-17 17-17-17-80-80L161 223l63 63L351 159 385 193 241 337z',
  error:
    'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm97.9-320l-17 17-47 47 47 47 17 17L320 353.9l-17-17-47-47-47 47-17 17L158.1 320l17-17 47-47-47-47-17-17L192 158.1l17 17 47 47 47-47 17-17L353.9 192z',
  info: 'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24V272H216 192V224h24 48 24v24 88h8 24v48H296 216 192V336h24zm72-144H224V128h64v64z',
  warning:
    'M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zm24-384v24V264v24H232V264 152 128h48zM232 368V320h48v48H232z',
}
</script>

<template>
  <span class="toast-icon" :style="{ color: TOAST_TYPE_COLORS[props.type] }" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
      <path fill="currentColor" :d="ICONS[props.type]" />
    </svg>
  </span>
</template>
