<script setup lang="ts">
defineProps<{ title: string }>()
</script>

<template>
  <v-card elevation="10" class="withbg mb-4">
    <v-card-item class="pa-0">
      <div class="d-sm-flex align-center justify-space-between">
        <h5 class="text-h5 mb-0 pl-7 pt-7 pb-4">{{ title }}</h5>
        <div class="pr-4 pt-4">
          <slot name="action" />
        </div>
      </div>
      <div class="px-7 pb-7">
        <slot />
      </div>
    </v-card-item>
  </v-card>
</template>
