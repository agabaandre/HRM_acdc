<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalBtn from '@/components/molecules/PortalBtn.vue'
import { fetchWorkplanPraSettings, saveWorkplanPraSettings } from '@/lib/settingsApi'

const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const apiKeySet = ref(false)

const form = reactive({
  base_url: '',
  api_key: '',
  tiers: '3,4',
  fiscal_year: '' as string | number,
  divisions: '',
  division_aliases: '',
  timeout: 60,
})

async function load() {
  loading.value = true
  error.value = null
  try {
    const data = await fetchWorkplanPraSettings()
    form.base_url = data.base_url || ''
    form.api_key = ''
    form.tiers = data.tiers || '3,4'
    form.fiscal_year = data.fiscal_year ?? ''
    form.divisions = data.divisions || ''
    form.division_aliases = data.division_aliases || ''
    form.timeout = data.timeout || 60
    apiKeySet.value = data.api_key_set
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load PRA settings')
  } finally {
    loading.value = false
  }
}

async function onSave() {
  saving.value = true
  success.value = null
  error.value = null
  try {
    const fiscal =
      form.fiscal_year === '' || form.fiscal_year == null ? null : Number(form.fiscal_year)
    const res = await saveWorkplanPraSettings({
      base_url: form.base_url.trim(),
      api_key: form.api_key,
      tiers: form.tiers,
      fiscal_year: Number.isFinite(fiscal) ? fiscal : null,
      divisions: form.divisions,
      division_aliases: form.division_aliases,
      timeout: Number(form.timeout) || 60,
    })
    success.value = res.message
    form.api_key = ''
    apiKeySet.value = res.data.api_key_set
    form.base_url = res.data.base_url
    form.tiers = res.data.tiers
    form.fiscal_year = res.data.fiscal_year ?? ''
    form.divisions = res.data.divisions
    form.division_aliases = res.data.division_aliases
    form.timeout = res.data.timeout
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save PRA settings')
  } finally {
    saving.value = false
  }
}

onMounted(() => void load())
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-3 flex-wrap ga-2">
      <CbpPageHeading
        title="Workplan / PRA"
        subtitle="API used by Sync from PRA and the daily staff job workplan:sync-pra."
      />
      <div class="d-flex ga-2">
        <RouterLink to="/workplan" style="text-decoration: none">
          <PortalBtn variant="outlined" color="secondary">Workplan</PortalBtn>
        </RouterLink>
        <RouterLink to="/settings" style="text-decoration: none">
          <PortalBtn variant="outlined" color="secondary">Back to settings</PortalBtn>
        </RouterLink>
      </div>
    </div>

    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-card v-else variant="outlined">
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-4">
          Values saved here override
          <code>PRA_WORKPLAN_*</code>
          in <code>.env</code>. Leave the API key blank to keep the current key.
        </p>

        <v-text-field
          v-model="form.base_url"
          label="PRA API URL"
          hint="Public workplan endpoint, e.g. https://pra.africacdc.org/api/public/workplan"
          persistent-hint
          variant="outlined"
          density="comfortable"
          class="mb-3"
        />

        <v-text-field
          v-model="form.api_key"
          label="API key"
          type="password"
          autocomplete="new-password"
          :placeholder="apiKeySet ? 'Leave blank to keep the current key' : 'Required — from PRA'"
          :hint="apiKeySet ? 'A key is already saved (or set in .env). Enter a new value only to replace it.' : 'Sync stays disabled until a key is saved here or in .env.'"
          persistent-hint
          variant="outlined"
          density="comfortable"
          class="mb-3"
        />

        <v-row dense>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="form.tiers"
              label="Tiers"
              hint="Comma-separated PRA tiers. Default 3,4."
              persistent-hint
              variant="outlined"
              density="comfortable"
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="form.fiscal_year"
              type="number"
              label="Fiscal year"
              hint="Leave blank to always use the current calendar year at sync time."
              persistent-hint
              variant="outlined"
              density="comfortable"
              clearable
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model.number="form.timeout"
              type="number"
              min="10"
              max="300"
              label="Timeout (seconds)"
              hint="HTTP timeout, 10–300."
              persistent-hint
              variant="outlined"
              density="comfortable"
            />
          </v-col>
        </v-row>

        <v-text-field
          v-model="form.divisions"
          label="Divisions to sync"
          hint="PRA codes, comma-separated. Blank / * / all = every local division short name."
          persistent-hint
          variant="outlined"
          density="comfortable"
          class="mt-3 mb-3"
        />

        <v-text-field
          v-model="form.division_aliases"
          label="Division aliases"
          hint="PRA code → local short name, e.g. MIS:DHIS"
          persistent-hint
          variant="outlined"
          density="comfortable"
          class="mb-2"
        />
      </v-card-text>
      <v-card-actions>
        <PortalBtn :loading="saving" @click="onSave">Save PRA settings</PortalBtn>
      </v-card-actions>
    </v-card>
  </div>
</template>
