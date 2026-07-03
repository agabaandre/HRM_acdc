<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { formErrorsKey } from './formContext'

const props = defineProps<{
  label?: string
  name?: string
  required?: boolean
  description?: string
}>()

const errors = inject(formErrorsKey, ref([]))
const fieldError = computed(() => errors.value.find((e) => e.name === props.name)?.message)
</script>

<template>
  <div class="hd-v-form-field" :class="$attrs.class">
    <div v-if="label" class="hd-v-form-field__label">
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
