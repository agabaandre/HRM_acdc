<script setup lang="ts">
import { computed } from 'vue'
import { mapLucideIcon } from './iconMap'

const model = defineModel<string | number | null>({ default: '' })

const props = withDefaults(
  defineProps<{
    type?: string
    placeholder?: string
    disabled?: boolean
    min?: number | string
    maxlength?: number | string
    icon?: string
    autocomplete?: string
    ariaLabel?: string
    size?: string
  }>(),
  {
    type: 'text',
    disabled: false,
  },
)

const prependIcon = computed(() => mapLucideIcon(props.icon))

const inputType = computed(() => {
  if (props.type === 'search') return 'text'
  return props.type
})

const density = computed(() => (props.size === 'lg' ? 'default' : 'compact'))
</script>

<template>
  <v-text-field
    v-model="model"
    :type="inputType"
    :placeholder="placeholder"
    :disabled="disabled"
    :min="min"
    :maxlength="maxlength"
    :prepend-inner-icon="prependIcon"
    :autocomplete="autocomplete"
    :density="density"
    :aria-label="ariaLabel ?? ($attrs['aria-label'] as string | undefined)"
    class="hd-v-input w-full"
    v-bind="$attrs"
  />
</template>

<style scoped>
.w-full {
  width: 100%;
}
</style>
