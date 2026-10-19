<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { api } from '@/lib/api'

const props = defineProps<{
  resetKey?: number | string
}>()

const emit = defineEmits<{
  (e: 'update:key', value: string | null): void
  (e: 'update:answer', value: string): void
}>()

const enabled = ref(false)
const loading = ref(true)
const error = ref('')
const image = ref<string | null>(null)
const captchaKey = ref<string | null>(null)
const answer = ref('')

async function loadChallenge() {
  loading.value = true
  error.value = ''
  answer.value = ''
  emit('update:key', null)
  emit('update:answer', '')

  try {
    const res = await api.get('/admin/captcha')
    enabled.value = Boolean(res.data.data?.enabled)
    captchaKey.value = res.data.data?.key ?? null
    image.value = res.data.data?.image ?? null
    emit('update:key', captchaKey.value)
  } catch {
    error.value = 'Could not load captcha. Refresh and try again.'
    enabled.value = false
    captchaKey.value = null
    image.value = null
  } finally {
    loading.value = false
  }
}

function onAnswerInput(value: string) {
  answer.value = value
  emit('update:answer', value)
}

watch(
  () => props.resetKey,
  () => {
    if (enabled.value) void loadChallenge()
  },
)

onMounted(loadChallenge)

defineExpose({ refresh: loadChallenge, enabled })
</script>

<template>
  <div v-if="enabled || loading" class="captcha-wrap">
    <div v-if="loading" class="text-caption text-medium-emphasis mb-2">Loading captcha…</div>
    <template v-else-if="enabled">
      <div class="d-flex align-center flex-wrap ga-3 mb-3">
        <img v-if="image" :src="image" alt="Captcha challenge" class="captcha-image" />
        <v-btn size="small" variant="text" icon="mdi-refresh" aria-label="Refresh captcha" @click="loadChallenge" />
      </div>
      <v-text-field
        :model-value="answer"
        label="Enter the characters shown above"
        variant="outlined"
        hide-details
        autocomplete="off"
        @update:model-value="onAnswerInput"
      />
    </template>
    <p v-if="error" class="text-error text-caption mt-2 mb-0">{{ error }}</p>
  </div>
</template>

<style scoped>
.captcha-wrap {
  max-width: 360px;
}
.captcha-image {
  height: 52px;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
  background: #f5f7fa;
}
</style>
