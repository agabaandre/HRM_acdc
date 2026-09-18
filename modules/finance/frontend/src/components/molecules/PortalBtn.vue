<script setup lang="ts">
/**
 * Primary action button molecule.
 * Defaults to filled (flat) so it stays visible inside v-card-actions,
 * which Vuetify otherwise defaults to variant="text".
 */
withDefaults(
  defineProps<{
    color?: string
    variant?: 'flat' | 'tonal' | 'outlined' | 'text' | 'plain' | 'elevated'
    size?: string
    type?: 'button' | 'submit' | 'reset'
    loading?: boolean
    disabled?: boolean
    block?: boolean
    prependIcon?: string
    appendIcon?: string
    to?: string | Record<string, unknown>
    href?: string
  }>(),
  {
    color: 'primary',
    variant: 'flat',
    size: 'default',
    type: 'button',
    loading: false,
    disabled: false,
    block: false,
  },
)
</script>

<template>
  <v-btn
    class="portal-btn"
    :class="{ 'portal-btn--filled-primary': color === 'primary' && (variant === 'flat' || variant === 'elevated') }"
    :color="color"
    :variant="variant"
    :size="size"
    :type="type"
    :loading="loading"
    :disabled="disabled"
    :block="block"
    :prepend-icon="prependIcon"
    :append-icon="appendIcon"
    :to="to"
    :href="href"
  >
    <slot />
  </v-btn>
</template>

<style scoped>
.portal-btn {
  text-transform: none;
  letter-spacing: 0.01em;
  font-weight: 600;
}
.portal-btn--filled-primary {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: #fff !important;
  border-color: transparent !important;
}
</style>
