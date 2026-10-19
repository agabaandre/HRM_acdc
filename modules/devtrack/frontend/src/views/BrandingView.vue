<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { useBrandingStore } from '@/stores/branding'

const branding = useBrandingStore()
const saving = ref(false)
const message = ref('')

const form = ref({
  app_name: '',
  tagline: '',
  primary_color: '#0d7a3a',
  secondary_color: '#c9a227',
  support_email: '',
  admin_logo_inverse: false,
  admin_logo_size_percent: 100,
})

const logoFile = ref<File | null>(null)
const logoDarkFile = ref<File | null>(null)
const logoPreview = ref<string | null>(null)

function onLogoChange(files: File[] | null) {
  logoFile.value = files?.[0] ?? null
  if (logoFile.value) logoPreview.value = URL.createObjectURL(logoFile.value)
}

async function save() {
  saving.value = true
  message.value = ''
  try {
    const fd = new FormData()
    fd.append('app_name', form.value.app_name)
    fd.append('tagline', form.value.tagline)
    fd.append('primary_color', form.value.primary_color)
    fd.append('secondary_color', form.value.secondary_color)
    fd.append('admin_logo_inverse', form.value.admin_logo_inverse ? '1' : '0')
    fd.append('admin_logo_size_percent', String(form.value.admin_logo_size_percent))
    if (form.value.support_email) fd.append('support_email', form.value.support_email)
    if (logoFile.value) fd.append('logo', logoFile.value)
    if (logoDarkFile.value) fd.append('logo_dark', logoDarkFile.value)
    await branding.update(fd)
    message.value = 'Branding saved.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!branding.loaded) await branding.fetch()
  form.value = {
    app_name: branding.branding.app_name,
    tagline: branding.branding.tagline ?? '',
    primary_color: branding.branding.primary_color,
    secondary_color: branding.branding.secondary_color,
    support_email: branding.branding.support_email ?? '',
    admin_logo_inverse: branding.branding.admin_logo_inverse,
    admin_logo_size_percent: branding.branding.admin_logo_size_percent,
  }
  logoPreview.value = branding.branding.logo_url
})
</script>

<template>
  <div>
    <PageHeader title="Branding" subtitle="Logo, colours, and application identity" />

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4">{{ message }}</v-alert>

    <ParentCard title="Brand settings">
      <v-row>
        <v-col cols="12" md="6" class="form-stack">
          <FormField label="Application name" required>
            <v-text-field v-model="form.app_name" variant="outlined" hide-details />
          </FormField>
          <FormField label="Tagline">
            <v-text-field v-model="form.tagline" variant="outlined" hide-details />
          </FormField>
          <FormField label="Support email">
            <v-text-field v-model="form.support_email" type="email" variant="outlined" hide-details />
          </FormField>
          <FormField label="Primary colour">
            <v-text-field v-model="form.primary_color" type="color" variant="outlined" hide-details />
          </FormField>
          <FormField label="Secondary colour">
            <v-text-field v-model="form.secondary_color" type="color" variant="outlined" hide-details />
          </FormField>
        </v-col>
        <v-col cols="12" md="6" class="form-stack">
          <FormField label="Logo">
            <v-file-input
              accept="image/*"
              variant="outlined"
              hide-details
              prepend-icon="mdi-image"
              label="Upload logo"
              @update:model-value="onLogoChange"
            />
          </FormField>
          <div v-if="logoPreview" class="mb-4 pa-4 rounded theme-border">
            <img :src="logoPreview" alt="Logo preview" height="64" style="max-width: 100%; object-fit: contain" />
          </div>
          <FormField label="Dark mode logo (optional)">
            <v-file-input
              accept="image/*"
              variant="outlined"
              hide-details
              prepend-icon="mdi-image"
              label="Upload dark logo"
              @update:model-value="(f) => (logoDarkFile = f?.[0] ?? null)"
            />
          </FormField>
          <FormField label="Admin header logo">
            <v-switch
              v-model="form.admin_logo_inverse"
              label="Invert logo on admin top bar"
              color="primary"
              hide-details
            />
            <div class="text-caption text-medium-emphasis mt-1">
              Makes the logo white on the coloured admin header. Login page is unchanged.
            </div>
          </FormField>
          <FormField :label="`Admin logo size (${form.admin_logo_size_percent}%)`">
            <v-slider
              v-model="form.admin_logo_size_percent"
              :min="50"
              :max="200"
              :step="5"
              color="primary"
              thumb-label
              hide-details
            />
            <div class="text-caption text-medium-emphasis mt-1">
              Adjusts logo height in the admin top bar (50%–200% of the default size).
            </div>
          </FormField>
          <div
            v-if="logoPreview"
            class="admin-logo-preview pa-4 rounded"
            :style="{ backgroundColor: form.primary_color }"
          >
            <div class="text-caption text-white mb-2">Admin header preview</div>
            <img
              :src="logoPreview"
              alt="Admin header logo preview"
              class="admin-logo-preview__image"
              :class="{ 'admin-logo-preview__image--inverse': form.admin_logo_inverse }"
              :style="{ height: `${Math.round(40 * (form.admin_logo_size_percent / 100))}px` }"
            />
          </div>
        </v-col>
      </v-row>
      <div class="d-flex ga-2 mt-4">
        <v-btn color="primary" :loading="saving" @click="save">Save branding</v-btn>
      </div>
    </ParentCard>
  </div>
</template>

<style scoped>
.admin-logo-preview__image {
  max-width: 200px;
  object-fit: contain;
}

.admin-logo-preview__image--inverse {
  filter: brightness(0) invert(1);
}
</style>
