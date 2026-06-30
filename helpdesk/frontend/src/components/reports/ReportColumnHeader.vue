<script setup lang="ts">
const value = defineModel<string>({ default: '' })

withDefaults(
  defineProps<{
    title: string
    placeholder?: string
    ariaLabel: string
  }>(),
  {
    placeholder: 'Search…',
  },
)

const emit = defineEmits<{ filter: [] }>()

let timer: ReturnType<typeof setTimeout> | undefined

function onInput() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => emit('filter'), 350)
}

function onEnter() {
  if (timer) clearTimeout(timer)
  emit('filter')
}
</script>

<template>
  <div class="hd-dt-col-head">
    <span class="hd-dt-col-head__title">{{ title }}</span>
    <input
      v-model="value"
      type="search"
      class="hd-dt-col-filter"
      :placeholder="placeholder"
      :aria-label="ariaLabel"
      @input="onInput"
      @keydown.enter.prevent="onEnter"
    />
  </div>
</template>
