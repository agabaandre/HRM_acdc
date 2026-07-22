<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, ref, watch } from 'vue'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { hasRichTextContent, htmlContainsDataUriImages } from '../../lib/richText'
import { notifySuccess } from '../../lib/notify'
import { useAuthStore } from '../../stores/auth'

const CbpRichTextEditor = defineAsyncComponent(
  () => import('../common/CbpRichTextEditor.vue'),
)

export interface ResolveTicketRef {
  id: number
  ticket_number: string
  subject: string
  category?: { id: number; name: string } | null
}

interface LinkableAsset {
  id: number
  asset_tag: string
  name: string
  brand?: string | null
  model?: string | null
  serial_number?: string | null
  label: string
}

const props = defineProps<{
  ticket: ResolveTicketRef | null
}>()

const emit = defineEmits<{
  close: []
  resolved: [ticketId: number]
}>()

const auth = useAuthStore()
const resolutionNotes = ref('')
const publishToKb = ref(false)
const kbSubject = ref('')
const resolving = ref(false)
const resolveErr = ref<string | null>(null)
const inlineImageBusy = ref(false)
const resolutionEditorRef = ref<InstanceType<typeof CbpRichTextEditor> | null>(null)

const assetLinkEnabled = ref(false)
const assetSearch = ref('')
const assetResults = ref<LinkableAsset[]>([])
const assetLoading = ref(false)
const assetPickerOpen = ref(false)
const selectedAsset = ref<LinkableAsset | null>(null)
let assetTimer: ReturnType<typeof setTimeout> | null = null

const modalOpen = computed({
  get: () => props.ticket !== null,
  set: (open: boolean) => {
    if (!open) {
      close()
    }
  },
})

const modalTitle = computed(() =>
  props.ticket ? `Resolve ${props.ticket.ticket_number}` : 'Resolve ticket',
)

const modalDescription = computed(() => props.ticket?.subject ?? undefined)

const canPublishKb = computed(() => {
  const p = auth.me?.profile
  if (!p) {
    return false
  }
  return !!p.is_helpdesk_admin || p.role === 'admin' || !!p.can_manage_kb
})

const canSubmit = computed(() => {
  if (!hasRichTextContent(resolutionNotes.value)) {
    return false
  }
  if (publishToKb.value && !kbSubject.value.trim()) {
    return false
  }
  if (inlineImageBusy.value) {
    return false
  }
  return true
})

function resetState(): void {
  resolutionNotes.value = ''
  publishToKb.value = false
  kbSubject.value = ''
  resolving.value = false
  resolveErr.value = null
  inlineImageBusy.value = false
  assetLinkEnabled.value = false
  assetSearch.value = ''
  assetResults.value = []
  assetLoading.value = false
  assetPickerOpen.value = false
  selectedAsset.value = null
  if (assetTimer) {
    clearTimeout(assetTimer)
    assetTimer = null
  }
}

function close(): void {
  resetState()
  emit('close')
}

async function loadLinkableAssets(q = ''): Promise<void> {
  const ticket = props.ticket
  if (!ticket) return
  assetLoading.value = true
  try {
    const { data } = await api.get<{
      data: LinkableAsset[]
      meta?: { enabled?: boolean }
    }>(`/api/v1/tickets/${ticket.id}/linkable-assets`, {
      params: q.trim() ? { q: q.trim() } : undefined,
    })
    assetLinkEnabled.value = !!data.meta?.enabled
    assetResults.value = Array.isArray(data.data) ? data.data : []
  } catch {
    assetLinkEnabled.value = false
    assetResults.value = []
  } finally {
    assetLoading.value = false
  }
}

function pickAsset(row: LinkableAsset): void {
  selectedAsset.value = row
  assetSearch.value = row.label
  assetPickerOpen.value = false
}

function clearAsset(): void {
  selectedAsset.value = null
  assetSearch.value = ''
  void loadLinkableAssets('')
}

watch(
  () => props.ticket,
  (ticket) => {
    resetState()
    if (ticket) {
      kbSubject.value = ticket.subject?.trim() ?? ''
      void loadLinkableAssets('')
    }
  },
  { immediate: true },
)

watch(assetSearch, (q) => {
  if (!assetLinkEnabled.value || selectedAsset.value) return
  assetPickerOpen.value = true
  if (assetTimer) clearTimeout(assetTimer)
  assetTimer = setTimeout(() => {
    void loadLinkableAssets(q)
  }, 250)
})

onBeforeUnmount(() => {
  if (assetTimer) clearTimeout(assetTimer)
})

async function submitResolution(): Promise<void> {
  const ticket = props.ticket
  if (!ticket) {
    return
  }
  if (!hasRichTextContent(resolutionNotes.value)) {
    resolveErr.value = 'Describe what was fixed before resolving this ticket.'
    return
  }
  if (inlineImageBusy.value) {
    resolveErr.value = 'An image is still uploading. Wait a moment and try again.'
    return
  }
  await resolutionEditorRef.value?.ensureImagesUploaded()
  const summary = resolutionNotes.value
  if (htmlContainsDataUriImages(summary)) {
    resolveErr.value = 'An image is still uploading. Wait a moment and try again.'
    return
  }
  if (publishToKb.value && !kbSubject.value.trim()) {
    resolveErr.value = 'Enter a subject for the knowledge base article.'
    return
  }

  resolving.value = true
  resolveErr.value = null
  try {
    const payload: Record<string, unknown> = { resolution_summary: summary }
    if (publishToKb.value && canPublishKb.value) {
      payload.publish_to_kb = true
      payload.kb_question = kbSubject.value.trim()
    }
    if (assetLinkEnabled.value && selectedAsset.value) {
      payload.linked_it_asset_id = selectedAsset.value.id
    }
    await api.post(`/api/v1/tickets/${ticket.id}/submit-resolution`, payload)
    notifySuccess(`${ticket.ticket_number} resolved — requester notified.`)
    emit('resolved', ticket.id)
    close()
  } catch (e: unknown) {
    resolveErr.value = apiErrorMessage(e, 'Could not submit resolution')
  } finally {
    resolving.value = false
  }
}
</script>

<template>
  <UModal
    v-if="ticket"
    v-model:open="modalOpen"
    :title="modalTitle"
    :description="modalDescription"
    :ui="{ content: 'max-w-2xl' }"
  >
    <template #body>
      <div class="resolve-modal-body">
        <p class="resolve-modal-lede muted small">
          The ticket will move to <strong>Resolved</strong> and the requester will receive an email with your
          resolution notes. They can mark the ticket closed when satisfied, or reopen it if the issue persists.
          Unclosed resolved tickets are automatically closed after the review period in settings.
          <span v-if="ticket.category?.name">
            If you publish to the knowledge base, the article will appear under
            <strong>{{ ticket.category.name }}</strong>.
          </span>
        </p>

        <UFormField
          label="What did you do to resolve this?"
          name="resolutionNotes"
          required
          description="Supports headings, lists, links, screenshots, and video embeds."
        >
          <CbpRichTextEditor
            ref="resolutionEditorRef"
            v-model="resolutionNotes"
            variant="full"
            :ticket-id="ticket.id"
            :min-rows="6"
            placeholder="Describe what was fixed. Supports headings, lists, links, code blocks, screenshots, and video embeds…"
            @uploading="inlineImageBusy = $event"
          />
        </UFormField>

        <UFormField
          v-if="assetLinkEnabled"
          label="Related IT asset (optional)"
          name="linkedAsset"
          description="Search the requester’s assigned assets by serial, tag, name, brand, or model."
        >
          <div class="asset-picker">
            <UInput
              v-model="assetSearch"
              type="search"
              icon="i-lucide-search"
              placeholder="Serial, asset tag, name, brand…"
              class="w-full"
              autocomplete="off"
              @focus="assetPickerOpen = true"
            />
            <ul
              v-if="assetPickerOpen && !selectedAsset && (assetResults.length || assetLoading || assetSearch.trim())"
              class="asset-results"
              role="listbox"
              aria-label="Requester IT assets"
            >
              <li v-if="assetLoading" class="asset-empty">Searching…</li>
              <li
                v-for="row in assetResults"
                :key="row.id"
                role="option"
                class="asset-result"
                @mousedown.prevent="pickAsset(row)"
              >
                <strong>{{ row.asset_tag }}</strong>
                <span class="meta">{{ row.label }}</span>
              </li>
              <li v-if="!assetLoading && !assetResults.length" class="asset-empty">
                No assets assigned to this requester match that search.
              </li>
            </ul>
            <p v-if="selectedAsset" class="asset-selected" role="status">
              <strong>Linked:</strong> {{ selectedAsset.label }}
              <button type="button" class="clear-btn" @click="clearAsset">Clear</button>
            </p>
          </div>
        </UFormField>

        <UForm :state="{ publishToKb, kbSubject }" class="hd-form resolve-modal-form">
          <UFormField v-if="canPublishKb" name="publishToKb">
            <UCheckbox
              v-model="publishToKb"
              :disabled="!hasRichTextContent(resolutionNotes)"
              label="Publish this solution to the knowledge base"
            />
          </UFormField>

          <UFormField
            v-if="publishToKb && canPublishKb"
            label="Knowledge base subject"
            name="kbSubject"
            description="Shown as the FAQ question on the home page search."
            required
          >
            <UInput
              v-model="kbSubject"
              type="text"
              maxlength="255"
              placeholder="e.g. How to reset your VPN password"
              :disabled="!hasRichTextContent(resolutionNotes)"
              class="w-full"
            />
          </UFormField>
        </UForm>

        <p v-if="resolveErr" class="resolve-modal-err" role="alert">{{ resolveErr }}</p>
      </div>
    </template>
    <template #footer>
      <UButton color="neutral" variant="outline" label="Cancel" :disabled="resolving" @click="close" />
      <UButton
        color="primary"
        :label="resolving ? 'Resolving…' : 'Confirm & resolve'"
        :loading="resolving"
        :disabled="!canSubmit"
        @click="submitResolution"
      />
    </template>
  </UModal>
</template>

<style scoped>
.resolve-modal-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.resolve-modal-lede {
  margin: 0;
  line-height: 1.5;
}
.resolve-modal-form {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.resolve-modal-err {
  margin: 0;
  font-size: 0.85rem;
  color: #b91c1c;
}
.muted {
  color: #64748b;
}
.small {
  font-size: 0.88rem;
}
.w-full {
  width: 100%;
}
.asset-picker {
  position: relative;
  width: 100%;
}
.asset-results {
  position: absolute;
  z-index: 30;
  left: 0;
  right: 0;
  top: calc(100% + 0.25rem);
  margin: 0;
  padding: 0.25rem;
  list-style: none;
  max-height: 14rem;
  overflow: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}
.asset-result {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 0.45rem 0.6rem;
  border-radius: 0.35rem;
  cursor: pointer;
}
.asset-result:hover {
  background: #f0fdf4;
}
.asset-result .meta {
  font-size: 0.75rem;
  color: #64748b;
}
.asset-empty {
  padding: 0.6rem;
  color: #64748b;
  font-size: 0.85rem;
}
.asset-selected {
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
