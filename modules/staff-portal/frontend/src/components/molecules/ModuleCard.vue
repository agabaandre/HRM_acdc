<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  description?: string
  icon?: string
  href: string
  opensInNewTab?: boolean
}>()

defineEmits<{ click: [e: MouseEvent] }>()

/** DB icons are often `fa-users`; normalize for Font Awesome 6. */
const iconClass = computed(() => {
  const raw = (props.icon || 'fa-th').trim()
  if (raw.includes('fa-solid') || raw.includes('fas ') || raw.startsWith('fa ')) {
    return raw
  }
  const name = raw.replace(/^fa-/, '')
  return `fa-solid fa-${name}`
})
</script>

<template>
  <a
    :href="href"
    class="cbp-home-card-link"
    :target="opensInNewTab ? '_blank' : undefined"
    :rel="opensInNewTab ? 'noopener noreferrer' : undefined"
    @click="$emit('click', $event)"
  >
    <div class="settings-card">
      <div>
        <h6>{{ label }}</h6>
        <p v-if="description">{{ description }}</p>
      </div>
      <div class="widgets-icons" aria-hidden="true">
        <i :class="iconClass" />
      </div>
    </div>
  </a>
</template>
