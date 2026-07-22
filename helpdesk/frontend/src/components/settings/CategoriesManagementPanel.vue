<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import type { DataTableHeader } from 'vuetify'
import type { FormError, FormSubmitEvent } from '../../types/form'
import { api } from '../../lib/api'
import { apiErrorMessage } from '../../lib/apiErrorMessage'
import { fieldError, PRIORITY_ITEMS, type TicketPriority } from '../../lib/helpdeskForm'
import { notifyError, notifySuccess } from '../../lib/notify'

interface BusinessUnitOption {
  id: number
  name: string
  slug: string
  description?: string | null
  sort_order: number
  is_active: boolean
  allows_anonymous: boolean
  allows_asset_link_on_resolve: boolean
  support_mailbox?: string | null
  email_intake_enabled?: boolean
  categories_count?: number
  active_categories_count?: number
  categories?: Array<{ id: number; name: string; is_active: boolean }>
}

interface CategoryRow {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
  default_priority: TicketPriority
  business_unit_id: number | null
  ai_description: string | null
  business_unit?: { id: number; name: string } | null
}

const tab = ref<'categories' | 'business-units'>('categories')

const catHeaders: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '180px' },
  { title: 'Business unit', key: 'business_unit', sortable: false, minWidth: '160px' },
  { title: 'Priority', key: 'default_priority', sortable: false, width: '110px' },
  { title: 'Order', key: 'sort_order', sortable: false, width: '90px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '180px', align: 'end' },
]

const buHeaders: DataTableHeader[] = [
  { title: 'Name', key: 'name', sortable: false, minWidth: '160px' },
  { title: 'Mailbox', key: 'support_mailbox', sortable: false, minWidth: '180px' },
  { title: 'Categories', key: 'categories', sortable: false, minWidth: '200px' },
  { title: 'Active', key: 'is_active', sortable: false, width: '90px', align: 'center' },
  { title: 'Actions', key: 'actions', sortable: false, width: '280px', align: 'end' },
]

const rows = ref<CategoryRow[]>([])
const units = ref<BusinessUnitOption[]>([])
const busyId = ref<number | null>(null)
const buBusyId = ref<number | null>(null)
const catModalOpen = ref(false)
const catEditingId = ref<number | null>(null)
const buModalOpen = ref(false)
const buEditingId = ref<number | null>(null)
const testReadBusyId = ref<number | null>(null)
const testReadOpen = ref(false)
const testReadTitle = ref('Mailbox test read')
const testReadResult = ref<{
  mailbox: string
  count: number
  messages: Array<{
    subject?: string | null
    from_name?: string | null
    from_email?: string | null
    received_at?: string | null
    preview?: string
    already_imported?: boolean
  }>
} | null>(null)
const testReadError = ref<string | null>(null)

const draft = reactive({
  name: '',
  slug: '',
  sort_order: 0,
  is_active: true,
  default_priority: 'medium' as TicketPriority,
  business_unit_id: undefined as number | undefined,
  ai_description: '',
})

const buDraft = reactive({
  name: '',
  slug: '',
  description: '',
  sort_order: 0,
  is_active: true,
  allows_anonymous: false,
  allows_asset_link_on_resolve: false,
  support_mailbox: '',
  email_intake_enabled: false,
})

const unitItems = computed(() =>
  units.value.map((u) => ({ label: u.name, value: u.id })),
)

const catModalTitle = computed(() => (catEditingId.value ? 'Edit issue category' : 'Add issue category'))
const buModalTitle = computed(() => (buEditingId.value ? 'Edit business unit' : 'Add business unit'))

function validateDraft(state: typeof draft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) errors.push(nameErr)
  if (!state.business_unit_id) {
    errors.push({ name: 'business_unit_id', message: 'Choose a business unit' })
  }
  return errors
}

function validateBuDraft(state: typeof buDraft): FormError[] {
  const errors: FormError[] = []
  const nameErr = fieldError('name', state.name, 'Name is required')
  if (nameErr) errors.push(nameErr)
  const mailbox = state.support_mailbox.trim()
  if (mailbox && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mailbox)) {
    errors.push({ name: 'support_mailbox', message: 'Enter a valid email address' })
  }
  return errors
}

function resetCatDraft() {
  draft.name = ''
  draft.slug = ''
  draft.sort_order = 0
  draft.is_active = true
  draft.default_priority = 'medium'
  draft.ai_description = ''
  const itMis = units.value.find((u) => u.slug === 'it-mis')
  draft.business_unit_id = itMis?.id ?? units.value[0]?.id
}

function openCreateCatModal() {
  catEditingId.value = null
  resetCatDraft()
  catModalOpen.value = true
}

function openEditCatModal(row: CategoryRow) {
  catEditingId.value = row.id
  draft.name = row.name
  draft.slug = row.slug
  draft.sort_order = row.sort_order
  draft.is_active = row.is_active
  draft.default_priority = row.default_priority
  draft.business_unit_id = row.business_unit_id ?? undefined
  draft.ai_description = row.ai_description ?? ''
  catModalOpen.value = true
}

function closeCatModal() {
  catModalOpen.value = false
  catEditingId.value = null
  resetCatDraft()
}

function resetBuDraft() {
  buDraft.name = ''
  buDraft.slug = ''
  buDraft.description = ''
  buDraft.sort_order = 0
  buDraft.is_active = true
  buDraft.allows_anonymous = false
  buDraft.allows_asset_link_on_resolve = false
  buDraft.support_mailbox = ''
  buDraft.email_intake_enabled = false
}

function openCreateBuModal() {
  buEditingId.value = null
  resetBuDraft()
  buModalOpen.value = true
}

function openEditBuModal(row: BusinessUnitOption) {
  buEditingId.value = row.id
  buDraft.name = row.name
  buDraft.slug = row.slug
  buDraft.description = row.description ?? ''
  buDraft.sort_order = row.sort_order
  buDraft.is_active = row.is_active
  buDraft.allows_anonymous = row.allows_anonymous
  buDraft.allows_asset_link_on_resolve = row.allows_asset_link_on_resolve
  buDraft.support_mailbox = row.support_mailbox ?? ''
  buDraft.email_intake_enabled = !!row.email_intake_enabled
  buModalOpen.value = true
}

function closeBuModal() {
  buModalOpen.value = false
  buEditingId.value = null
  resetBuDraft()
}

function normalizeRow(row: CategoryRow): CategoryRow {
  const priority = row.default_priority
  return {
    ...row,
    ai_description: row.ai_description ?? '',
    default_priority: (['low', 'medium', 'high', 'critical'].includes(priority) ? priority : 'medium') as TicketPriority,
  }
}

async function loadUnits() {
  const { data } = await api.get<{ data: BusinessUnitOption[] }>('/api/v1/admin/business-units')
  units.value = Array.isArray(data.data) ? data.data : []
  if (!draft.business_unit_id && units.value.length) {
    const itMis = units.value.find((u) => u.slug === 'it-mis')
    draft.business_unit_id = itMis?.id ?? units.value[0].id
  }
}

async function loadCategories() {
  const { data } = await api.get<{ data: CategoryRow[] }>('/api/v1/admin/categories')
  rows.value = (Array.isArray(data.data) ? data.data : []).map(normalizeRow)
}

async function load() {
  try {
    await Promise.all([loadUnits(), loadCategories()])
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Failed to load categories.'))
  }
}

async function saveCatModal(_event?: FormSubmitEvent<typeof draft>) {
  const payload = {
    name: draft.name.trim(),
    slug: draft.slug.trim() || undefined,
    sort_order: draft.sort_order,
    is_active: draft.is_active,
    default_priority: draft.default_priority,
    business_unit_id: draft.business_unit_id,
    ai_description: draft.ai_description.trim() || null,
  }

  busyId.value = catEditingId.value ?? -1
  try {
    if (catEditingId.value) {
      await api.put(`/api/v1/admin/categories/${catEditingId.value}`, payload)
      notifySuccess(`Updated “${payload.name}”.`)
    } else {
      await api.post('/api/v1/admin/categories', payload)
      notifySuccess('Category created.')
    }
    closeCatModal()
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    busyId.value = null
  }
}

async function remove(row: CategoryRow) {
  if (!window.confirm(`Delete category “${row.name}”? This fails if tickets still use it.`)) return
  busyId.value = row.id
  try {
    await api.delete(`/api/v1/admin/categories/${row.id}`)
    notifySuccess('Category deleted.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
  } finally {
    busyId.value = null
  }
}

async function saveBuModal(_event?: FormSubmitEvent<typeof buDraft>) {
  const payload = {
    name: buDraft.name.trim(),
    slug: buDraft.slug.trim() || undefined,
    description: buDraft.description.trim() || null,
    sort_order: buDraft.sort_order,
    is_active: buDraft.is_active,
    allows_anonymous: buDraft.allows_anonymous,
    allows_asset_link_on_resolve: buDraft.allows_asset_link_on_resolve,
    support_mailbox: buDraft.support_mailbox.trim() || null,
    email_intake_enabled: buDraft.email_intake_enabled,
  }

  buBusyId.value = buEditingId.value ?? -1
  try {
    if (buEditingId.value) {
      await api.put(`/api/v1/admin/business-units/${buEditingId.value}`, payload)
      notifySuccess(`Updated “${payload.name}”.`)
    } else {
      await api.post('/api/v1/admin/business-units', payload)
      notifySuccess('Business unit created.')
    }
    closeBuModal()
    await loadUnits()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Save failed'))
  } finally {
    buBusyId.value = null
  }
}

async function removeUnit(row: BusinessUnitOption) {
  if (!window.confirm(`Delete business unit “${row.name}”?`)) return
  buBusyId.value = row.id
  try {
    await api.delete(`/api/v1/admin/business-units/${row.id}`)
    notifySuccess('Business unit deleted.')
    await load()
  } catch (e: unknown) {
    notifyError(apiErrorMessage(e, 'Delete failed'))
  } finally {
    buBusyId.value = null
  }
}

async function testEmailRead(row: BusinessUnitOption) {
  if (!row.support_mailbox?.trim()) {
    notifyError('Set a support mailbox on this business unit first.')
    return
  }
  testReadBusyId.value = row.id
  testReadError.value = null
  testReadResult.value = null
  testReadTitle.value = `Test read · ${row.name}`
  testReadOpen.value = true
  try {
    const { data } = await api.post<{
      message: string
      data: {
        mailbox: string
        count: number
        messages: Array<{
          subject?: string | null
          from_name?: string | null
          from_email?: string | null
          received_at?: string | null
          preview?: string
          already_imported?: boolean
        }>
      }
    }>(`/api/v1/admin/business-units/${row.id}/test-email-read`, { top: 10 })
    testReadResult.value = data.data
    notifySuccess(data.message || 'Mailbox read OK.')
  } catch (e: unknown) {
    testReadError.value = apiErrorMessage(e, 'Mailbox test read failed')
    notifyError(testReadError.value)
  } finally {
    testReadBusyId.value = null
  }
}

async function testEmailReadFromModal() {
  if (!buEditingId.value) {
    notifyError('Save the business unit first, then run Test read.')
    return
  }
  const row = units.value.find((u) => u.id === buEditingId.value)
  if (!row) return
  await testEmailRead({
    ...row,
    support_mailbox: buDraft.support_mailbox.trim() || row.support_mailbox,
  })
}

onMounted(() => {
  void load()
})
</script>

<template>
  <section class="panel" aria-labelledby="cat-heading">
    <h2 id="cat-heading">Issue categories &amp; business units</h2>
    <p class="hint">
      Business units group issue categories (e.g. IT &amp; MIS). Add a <strong>description</strong> so requesters understand which unit handles their issue.
      Routing still uses issue categories. Add an <strong>AI description</strong> on each category so automatic categorization can match requester text.
      Default priority applies when no
      <RouterLink to="/settings/risk-matrix">priority matrix</RouterLink> rule matches.
    </p>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="categories">Issue categories</v-tab>
      <v-tab value="business-units">Business units</v-tab>
    </v-tabs>

    <div v-if="tab === 'categories'">
      <div class="bu-toolbar">
        <p class="bu-toolbar-hint muted">
          Edit each category in a modal (business unit, AI description, priority). Keep the table lean for scanning.
        </p>
        <UButton color="primary" @click="openCreateCatModal">Add category</UButton>
      </div>

      <v-card v-if="rows.length" class="cat-table-card" elevation="10">
        <v-data-table
          :headers="catHeaders"
          :items="rows"
          item-value="id"
          density="comfortable"
          class="hd-data-table cat-table"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <strong>{{ item.name }}</strong>
            <div class="bu-slug">{{ item.slug }}</div>
          </template>
          <template #item.business_unit="{ item }">
            {{ item.business_unit?.name ?? unitItems.find((u) => u.value === item.business_unit_id)?.label ?? '—' }}
          </template>
          <template #item.default_priority="{ item }">
            <span class="priority-pill">{{ item.default_priority }}</span>
          </template>
          <template #item.is_active="{ item }">
            <UCheckbox :model-value="item.is_active" disabled hide-details />
          </template>
          <template #item.actions="{ item }">
            <div class="actions">
              <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditCatModal(item)">Edit</UButton>
              <UButton type="button" color="error" variant="soft" size="small" :disabled="busyId === item.id" @click="remove(item)">Delete</UButton>
            </div>
          </template>
        </v-data-table>
      </v-card>
      <p v-else class="muted">No categories loaded.</p>

      <UModal
        v-model:open="catModalOpen"
        :title="catModalTitle"
        :ui="{ content: 'max-w-xl' }"
      >
        <template #body>
          <UForm :state="draft" :validate="validateDraft" class="hd-form hd-form--grid" @submit="saveCatModal">
            <UFormField label="Name" name="name" required class="span-2">
              <UInput v-model="draft.name" maxlength="191" icon="mdi-tag-outline" />
            </UFormField>
            <UFormField label="Business unit" name="business_unit_id" required>
              <USelect v-model="draft.business_unit_id" :items="unitItems" icon="mdi-office-building-outline" />
            </UFormField>
            <UFormField label="Slug (optional)" name="slug" stacked-label description="Leave blank to auto-generate">
              <UInput v-model="draft.slug" maxlength="191" placeholder="auto from name" />
            </UFormField>
            <UFormField label="Default priority" name="default_priority">
              <USelect v-model="draft.default_priority" :items="PRIORITY_ITEMS" icon="mdi-flag-outline" />
            </UFormField>
            <UFormField label="Sort order" name="sort_order">
              <UInput v-model.number="draft.sort_order" type="number" min="0" icon="mdi-sort-numeric-ascending" />
            </UFormField>
            <UFormField name="is_active">
              <UCheckbox v-model="draft.is_active" label="Active" />
            </UFormField>
            <UFormField
              label="AI description"
              name="ai_description"
              class="full"
              stacked-label
              description="Criteria AI uses to match this category"
            >
              <UTextarea v-model="draft.ai_description" :rows="3" />
            </UFormField>
          </UForm>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" :disabled="busyId !== null" @click="closeCatModal">Cancel</UButton>
          <UButton
            color="primary"
            :loading="busyId !== null"
            :label="catEditingId ? 'Save changes' : 'Create category'"
            @click="saveCatModal()"
          />
        </template>
      </UModal>
    </div>

    <div v-else>
      <div class="bu-toolbar">
        <p class="bu-toolbar-hint muted">
          Edit each unit in a modal (mailbox, anonymous, asset link, intake). IT &amp; MIS defaults to
          <code>helpdesk@africacdc.org</code>.
        </p>
        <UButton color="primary" @click="openCreateBuModal">Add business unit</UButton>
      </div>

      <v-card v-if="units.length" class="cat-table-card" elevation="10">
        <v-data-table
          :headers="buHeaders"
          :items="units"
          item-value="id"
          density="comfortable"
          class="hd-data-table cat-table"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <strong>{{ item.name }}</strong>
            <div class="bu-slug">{{ item.slug }}</div>
          </template>
          <template #item.support_mailbox="{ item }">
            <span v-if="item.support_mailbox">{{ item.support_mailbox }}</span>
            <span v-else class="muted">—</span>
            <span v-if="item.email_intake_enabled" class="intake-pill">Intake on</span>
          </template>
          <template #item.categories="{ item }">
            <div class="bu-cats">
              <span class="bu-cat-count">{{ item.categories_count ?? item.categories?.length ?? 0 }} linked</span>
              <span v-for="c in (item.categories ?? []).slice(0, 4)" :key="c.id" class="bu-cat-chip">{{ c.name }}</span>
              <span v-if="(item.categories?.length ?? 0) > 4" class="bu-cat-more">+{{ (item.categories?.length ?? 0) - 4 }} more</span>
            </div>
          </template>
          <template #item.is_active="{ item }">
            <UCheckbox :model-value="item.is_active" disabled hide-details />
          </template>
          <template #item.actions="{ item }">
            <div class="actions">
              <UButton type="button" color="neutral" variant="outlined" size="small" @click="openEditBuModal(item)">Edit</UButton>
              <UButton
                type="button"
                color="neutral"
                variant="soft"
                size="small"
                :loading="testReadBusyId === item.id"
                :disabled="!item.support_mailbox"
                @click="testEmailRead(item)"
              >
                Test read
              </UButton>
              <UButton type="button" color="error" variant="soft" size="small" :disabled="buBusyId === item.id" @click="removeUnit(item)">Delete</UButton>
            </div>
          </template>
        </v-data-table>
      </v-card>
      <p v-else class="muted">No business units loaded.</p>

      <UModal
        v-model:open="buModalOpen"
        :title="buModalTitle"
        :ui="{ content: 'max-w-xl' }"
      >
        <template #body>
          <UForm :state="buDraft" :validate="validateBuDraft" class="hd-form hd-form--grid" @submit="saveBuModal">
            <UFormField label="Name" name="name" required class="span-2">
              <UInput v-model="buDraft.name" maxlength="191" icon="mdi-office-building-outline" />
            </UFormField>
            <UFormField label="Slug (optional)" name="slug">
              <UInput v-model="buDraft.slug" maxlength="191" placeholder="auto from name" />
            </UFormField>
            <UFormField label="Sort order" name="sort_order">
              <UInput v-model.number="buDraft.sort_order" type="number" min="0" />
            </UFormField>
            <UFormField
              label="Description"
              name="description"
              class="full"
              stacked-label
              description="Shown on the new request form to explain which issues this unit handles"
            >
              <UTextarea v-model="buDraft.description" :rows="2" />
            </UFormField>
            <UFormField
              label="Support mailbox"
              name="support_mailbox"
              class="full"
              stacked-label
              description="Exchange mailbox for this unit (e.g. helpdesk@africacdc.org for IT & MIS)"
            >
              <UInput
                v-model="buDraft.support_mailbox"
                type="email"
                maxlength="191"
                placeholder="helpdesk@africacdc.org"
              />
            </UFormField>
            <UFormField name="is_active">
              <UCheckbox v-model="buDraft.is_active" label="Active" />
            </UFormField>
            <UFormField name="allows_anonymous">
              <UCheckbox v-model="buDraft.allows_anonymous" label="Allow anonymous reports" />
            </UFormField>
            <UFormField name="allows_asset_link_on_resolve">
              <UCheckbox v-model="buDraft.allows_asset_link_on_resolve" label="Allow Asset on resolve" />
            </UFormField>
            <UFormField name="email_intake_enabled" class="full">
              <UCheckbox
                v-model="buDraft.email_intake_enabled"
                label="Enable email intake (poll mailbox every minute)"
              />
            </UFormField>
            <div v-if="buEditingId" class="full">
              <UButton
                type="button"
                color="neutral"
                variant="outline"
                :loading="testReadBusyId === buEditingId"
                :disabled="!buDraft.support_mailbox.trim()"
                @click="testEmailReadFromModal"
              >
                Test read mailbox
              </UButton>
              <p class="test-read-hint muted">
                Lists unread Inbox messages via Exchange Graph. Does not create tickets. Global “Allow email submission” in General settings controls live intake.
              </p>
            </div>
          </UForm>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" :disabled="buBusyId !== null" @click="closeBuModal">Cancel</UButton>
          <UButton
            color="primary"
            :loading="buBusyId !== null"
            :label="buEditingId ? 'Save changes' : 'Create business unit'"
            @click="saveBuModal()"
          />
        </template>
      </UModal>

      <UModal
        v-model:open="testReadOpen"
        :title="testReadTitle"
        :ui="{ content: 'max-w-2xl' }"
      >
        <template #body>
          <p v-if="testReadError" class="test-read-err" role="alert">{{ testReadError }}</p>
          <p v-else-if="testReadBusyId !== null" class="muted">Reading mailbox…</p>
          <div v-else-if="testReadResult" class="test-read-body">
            <p class="muted">
              <strong>{{ testReadResult.mailbox }}</strong>
              — {{ testReadResult.count }} unread message{{ testReadResult.count === 1 ? '' : 's' }}
              (dry run).
            </p>
            <ul v-if="testReadResult.messages.length" class="test-read-list">
              <li v-for="(m, idx) in testReadResult.messages" :key="idx">
                <strong>{{ m.subject || '(no subject)' }}</strong>
                <span class="meta">
                  {{ m.from_name || m.from_email || 'Unknown sender' }}
                  <template v-if="m.from_email && m.from_name"> · {{ m.from_email }}</template>
                  <template v-if="m.received_at"> · {{ m.received_at }}</template>
                  <template v-if="m.already_imported"> · already imported</template>
                </span>
                <span v-if="m.preview" class="preview">{{ m.preview }}</span>
              </li>
            </ul>
            <p v-else class="muted">No unread messages in Inbox.</p>
          </div>
        </template>
        <template #footer>
          <UButton color="neutral" variant="outline" label="Close" @click="testReadOpen = false" />
        </template>
      </UModal>
    </div>
  </section>
</template>

<style scoped>
.panel h2 {
  font-size: 1.1rem;
  margin: 0 0 0.35rem;
  font-weight: 700;
}
.hint {
  color: var(--cdc-ink-muted, #3d5247);
  font-size: 0.88rem;
  margin: 0 0 1rem;
  line-height: 1.55;
  max-width: 52rem;
}
.new-card { margin-bottom: 1rem; }
.cat-table-card { overflow: hidden; }
.cat-table :deep(.cat-name-input) { min-width: 180px; }
.cat-table :deep(.cat-order-input) {
  min-width: 4.5rem;
  width: 5rem;
  max-width: 6rem;
}
.cat-table :deep(.cat-order-input input) {
  text-align: center;
  min-width: 3rem;
}
.cat-table :deep(.cat-ai-desc) { min-width: 200px; }
.cat-table :deep(.bu-desc) { min-width: 220px; }
.actions { display: flex; gap: 0.35rem; flex-wrap: wrap; justify-content: flex-end; }
.muted { color: #64748b; }
.bu-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}
.bu-toolbar-hint {
  margin: 0;
  flex: 1;
  min-width: 14rem;
  font-size: 0.88rem;
  line-height: 1.45;
}
.bu-slug {
  font-size: 0.75rem;
  color: #768b9e;
  font-weight: 400;
}
.priority-pill {
  display: inline-block;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
  background: #eef5f9;
  color: #3a4752;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
}
.intake-pill {
  display: inline-block;
  margin-left: 0.35rem;
  font-size: 0.68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  background: #dcfce7;
  color: #166534;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
}
.bu-cats { display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; }
.bu-cat-count { font-size: 0.75rem; color: #768b9e; margin-right: 0.25rem; }
.bu-cat-chip {
  font-size: 0.72rem; background: #eef5f9; color: #3a4752;
  padding: 0.1rem 0.4rem; border-radius: 999px;
}
.bu-cat-more { font-size: 0.72rem; color: #768b9e; }
.test-read-hint { margin: 0.4rem 0 0; font-size: 0.8rem; line-height: 1.4; }
.test-read-err { margin: 0; color: #b91c1c; font-size: 0.9rem; }
.test-read-body { display: flex; flex-direction: column; gap: 0.75rem; }
.test-read-list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: 22rem;
  overflow: auto;
}
.test-read-list li {
  padding: 0.65rem 0.75rem;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #f8fafc;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.test-read-list .meta { font-size: 0.78rem; color: #64748b; }
.test-read-list .preview { font-size: 0.82rem; color: #475569; }
</style>
