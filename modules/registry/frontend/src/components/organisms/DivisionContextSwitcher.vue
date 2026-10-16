<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { api } from '@/lib/api'
import { useAuthStore, type DivisionContext } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const props = withDefaults(
  defineProps<{
    /** `page` sits beside page titles on light backgrounds; `chrome` is for the top bar. */
    placement?: 'page' | 'chrome'
  }>(),
  { placement: 'page' },
)

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

const activeLabel = computed(() => {
  const match = (ctx.value?.divisions ?? []).find((d) => d.id === activeId.value)
  return match?.name || locale.t('chrome.acting_division', 'Acting division')
})

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
  <div
    v-if="enabled"
    class="rr-division-switcher"
    :class="placement === 'page' ? 'rr-division-switcher--page' : 'rr-division-switcher--chrome'"
  >
    <v-select
      v-model="activeId"
      :items="items"
      :label="placement === 'chrome' ? locale.t('chrome.acting_division', 'Acting division') : undefined"
      :aria-label="locale.t('chrome.acting_division', 'Acting division')"
      density="compact"
      hide-details="auto"
      :variant="placement === 'page' ? 'solo' : 'outlined'"
      :loading="switching"
      :disabled="switching"
      prepend-inner-icon="mdi-office-building-outline"
      class="rr-division-switcher__select"
      @update:model-value="onChange"
    >
      <template v-if="placement === 'page'" #selection>
        <span class="rr-division-switcher__chip-label">
          <span class="rr-division-switcher__chip-kicker">{{ locale.t('chrome.acting_division', 'Acting division') }}</span>
          <span class="rr-division-switcher__chip-value">{{ activeLabel }}</span>
        </span>
      </template>
    </v-select>
    <div v-if="error" class="text-caption text-error mt-1">{{ error }}</div>
  </div>
</template>

<style scoped>
.rr-division-switcher {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.rr-division-switcher--chrome .rr-division-switcher__select {
  min-width: 220px;
  max-width: 320px;
}
.rr-division-switcher--page {
  flex-shrink: 0;
}
.rr-division-switcher--page .rr-division-switcher__select {
  min-width: 200px;
  max-width: 280px;
}
.rr-division-switcher--page :deep(.v-field) {
  border-radius: 999px;
  background: #e8eef3 !important;
  box-shadow: none !important;
  min-height: 34px !important;
}
.rr-division-switcher--page :deep(.v-field__outline) {
  display: none;
}
.rr-division-switcher--page :deep(.v-field__input) {
  padding-top: 4px;
  padding-bottom: 4px;
  min-height: 34px;
  font-size: 0.82rem;
  color: #334155;
}
.rr-division-switcher--page :deep(.v-field__prepend-inner) {
  padding-top: 0;
  align-self: center;
  color: #64748b;
}
.rr-division-switcher__chip-label {
  display: inline-flex;
  align-items: baseline;
  gap: 0.35rem;
  min-width: 0;
  overflow: hidden;
}
.rr-division-switcher__chip-kicker {
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #64748b;
  flex-shrink: 0;
}
.rr-division-switcher__chip-value {
  font-weight: 650;
  color: #0b3b5c;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
