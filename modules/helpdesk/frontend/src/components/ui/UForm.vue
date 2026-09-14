<script setup lang="ts" generic="T = Record<string, unknown>">
import { computed, provide, ref, useAttrs } from 'vue'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { formErrorsKey } from './formContext'

defineOptions({ inheritAttrs: false })

const props = withDefaults(
  defineProps<{
    state: T
    validate?: (state: T) => FormError[]
    disabled?: boolean
  }>(),
  {
    disabled: false,
  },
)

const emit = defineEmits<{
  submit: [event: FormSubmitEvent<T>]
  'validation-failed': [errors: FormError[]]
}>()

const attrs = useAttrs()
const fieldsetClass = computed(() => attrs.class)
const formId = computed(() => (typeof attrs.id === 'string' ? attrs.id : undefined))

const errors = ref<FormError[]>([])
provide(formErrorsKey, errors)

function onSubmit() {
  if (props.disabled) return
  errors.value = props.validate ? props.validate(props.state) : []
  if (errors.value.length === 0) {
    emit('submit', { data: props.state })
    return
  }
  emit('validation-failed', errors.value)
}

defineExpose({ submit: onSubmit })
</script>

<template>
  <v-form :id="formId" class="hd-v-form" @submit.prevent="onSubmit">
    <fieldset class="hd-v-form__fieldset" :class="fieldsetClass" :disabled="disabled">
      <slot />
    </fieldset>
  </v-form>
</template>

<style scoped>
.hd-v-form__fieldset {
  border: 0;
  margin: 0;
  padding: 0;
  min-width: 0;
  width: 100%;
}
</style>
