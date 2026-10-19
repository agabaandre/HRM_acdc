<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useTheme } from 'vuetify'
import { withAppBase } from '@/lib/basePath'
import { useBrandingStore } from '@/stores/branding'

const props = withDefaults(
  defineProps<{
    inverse?: boolean
  }>(),
  {
    inverse: false,
  },
)

const theme = useTheme()
const branding = useBrandingStore()
const failed = ref(false)

const preferredSrc = computed(() => {
  const url = theme.global.current.value.dark
    ? branding.branding.logo_dark_url ?? branding.branding.logo_url
    : branding.branding.logo_url
  return url ?? withAppBase('/ui/branding-logo.png')
})

const logoSrc = computed(() => (failed.value ? withAppBase('/ui/branding-logo.png') : preferredSrc.value))

watch(preferredSrc, () => {
  failed.value = false
})

function onError() {
  if (!failed.value) failed.value = true
}
</script>

<template>
  <img
    :src="logoSrc"
    :alt="branding.branding.app_name"
    class="app-logo"
    :class="{ 'app-logo--inverse': inverse }"
    @error="onError"
  />
</template>

<style scoped>
.app-logo {
  height: var(--admin-logo-height, 40px);
  max-width: 200px;
  object-fit: contain;
}

.app-logo--inverse {
  filter: brightness(0) invert(1);
}
</style>
