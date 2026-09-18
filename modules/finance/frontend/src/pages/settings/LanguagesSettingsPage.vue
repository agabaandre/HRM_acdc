<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import {
  createAdminLanguage,
  deleteAdminLanguage,
  fetchAdminLanguages,
  fetchTranslationGrid,
  fillTranslationsWithAi,
  saveTranslationGrid,
  updateAdminLanguage,
  type PortalLanguageAdminRow,
} from '@/lib/languagesApi'
import { useLocaleStore } from '@/stores/locale'

const localeStore = useLocaleStore()
const tab = ref<'languages' | 'translations'>('languages')
const loading = ref(false)
const saving = ref(false)
const fillingAi = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const languages = ref<PortalLanguageAdminRow[]>([])
const groups = ref<Record<string, string>>({})
const editingId = ref<number | null>(null)

const createForm = reactive({
  locale_code: '',
  name: '',
  google_translate_code: '',
  flag_emoji: '',
  sort_order: 100,
  is_active: true,
})

const editForm = reactive({
  name: '',
  google_translate_code: '',
  flag_emoji: '',
  sort_order: 0,
  is_active: true,
})

const gridLocale = ref('en')
const gridGroup = ref('nav')
const gridLocales = ref<string[]>([])
const localeLabels = ref<Record<string, { name: string; flag: string }>>({})
const english = ref<Record<string, string>>({})
const lines = ref<Record<string, string>>({})
const gridLoading = ref(false)

const groupItems = computed(() =>
  Object.entries(groups.value).map(([value, title]) => ({ title, value })),
)

const localeItems = computed(() =>
  gridLocales.value.map((code) => ({
    title: `${localeLabels.value[code]?.flag || ''} ${code.toUpperCase()} — ${localeLabels.value[code]?.name || code}`.trim(),
    value: code,
  })),
)

const translationRows = computed(() =>
  Object.keys(english.value).map((key) => ({
    key,
    english: english.value[key] || '',
    value: lines.value[key] || '',
  })),
)

async function loadLanguages() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchAdminLanguages()
    languages.value = data.languages
    groups.value = data.groups
    if (!gridGroup.value || !groups.value[gridGroup.value]) {
      gridGroup.value = Object.keys(groups.value)[0] || 'nav'
    }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load languages')
  } finally {
    loading.value = false
  }
}

async function loadGrid() {
  if (!gridLocale.value || !gridGroup.value) return
  gridLoading.value = true
  error.value = null
  try {
    const data = await fetchTranslationGrid(gridLocale.value, gridGroup.value)
    gridLocales.value = data.locales
    localeLabels.value = data.locale_labels
    groups.value = data.groups
    gridLocale.value = data.locale
    gridGroup.value = data.group
    english.value = data.english
    lines.value = { ...data.lines }
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load translations')
  } finally {
    gridLoading.value = false
  }
}

async function onCreate() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    await createAdminLanguage({ ...createForm })
    createForm.locale_code = ''
    createForm.name = ''
    createForm.google_translate_code = ''
    createForm.flag_emoji = ''
    createForm.sort_order = 100
    createForm.is_active = true
    success.value = 'Language added.'
    await loadLanguages()
    await localeStore.bootstrap()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not add language')
  } finally {
    saving.value = false
  }
}

function startEdit(row: PortalLanguageAdminRow) {
  editingId.value = row.id
  editForm.name = row.name
  editForm.google_translate_code = row.google_translate_code || ''
  editForm.flag_emoji = row.flag_emoji || ''
  editForm.sort_order = row.sort_order
  editForm.is_active = row.is_active
}

async function onUpdate() {
  if (!editingId.value) return
  saving.value = true
  error.value = null
  success.value = null
  try {
    await updateAdminLanguage(editingId.value, { ...editForm })
    editingId.value = null
    success.value = 'Language updated.'
    await loadLanguages()
    await localeStore.bootstrap()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not update language')
  } finally {
    saving.value = false
  }
}

async function onDelete(row: PortalLanguageAdminRow) {
  if (row.locale_code === 'en') return
  if (!window.confirm(`Delete ${row.name}?`)) return
  saving.value = true
  error.value = null
  try {
    await deleteAdminLanguage(row.id)
    success.value = 'Language removed.'
    await loadLanguages()
    await localeStore.bootstrap()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not delete language')
  } finally {
    saving.value = false
  }
}

async function onSaveTranslations() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    const data = await saveTranslationGrid(gridLocale.value, gridGroup.value, { ...lines.value })
    lines.value = { ...data.lines }
    success.value = 'Translations saved.'
    await localeStore.bootstrap()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save translations')
  } finally {
    saving.value = false
  }
}

async function onFillWithAi() {
  fillingAi.value = true
  error.value = null
  success.value = null
  try {
    const data = await fillTranslationsWithAi(gridLocale.value, gridGroup.value)
    lines.value = { ...data.lines }
    success.value = 'AI suggestions filled. Review and save to keep them.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not fill translations with AI')
  } finally {
    fillingAi.value = false
  }
}

watch([gridLocale, gridGroup], () => {
  if (tab.value === 'translations') void loadGrid()
})

watch(tab, (next) => {
  if (next === 'translations') void loadGrid()
})

onMounted(async () => {
  await loadLanguages()
  if (languages.value[0]?.locale_code) {
    gridLocale.value = languages.value[0].locale_code
  }
})
</script>

<template>
  <div>
    <CbpPageHeading
      :title="localeStore.t('settings.card_languages', 'Languages')"
      back-to="/settings"
      :back-label="localeStore.t('settings.back_arrow', '← Back to settings')"
    />
    <p class="text-body-2 text-medium-emphasis mb-4">
      {{ localeStore.t('settings.languages_lede', 'Manage AU working languages and translate Staff Portal menu keywords.') }}
    </p>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">
      {{ error }}
    </v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = null">
      {{ success }}
    </v-alert>

    <v-tabs v-model="tab" color="primary" class="mb-4">
      <v-tab value="languages">{{ localeStore.t('settings.tab_languages', 'Languages') }}</v-tab>
      <v-tab value="translations">{{ localeStore.t('settings.tab_translations', 'Menu translations') }}</v-tab>
    </v-tabs>

    <div v-if="loading" class="text-medium-emphasis">{{ localeStore.t('actions.loading', 'Loading…') }}</div>

    <template v-else-if="tab === 'languages'">
      <v-card variant="outlined" class="mb-4">
        <v-card-title class="text-subtitle-1">{{ localeStore.t('settings.add_language', 'Add language') }}</v-card-title>
        <v-card-text>
          <p class="text-medium-emphasis text-body-2 mb-4">
            Locale code is stored on staff profiles (for example <code>pt</code> or <code>zh-cn</code>).
            Seeded languages are the African Union working languages: English, French, Arabic, Spanish, Portuguese, and Kiswahili.
          </p>
          <v-form @submit.prevent="onCreate">
            <v-row density="compact">
              <v-col cols="12" sm="2">
                <v-text-field v-model="createForm.locale_code" label="Locale code" placeholder="de" hide-details density="compact" required />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field v-model="createForm.name" label="Display name" hide-details density="compact" required />
              </v-col>
              <v-col cols="12" sm="2">
                <v-text-field v-model="createForm.google_translate_code" label="Google code" hide-details density="compact" />
              </v-col>
              <v-col cols="12" sm="1">
                <v-text-field v-model="createForm.flag_emoji" label="Flag" hide-details density="compact" />
              </v-col>
              <v-col cols="12" sm="1">
                <v-text-field v-model.number="createForm.sort_order" label="Sort" type="number" hide-details density="compact" />
              </v-col>
              <v-col cols="12" sm="2" class="d-flex align-center">
                <v-checkbox v-model="createForm.is_active" :label="localeStore.t('actions.active', 'Active')" hide-details density="compact" />
              </v-col>
              <v-col cols="12" sm="1" class="d-flex align-center">
                <v-btn type="submit" color="primary" size="small" :loading="saving">{{ localeStore.t('actions.save', 'Save') }}</v-btn>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
      </v-card>

      <v-card variant="outlined">
        <v-card-title class="text-subtitle-1">{{ localeStore.t('settings.configured_languages', 'Configured languages') }}</v-card-title>
        <v-table density="compact">
          <thead>
            <tr>
              <th>#</th>
              <th>Locale</th>
              <th>Name</th>
              <th>Google code</th>
              <th>Flag</th>
              <th>Sort</th>
              <th>{{ localeStore.t('actions.active', 'Active') }}</th>
              <th class="text-end">{{ localeStore.t('actions.actions', 'Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in languages" :key="row.id">
              <td class="text-medium-emphasis">{{ idx + 1 }}</td>
              <td><code>{{ row.locale_code }}</code></td>
              <td>{{ row.name }}</td>
              <td><code>{{ row.google_translate_code || '—' }}</code></td>
              <td>{{ row.flag_emoji }}</td>
              <td>{{ row.sort_order }}</td>
              <td>{{ row.is_active ? localeStore.t('actions.yes', 'Yes') : localeStore.t('actions.no', 'No') }}</td>
              <td class="text-end">
                <v-btn size="x-small" variant="outlined" class="me-1" @click="startEdit(row)">{{ localeStore.t('actions.edit', 'Edit') }}</v-btn>
                <v-btn
                  v-if="row.locale_code !== 'en'"
                  size="x-small"
                  variant="outlined"
                  color="error"
                  :disabled="saving"
                  @click="onDelete(row)"
                >
                  {{ localeStore.t('actions.delete', 'Delete') }}
                </v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-card>

      <v-dialog :model-value="editingId !== null" max-width="480" @update:model-value="(v) => { if (!v) editingId = null }">
        <v-card>
          <v-card-title>{{ localeStore.t('settings.edit_language', 'Edit language') }}</v-card-title>
          <v-card-text>
            <v-text-field v-model="editForm.name" label="Display name" class="mb-2" />
            <v-text-field v-model="editForm.google_translate_code" label="Google Translate code" class="mb-2" />
            <v-text-field v-model="editForm.flag_emoji" label="Flag emoji" class="mb-2" />
            <v-text-field v-model.number="editForm.sort_order" label="Sort" type="number" class="mb-2" />
            <v-checkbox v-model="editForm.is_active" :label="localeStore.t('actions.active', 'Active')" hide-details />
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="editingId = null">{{ localeStore.t('actions.cancel', 'Cancel') }}</v-btn>
            <v-btn color="primary" :loading="saving" @click="onUpdate">{{ localeStore.t('actions.save', 'Save') }}</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>

    <template v-else>
      <v-row class="mb-3" density="compact">
        <v-col cols="12" md="4">
          <v-select v-model="gridLocale" :items="localeItems" :label="localeStore.t('settings.locale', 'Locale')" hide-details density="compact" />
        </v-col>
        <v-col cols="12" md="5">
          <v-select v-model="gridGroup" :items="groupItems" :label="localeStore.t('settings.section', 'Section')" hide-details density="compact" />
        </v-col>
        <v-col cols="12" md="3" class="d-flex align-center ga-2">
          <v-btn
            variant="outlined"
            size="small"
            :loading="fillingAi"
            :disabled="gridLoading || saving"
            @click="onFillWithAi"
          >
            {{ localeStore.t('settings.translate_with_ai', 'Fill with AI') }}
          </v-btn>
          <v-btn color="primary" size="small" :loading="saving" :disabled="gridLoading || fillingAi" @click="onSaveTranslations">
            {{ localeStore.t('actions.save', 'Save') }}
          </v-btn>
        </v-col>
      </v-row>

      <v-card variant="outlined">
        <v-card-title class="text-subtitle-1">
          {{ groups[gridGroup] || gridGroup }}
        </v-card-title>
        <div v-if="gridLoading" class="pa-4 text-medium-emphasis">{{ localeStore.t('settings.loading_translations', 'Loading translations…') }}</div>
        <v-table v-else density="compact">
          <thead>
            <tr>
              <th style="width: 3rem">#</th>
              <th>{{ localeStore.t('settings.col_key', 'Key') }}</th>
              <th>{{ localeStore.t('settings.col_english', 'English (reference)') }}</th>
              <th>{{ localeStore.t('settings.col_translation', '{locale} translation', { locale: gridLocale.toUpperCase() }) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in translationRows" :key="row.key">
              <td class="text-medium-emphasis">{{ idx + 1 }}</td>
              <td><code class="text-caption">{{ row.key }}</code></td>
              <td class="text-medium-emphasis">{{ row.english }}</td>
              <td>
                <v-text-field
                  v-model="lines[row.key]"
                  density="compact"
                  hide-details
                  variant="outlined"
                />
              </td>
            </tr>
          </tbody>
        </v-table>
        <v-card-actions>
          <v-spacer />
          <v-btn color="primary" :loading="saving" :disabled="fillingAi" @click="onSaveTranslations">
            {{ localeStore.t('settings.save_translations', 'Save translations') }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </div>
</template>
