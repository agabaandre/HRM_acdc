<script setup lang="ts">
defineProps<{
  label: string
  required?: boolean
}>()
</script>

<template>
  <div class="form-field mb-4">
    <v-label class="font-weight-medium mb-1 d-block">
      {{ label }}
      <span v-if="required" class="text-error">*</span>
    </v-label>
    <slot />
  </div>
</template>
