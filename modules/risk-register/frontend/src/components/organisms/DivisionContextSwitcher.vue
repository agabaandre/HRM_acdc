<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore, type DivisionContext } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const auth = useAuthStore()
const locale = useLocaleStore()
const switching = ref(false)
const error = ref<string | null>(null)

const ctx = computed<DivisionContext | null>(() => auth.me?.division_context ?? null)
const enabled = computed(() => !!ctx.value?.enabled && (ctx.value.divisions?.length ?? 0) >= 2)
const activeId = ref<number | null>(null)

watch(
  () => ctx.value?.active_id ?? auth.me?.profile?.division_id ?? null,
  (id) => {
    activeId.value = id != null ? Number(id) : null
  },
  { immediate: true },
)

const items = computed(() =>
  (ctx.value?.divisions ?? []).map((d) => ({
    title: d.is_primary ? `${d.name} (Primary)` : d.name,
    value: d.id,
  })),
)

async function onChange(id: number | null) {
  if (id == null || switching.value) return
  const current = Number(ctx.value?.active_id ?? auth.me?.profile?.division_id ?? 0)
  if (Number(id) === current) return

  switching.value = true
  error.value = null
  try {
    await api.post('/api/v1/division-context', { division_id: id })
    await auth.fetchMe(true)
  } catch (e: unknown) {
    activeId.value = current > 0 ? current : null
    const msg =
      (e as { response?: { data?: { message?: string } } })?.response?.data?.message ||
      locale.t('chrome.division_switch_error', 'Could not switch division.')
    error.value = msg
  } finally {
    switching.value = false
  }
}
</script>

<template>
  <div v-if="enabled" class="rr-division-switcher">
    <v-select
      v-model="activeId"
      :items="items"
      :label="locale.t('chrome.acting_division', 'Acting division')"
      density="compact"
      hide-details="auto"
      variant="outlined"
      :loading="switching"
      :disabled="switching"
      prepend-inner-icon="mdi-office-building-outline"
      style="min-width: 220px; max-width: 320px"
      @update:model-value="onChange"
    />
    <div v-if="error" class="text-caption text-error mt-1">{{ error }}</div>
  </div>
</template>

<style scoped>
.rr-division-switcher {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
</style>
