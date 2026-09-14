<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { api } from '../../lib/api'

export interface StaffDirectoryRow {
  id: number
  name: string
  work_email?: string
  duty_station_name?: string | null
}

const model = defineModel<number | null>({ default: null })

const props = withDefaults(
  defineProps<{
    label?: string
    disabled?: boolean
    placeholder?: string
    /** Optional display label when staff_id is set but not in search results. */
    initialLabel?: string | null
  }>(),
  {
    placeholder: 'Search staff by name, email, or ID…',
  },
)

const emit = defineEmits<{
  selected: [row: StaffDirectoryRow | null]
}>()

const search = ref('')
const open = ref(false)
const loading = ref(false)
const rows = ref<StaffDirectoryRow[]>([])
const selectedRow = ref<StaffDirectoryRow | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null
let skipWatch = false

const preview = computed(() => {
  if (selectedRow.value) {
    const email = selectedRow.value.work_email ? ` · ${selectedRow.value.work_email}` : ''
    return `${selectedRow.value.name}${email}`
  }
  if (model.value && props.initialLabel) {
    return props.initialLabel
  }
  return null
})

async function fetchStaff(q: string) {
  loading.value = true
  try {
    const params: Record<string, string | number> = {}
    if (q.trim()) params.q = q.trim()
    const { data } = await api.get<{ data: { staff: StaffDirectoryRow[] } }>('/api/v1/reference-data/staff', {
      params,
    })
    rows.value = (data.data?.staff ?? []).slice(0, 40)
  } catch {
    rows.value = []
  } finally {
    loading.value = false
  }
}

function pick(row: StaffDirectoryRow) {
  skipWatch = true
  selectedRow.value = row
  model.value = row.id
  search.value = row.name
  open.value = false
  emit('selected', row)
  queueMicrotask(() => {
    skipWatch = false
  })
}

function clear() {
  selectedRow.value = null
  model.value = null
  search.value = ''
  emit('selected', null)
}

watch(search, (q) => {
  if (skipWatch || props.disabled) return
  open.value = true
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => {
    void fetchStaff(q)
  }, 250)
})

watch(
  () => model.value,
  (id) => {
    if (!id) {
      selectedRow.value = null
      return
    }
    if (selectedRow.value?.id === id) return
    if (props.initialLabel) {
      search.value = props.initialLabel.split(' · ')[0] ?? props.initialLabel
    }
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})
</script>

<template>
  <div class="staff-picker">
    <UInput
      v-model="search"
      type="search"
      icon="i-lucide-search"
      :label="label"
      :disabled="disabled"
      :placeholder="placeholder"
      autocomplete="off"
      class="w-full"
      @focus="open = true"
    />
    <ul
      v-if="open && !disabled && (rows.length || loading || search.trim())"
      class="combo-results"
      role="listbox"
      aria-label="Staff directory results"
    >
      <li v-if="loading" class="combo-empty">Searching…</li>
      <li
        v-for="s in rows"
        :key="s.id"
        role="option"
        class="combo-result"
        :class="{ selected: model === s.id }"
        :aria-selected="model === s.id"
        @mousedown.prevent="pick(s)"
      >
        <strong>{{ s.name }}</strong>
        <span class="meta">{{ s.work_email || '—' }} · ID {{ s.id }}</span>
      </li>
      <li v-if="!loading && search.trim() && !rows.length" class="combo-empty">No staff found.</li>
    </ul>
    <p v-if="preview" class="preview" role="status">
      <strong>Selected:</strong> {{ preview }}
      <button v-if="!disabled" type="button" class="clear-btn" @click="clear">Clear</button>
    </p>
  </div>
</template>

<style scoped>
.staff-picker {
  position: relative;
  width: 100%;
}
.combo-results {
  position: absolute;
  z-index: 20;
  left: 0;
  right: 0;
  top: calc(100% + 0.25rem);
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  max-height: 16rem;
  overflow: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}
.combo-result {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.35rem;
  cursor: pointer;
}
.combo-result:hover,
.combo-result.selected {
  background: #f0fdf4;
}
.combo-result .meta {
  font-size: 0.75rem;
  color: #64748b;
}
.combo-empty {
  padding: 0.6rem;
  color: #64748b;
  font-size: 0.85rem;
}
.preview {
  margin: 0.4rem 0 0;
  font-size: 0.85rem;
  color: #334155;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.clear-btn {
  border: 0;
  background: transparent;
  color: #b91c1c;
  cursor: pointer;
  font-size: 0.8rem;
  text-decoration: underline;
  padding: 0;
}
</style>
