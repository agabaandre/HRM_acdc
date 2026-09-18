<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import PortalPageChrome from '@/components/molecules/PortalPageChrome.vue'
import {
  fetchBrandingSettings,
  saveBrandingSettings,
  uploadBrandingLogo,
  uploadLoginBackground,
  type PortalBrandingSettings,
} from '@/lib/settingsApi'

const loading = ref(true)
const saving = ref(false)
const uploading = ref(false)
const uploadingBg = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)
const logoPreview = ref('')
const loginBgPreview = ref('')

const form = reactive({
  company_name: 'Africa CDC',
  system_logo: '',
  footer_copyright: '',
  print_footer: '',
  company_email: '',
  company_phone: '',
  company_website: '',
  company_address: '',
  login_welcome_title: 'Welcome Back',
  login_welcome_text: '',
  login_background: '',
})

function apply(data: PortalBrandingSettings) {
  form.company_name = data.company_name || 'Africa CDC'
  form.system_logo = data.system_logo || ''
  form.footer_copyright = data.footer_copyright || ''
  form.print_footer = data.print_footer || ''
  form.company_email = data.company_email || ''
  form.company_phone = data.company_phone || ''
  form.company_website = data.company_website || ''
  form.company_address = data.company_address || ''
  form.login_welcome_title = data.login_welcome_title || 'Welcome Back'
  form.login_welcome_text = data.login_welcome_text || ''
  form.login_background = data.login_background || ''
  logoPreview.value = data.logo_url || ''
  loginBgPreview.value = data.login_background_url || ''
}

async function load() {
  loading.value = true
  error.value = null
  try {
    apply(await fetchBrandingSettings())
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not load branding settings')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  error.value = null
  success.value = null
  try {
    apply(await saveBrandingSettings({ ...form }))
    success.value = 'Branding saved. Login page and CBP modules will pick this up on their next load.'
    const { useBrandingStore } = await import('@/stores/branding')
    await useBrandingStore().refresh()
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not save branding')
  } finally {
    saving.value = false
  }
}

async function onLogoSelected(files: File | File[] | null) {
  const file = Array.isArray(files) ? files[0] : files
  if (!file) return
  uploading.value = true
  error.value = null
  success.value = null
  try {
    apply(await uploadBrandingLogo(file))
    success.value = 'Logo uploaded.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not upload logo')
  } finally {
    uploading.value = false
  }
}

async function onLoginBgSelected(files: File | File[] | null) {
  const file = Array.isArray(files) ? files[0] : files
  if (!file) return
  uploadingBg.value = true
  error.value = null
  success.value = null
  try {
    apply(await uploadLoginBackground(file))
    success.value = 'Login background uploaded.'
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not upload login background')
  } finally {
    uploadingBg.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PortalPageChrome
      title="Branding"
      lede="Company name, logo, login welcome, copyright, contacts, and mPDF print footer used across Staff Portal and other CBP modules."
    >
      <template #actions>
        <RouterLink to="/settings" class="text-decoration-none me-2">
          <v-btn variant="text">Back</v-btn>
        </RouterLink>
        <v-btn color="primary" :loading="saving" :disabled="loading" @click="save">Save</v-btn>
      </template>
    </PortalPageChrome>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3" density="compact">{{ error }}</v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-3" density="compact">{{ success }}</v-alert>
    <div v-if="loading" class="text-medium-emphasis">Loading…</div>

    <v-row v-else>
      <v-col cols="12" md="7">
        <v-card variant="outlined" class="mb-4">
          <v-card-title class="text-subtitle-1">Organisation</v-card-title>
          <v-card-text>
            <v-text-field v-model="form.company_name" label="Company name" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.company_email" label="Company email" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.company_phone" label="Company phone" density="comfortable" class="mb-2" />
            <v-text-field v-model="form.company_website" label="Company website" density="comfortable" class="mb-2" />
            <v-textarea v-model="form.company_address" label="Company address / contacts" rows="4" auto-grow />
          </v-card-text>
        </v-card>

        <v-card variant="outlined" class="mb-4">
          <v-card-title class="text-subtitle-1">Login welcome</v-card-title>
          <v-card-text>
            <v-text-field
              v-model="form.login_welcome_title"
              label="Welcome title"
              hint="Shown on the left panel of /staff/login"
              persistent-hint
              density="comfortable"
              class="mb-4"
            />
            <v-textarea
              v-model="form.login_welcome_text"
              label="Welcome text"
              hint="Supporting paragraph under the welcome title"
              persistent-hint
              rows="4"
              auto-grow
              class="mb-4"
            />
            <v-text-field
              v-model="form.login_background"
              label="Login background path or URL"
              density="comfortable"
              class="mb-3"
              hint="Full-page background behind the login card"
              persistent-hint
            />
            <div
              class="mb-3 d-flex align-center justify-center"
              style="min-height: 120px; background: #0f172a; border-radius: 8px; overflow: hidden;"
            >
              <img
                v-if="loginBgPreview"
                :src="loginBgPreview"
                alt="Login background preview"
                style="width: 100%; max-height: 160px; object-fit: cover;"
              />
              <span v-else class="text-medium-emphasis">No background</span>
            </div>
            <v-file-input
              label="Upload login background"
              accept="image/*"
              prepend-icon="mdi-image"
              density="comfortable"
              :loading="uploadingBg"
              show-size
              @update:model-value="onLoginBgSelected"
            />
          </v-card-text>
        </v-card>

        <v-card variant="outlined" class="mb-4">
          <v-card-title class="text-subtitle-1">Footers</v-card-title>
          <v-card-text>
            <v-text-field
              v-model="form.footer_copyright"
              label="Page copyright"
              hint="Use {year} for the current year. Shown in module footers."
              persistent-hint
              density="comfortable"
              class="mb-4"
            />
            <v-textarea
              v-model="form.print_footer"
              label="Print footer (mPDF)"
              hint="Address block used on PDF printouts. Email/phone from contacts above are appended when present."
              persistent-hint
              rows="5"
              auto-grow
            />
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card variant="outlined">
          <v-card-title class="text-subtitle-1">System logo</v-card-title>
          <v-card-text>
            <div class="mb-3 d-flex align-center justify-center" style="min-height: 96px; background: #f8fafc; border-radius: 8px;">
              <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" style="max-height: 80px; max-width: 100%;" />
              <span v-else class="text-medium-emphasis">No logo</span>
            </div>
            <v-text-field
              v-model="form.system_logo"
              label="Logo path or URL"
              density="comfortable"
              class="mb-3"
              hint="Site-relative path (e.g. /assets/images/…) or absolute URL"
              persistent-hint
            />
            <v-file-input
              label="Upload new logo"
              accept="image/*"
              prepend-icon="mdi-upload"
              density="comfortable"
              :loading="uploading"
              show-size
              @update:model-value="onLogoSelected"
            />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>
