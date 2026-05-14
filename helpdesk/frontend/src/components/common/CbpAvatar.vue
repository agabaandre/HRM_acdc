<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { avatarBackground, avatarInitials } from '../../lib/avatar'

const props = withDefaults(
  defineProps<{
    name: string
    imageUrl?: string | null
    size?: 'xs' | 'sm' | 'md' | 'lg'
  }>(),
  { size: 'md', imageUrl: null },
)

const showImg = ref(!!props.imageUrl)

watch(
  () => props.imageUrl,
  (url) => {
    showImg.value = !!url
  },
)

const initials = computed(() => avatarInitials(props.name || '?'))
const bg = computed(() => avatarBackground(props.name || '?'))

const sizeClass = computed(() => `cbp-avatar--${props.size}`)

function onImgError() {
  showImg.value = false
}
</script>

<template>
  <div
    class="cbp-avatar"
    :class="sizeClass"
    :style="{ backgroundColor: showImg && imageUrl ? 'transparent' : bg }"
    :title="name"
    role="img"
    :aria-label="name"
  >
    <img
      v-if="imageUrl && showImg"
      :src="imageUrl"
      alt=""
      class="cbp-avatar__img"
      referrerpolicy="no-referrer"
      @error="onImgError"
    />
    <span v-else class="cbp-avatar__initials">{{ initials }}</span>
  </div>
</template>

<style scoped>
.cbp-avatar {
  position: relative;
  flex-shrink: 0;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  color: #fff;
  font-weight: 700;
  border: 2px solid rgba(15, 23, 42, 0.12);
  box-sizing: border-box;
}
.cbp-avatar--xs {
  width: 26px;
  height: 26px;
  font-size: 0.62rem;
}
.cbp-avatar--sm {
  width: 32px;
  height: 32px;
  font-size: 0.72rem;
}
.cbp-avatar--md {
  width: 40px;
  height: 40px;
  font-size: 0.85rem;
}
.cbp-avatar--lg {
  width: 48px;
  height: 48px;
  font-size: 1rem;
}
.cbp-avatar__img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
  display: block;
}
.cbp-avatar__initials {
  line-height: 1;
  user-select: none;
}
</style>
