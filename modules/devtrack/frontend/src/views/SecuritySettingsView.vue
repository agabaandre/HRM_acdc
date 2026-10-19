<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import PageHeader from '@/components/shared/PageHeader.vue'
import ParentCard from '@/components/shared/ParentCard.vue'
import { apiErrorMessage } from '@/lib/apiError'
import { useAuthStore, type TwoFactorStatus } from '@/stores/auth'

const auth = useAuthStore()

const status = ref<TwoFactorStatus | null>(null)
const loading = ref(true)
const message = ref('')
const error = ref('')

const emailPassword = ref('')
const totpPassword = ref('')
const disableEmailPassword = ref('')
const disableTotpPassword = ref('')
const totpCode = ref('')

const totpSetup = ref<{ secret: string; otpauth_url: string } | null>(null)
const recoveryCodes = ref<string[]>([])
const qrDataUrl = ref('')

const emailBusy = ref(false)
const totpBusy = ref(false)

const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirmation = ref('')
const passwordBusy = ref(false)

async function loadStatus() {
  loading.value = true
  try {
    status.value = await auth.fetch2faStatus()
  } finally {
    loading.value = false
  }
}

async function changePassword() {
  passwordBusy.value = true
  error.value = ''
  message.value = ''
  try {
    message.value = await auth.changePassword({
      current_password: currentPassword.value,
      password: newPassword.value,
      password_confirmation: newPasswordConfirmation.value,
    })
    currentPassword.value = ''
    newPassword.value = ''
    newPasswordConfirmation.value = ''
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not change password. Check your current password and try again.')
  } finally {
    passwordBusy.value = false
  }
}

async function enableEmail() {
  emailBusy.value = true
  error.value = ''
  message.value = ''
  try {
    const result = await auth.enableEmail2fa(emailPassword.value)
    status.value = result.data
    message.value = result.message
    emailPassword.value = ''
  } catch {
    error.value = 'Could not enable email verification. Check your password and try again.'
  } finally {
    emailBusy.value = false
  }
}

async function disableEmail() {
  emailBusy.value = true
  error.value = ''
  message.value = ''
  try {
    const result = await auth.disableEmail2fa(disableEmailPassword.value)
    status.value = result.data
    message.value = result.message
    disableEmailPassword.value = ''
  } catch {
    error.value = 'Could not disable email verification. Check your password and try again.'
  } finally {
    emailBusy.value = false
  }
}

async function startTotpSetup() {
  totpBusy.value = true
  error.value = ''
  message.value = ''
  recoveryCodes.value = []
  qrDataUrl.value = ''
  try {
    totpSetup.value = await auth.setupTotp(status.value?.must_setup_totp ? undefined : totpPassword.value)
    totpPassword.value = ''
    const QRCode = await import('qrcode')
    qrDataUrl.value = await QRCode.toDataURL(totpSetup.value.otpauth_url, { margin: 1, width: 220 })
  } catch {
    error.value = 'Could not start authenticator setup. Check your password and try again.'
    totpSetup.value = null
  } finally {
    totpBusy.value = false
  }
}

async function confirmTotp() {
  totpBusy.value = true
  error.value = ''
  message.value = ''
  try {
    const result = await auth.confirmTotp(totpCode.value)
    status.value = result.data
    recoveryCodes.value = result.data.recovery_codes ?? []
    message.value = result.message
    totpSetup.value = null
    totpCode.value = ''
    qrDataUrl.value = ''
  } catch {
    error.value = 'The authenticator code is invalid. Try again.'
  } finally {
    totpBusy.value = false
  }
}

async function disableTotp() {
  totpBusy.value = true
  error.value = ''
  message.value = ''
  try {
    const result = await auth.disableTotp(disableTotpPassword.value)
    status.value = result.data
    message.value = result.message
    disableTotpPassword.value = ''
    recoveryCodes.value = []
    totpSetup.value = null
  } catch {
    error.value = 'Could not disable authenticator verification. Check your password and try again.'
  } finally {
    totpBusy.value = false
  }
}

onMounted(loadStatus)
</script>

<template>
  <div>
    <PageHeader
      title="Security"
      subtitle="Change your password and manage optional two-factor sign-in"
    />

    <v-alert v-if="message" type="success" variant="tonal" class="mb-4">{{ message }}</v-alert>
    <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>

    <ParentCard title="Change password" class="mb-4">
      <p class="text-body-2 text-medium-emphasis mb-4">
        Update the password you use to sign in. Other sessions will be signed out.
      </p>
      <v-row>
        <v-col cols="12" md="4">
          <FormField label="Current password" required>
            <v-text-field
              v-model="currentPassword"
              type="password"
              variant="outlined"
              hide-details
              autocomplete="current-password"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="4">
          <FormField label="New password" required>
            <v-text-field
              v-model="newPassword"
              type="password"
              variant="outlined"
              hide-details
              autocomplete="new-password"
            />
          </FormField>
        </v-col>
        <v-col cols="12" md="4">
          <FormField label="Confirm new password" required>
            <v-text-field
              v-model="newPasswordConfirmation"
              type="password"
              variant="outlined"
              hide-details
              autocomplete="new-password"
            />
          </FormField>
        </v-col>
      </v-row>
      <v-btn color="primary" class="mt-4" :loading="passwordBusy" @click="changePassword">
        Update password
      </v-btn>
    </ParentCard>

    <v-row v-if="loading">
      <v-col cols="12"><v-skeleton-loader type="card" /></v-col>
    </v-row>

    <v-row v-else>
      <v-col cols="12" lg="6">
        <ParentCard title="Email verification">
          <p class="text-body-2 text-medium-emphasis mb-4">
            When enabled, a 6-digit code is emailed after you enter your password at sign-in.
          </p>

          <v-chip
            :color="status?.two_factor_email_enabled ? 'success' : 'default'"
            variant="tonal"
            class="mb-4"
          >
            {{ status?.two_factor_email_enabled ? 'Enabled' : 'Disabled' }}
          </v-chip>

          <template v-if="!status?.two_factor_email_enabled">
            <FormField label="Confirm password to enable" required>
              <v-text-field
                v-model="emailPassword"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="current-password"
              />
            </FormField>
            <v-btn color="primary" class="mt-4" :loading="emailBusy" @click="enableEmail">
              Enable email verification
            </v-btn>
          </template>

          <template v-else>
            <FormField label="Confirm password to disable" required>
              <v-text-field
                v-model="disableEmailPassword"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="current-password"
              />
            </FormField>
            <v-btn color="error" variant="tonal" class="mt-4" :loading="emailBusy" @click="disableEmail">
              Disable email verification
            </v-btn>
          </template>
        </ParentCard>
      </v-col>

      <v-col cols="12" lg="6">
        <ParentCard title="Authenticator app">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Use Google Authenticator, Microsoft Authenticator, or any compatible TOTP app.
          </p>

          <v-chip
            :color="status?.two_factor_totp_enabled ? 'success' : 'default'"
            variant="tonal"
            class="mb-4"
          >
            {{ status?.two_factor_totp_enabled ? 'Enabled' : 'Disabled' }}
          </v-chip>
          <v-alert
            v-if="status?.totp_required"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Authenticator app is required for this account and cannot be disabled.
          </v-alert>

          <template v-if="!status?.two_factor_totp_enabled && !totpSetup">
            <FormField v-if="!status?.must_setup_totp" label="Confirm password to set up" required>
              <v-text-field
                v-model="totpPassword"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="current-password"
              />
            </FormField>
            <v-btn color="primary" class="mt-4" :loading="totpBusy" @click="startTotpSetup">
              Set up authenticator app
            </v-btn>
          </template>

          <template v-else-if="totpSetup">
            <div class="text-center mb-4">
              <img v-if="qrDataUrl" :src="qrDataUrl" alt="Authenticator QR code" class="mb-3" />
              <div class="text-caption text-medium-emphasis mb-2">Or enter this secret manually:</div>
              <code class="d-inline-block pa-2 rounded theme-border">{{ totpSetup.secret }}</code>
            </div>
            <FormField label="Enter code from your app" required>
              <v-text-field
                v-model="totpCode"
                variant="outlined"
                hide-details
                maxlength="6"
                inputmode="numeric"
              />
            </FormField>
            <v-btn color="primary" class="mt-4" :loading="totpBusy" @click="confirmTotp">
              Confirm and enable
            </v-btn>
          </template>

          <template v-else-if="!status?.totp_required">
            <FormField label="Confirm password to disable" required>
              <v-text-field
                v-model="disableTotpPassword"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="current-password"
              />
            </FormField>
            <v-btn color="error" variant="tonal" class="mt-4" :loading="totpBusy" @click="disableTotp">
              Disable authenticator app
            </v-btn>
          </template>
        </ParentCard>
      </v-col>
    </v-row>

    <ParentCard v-if="recoveryCodes.length" title="Recovery codes" class="mt-2">
      <p class="text-body-2 text-medium-emphasis mb-4">
        Save these one-time recovery codes in a secure place. Each code can be used once if you lose access to your authenticator app.
      </p>
      <v-row dense>
        <v-col v-for="code in recoveryCodes" :key="code" cols="12" sm="6" md="3">
          <code class="d-block pa-3 rounded theme-border text-center">{{ code }}</code>
        </v-col>
      </v-row>
    </ParentCard>
  </div>
</template>
