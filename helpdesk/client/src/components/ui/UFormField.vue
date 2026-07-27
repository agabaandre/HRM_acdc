<script setup lang="ts">
import { computed, inject, provide, ref, useAttrs } from 'vue'
import { fieldLabelKey, fieldRequiredKey, formErrorsKey } from './formContext'

const props = defineProps<{
  label?: string
  name?: string
  required?: boolean
  description?: string
  /**
   * When true, label renders above the control instead of on the Vuetify outline notch.
   * Project standard is floating outline labels (IT Assets style).
   * Use stacked only for non-Vuetify controls (rich text, custom radio grids, etc.).
   */
  stackedLabel?: boolean
}>()

const attrs = useAttrs()
const errors = inject(formErrorsKey, ref([]))
const fieldError = computed(() => errors.value.find((e) => e.name === props.name)?.message)

const isRichField = computed(() => String(attrs.class ?? '').includes('hd-rich-field'))

const useStackedLabel = computed(() => {
  if (props.stackedLabel !== undefined) return props.stackedLabel
  // Rich text editors are not Vuetify fields — keep a stacked label above them.
  return isRichField.value
})

const injectedLabel = computed(() => (useStackedLabel.value ? undefined : props.label))
const injectedRequired = computed(() => props.required ?? false)

provide(fieldLabelKey, injectedLabel)
provide(fieldRequiredKey, injectedRequired)
</script>

<template>
  <div class="hd-v-form-field" :class="$attrs.class">
    <div v-if="useStackedLabel && label" class="hd-v-form-field__label">
      {{ label }}
      <span v-if="required" class="hd-v-form-field__required">*</span>
    </div>
    <p v-if="description" class="hd-v-form-field__desc">{{ description }}</p>
    <slot />
    <div v-if="fieldError" class="hd-v-form-field__error">{{ fieldError }}</div>
  </div>
</template>

<style scoped>
.hd-v-form-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.hd-v-form-field__label {
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.25;
  color: rgb(var(--v-theme-on-surface));
}
.hd-v-form-field__required {
  color: rgb(var(--v-theme-error));
  margin-left: 0.15rem;
}
.hd-v-form-field__desc {
  margin: 0;
  font-size: 0.78rem;
  color: rgba(var(--v-theme-on-surface), 0.65);
}
.hd-v-form-field__error {
  font-size: 0.75rem;
  color: rgb(var(--v-theme-error));
}
</style>
