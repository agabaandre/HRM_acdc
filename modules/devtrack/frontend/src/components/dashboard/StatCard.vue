<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    value: number | string
    icon: string
    color?: string
    subtitle?: string
    active?: boolean
    clickable?: boolean
  }>(),
  {
    clickable: false,
    active: false,
  },
)

defineEmits<{
  (e: 'click'): void
}>()
</script>

<template>
  <v-card
    elevation="10"
    class="withbg stat-card h-100"
    :class="{ 'stat-card--active': active, 'stat-card--clickable': clickable }"
    :role="clickable ? 'button' : undefined"
    :tabindex="clickable ? 0 : undefined"
    @click="clickable ? $emit('click') : undefined"
    @keydown.enter.prevent="clickable ? $emit('click') : undefined"
  >
    <v-card-text class="d-flex align-center justify-space-between">
      <div>
        <div class="text-subtitle-2 text-medium-emphasis mb-1">{{ title }}</div>
        <div class="text-h3 font-weight-semibold mb-0">{{ value }}</div>
        <div v-if="subtitle" class="text-caption text-medium-emphasis mt-1">{{ subtitle }}</div>
      </div>
      <v-avatar :color="color ?? 'primary'" variant="tonal" size="56" rounded="lg">
        <v-icon :icon="icon" size="28" />
      </v-avatar>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.stat-card {
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.stat-card--clickable {
  cursor: pointer;
}

.stat-card--clickable:hover {
  transform: translateY(-2px);
}

.stat-card--active {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 0;
}
</style>
