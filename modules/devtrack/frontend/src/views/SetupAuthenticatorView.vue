<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import QRCode from 'qrcode'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppLogo from '@/components/layout/AppLogo.vue'
import FormField from '@/components/forms/FormField.vue'
import { apiErrorMessage } from '@/lib/apiError'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

const loading = ref(false)
const confirming = ref(false)
const error = ref('')
const message = ref('')
const totpSetup = ref<{ secret: string; otpauth_url: string } | null>(null)
const qrDataUrl = ref('')
const totpCode = ref('')
const recoveryCodes = ref<string[]>([])

async function startSetup() {
  loading.value = true
  error.value = ''
  message.value = ''
  try {
    totpSetup.value = await auth.setupTotp()
    qrDataUrl.value = await QRCode.toDataURL(totpSetup.value.otpauth_url, { margin: 1, width: 220 })
  } catch (err) {
    error.value = apiErrorMessage(err, 'Could not start authenticator setup.')
    totpSetup.value = null
    qrDataUrl.value = ''
  } finally {
    loading.value = false
  }
}

async function confirm() {
  confirming.value = true
  error.value = ''
  message.value = ''
  try {
    const result = await auth.confirmTotp(totpCode.value.trim())
    recoveryCodes.value = result.data.recovery_codes ?? []
    message.value = result.message || 'Authenticator enabled.'
    totpSetup.value = null
    totpCode.value = ''
    if (recoveryCodes.value.length === 0) {
      await router.replace({ name: 'dashboard' })
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Invalid authenticator code. Try again.')
  } finally {
    confirming.value = false
  }
}

async function continueToApp() {
  await router.replace({ name: 'dashboard' })
}

async function logout() {
  await auth.logout()
  await router.replace({ name: 'login' })
}

onMounted(() => {
  if (!auth.mustSetupTotp) {
    void router.replace({ name: 'dashboard' })
    return
  }
  void startSetup()
})
</script>

<template>
  <AuthLayout>
    <div class="login-brand text-center mb-6">
      <AppLogo class="login-brand__logo mb-3" />
      <div class="text-h5 font-weight-bold login-brand__name">
        {{ branding.branding.app_name }}
      </div>
    </div>

    <v-card rounded="md" elevation="10" class="login-card withbg mx-auto" max-width="520">
      <div class="login-card__accent" />
      <v-card-item class="pa-sm-8 pa-6">
        <div class="text-h5 font-weight-bold mb-1 login-card__title">Set up authenticator</div>
        <div class="text-body-2 login-card__tagline mb-6">
          Your account requires an authenticator app before you can use the admin panel. Scan the QR code with Google
          Authenticator, Microsoft Authenticator, or a similar app.
        </div>

        <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">{{ error }}</v-alert>
        <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">{{ message }}</v-alert>

        <div v-if="recoveryCodes.length" class="mb-4">
          <p class="text-body-2 mb-3">
            Save these one-time recovery codes in a secure place. Each code can be used once if you lose your
            authenticator.
          </p>
          <v-row dense class="mb-4">
            <v-col v-for="code in recoveryCodes" :key="code" cols="6">
              <code class="d-block pa-2 rounded theme-border text-center">{{ code }}</code>
            </v-col>
          </v-row>
          <v-btn block color="primary" size="large" @click="continueToApp">Continue to dashboard</v-btn>
        </div>

        <div v-else>
          <div v-if="loading" class="text-body-2 text-medium-emphasis mb-4">Preparing authenticator setup…</div>
          <template v-else-if="totpSetup">
            <div class="d-flex flex-column align-center mb-4">
              <img v-if="qrDataUrl" :src="qrDataUrl" alt="Authenticator QR code" class="mb-3" width="220" height="220" />
              <div class="text-caption text-medium-emphasis mb-1">Or enter this key manually</div>
              <code class="d-inline-block pa-2 rounded theme-border">{{ totpSetup.secret }}</code>
            </div>
            <FormField label="6-digit code" required>
              <v-text-field
                v-model="totpCode"
                variant="outlined"
                hide-details
                autocomplete="one-time-code"
                inputmode="numeric"
              />
            </FormField>
            <v-btn
              block
              color="primary"
              size="large"
              class="mt-4"
              :loading="confirming"
              :disabled="totpCode.trim().length < 6"
              @click="confirm"
            >
              Confirm and continue
            </v-btn>
            <v-btn block variant="text" class="mt-2" :loading="loading" @click="startSetup">Refresh QR code</v-btn>
          </template>
        </div>

        <div class="d-flex justify-center mt-4">
          <v-btn variant="text" size="small" @click="logout">Sign out</v-btn>
        </div>
      </v-card-item>
    </v-card>
  </AuthLayout>
</template>

<style scoped>
.login-brand__logo :deep(img) {
  height: 56px;
  max-width: 240px;
}

.login-brand__name,
.login-card__title {
  color: var(--brand-primary, #0d7a3a);
}

.login-card {
  position: relative;
  overflow: hidden;
}

.login-card__accent {
  height: 4px;
  background: linear-gradient(
    90deg,
    var(--brand-primary, #0d7a3a) 0%,
    var(--brand-secondary, #c9a227) 100%
  );
}

.login-card__tagline {
  color: rgb(var(--v-theme-text-secondary));
}
</style>
