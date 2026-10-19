<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

const method = ref<'email' | 'totp'>('email')
const code = ref('')
const loading = ref(false)
const resending = ref(false)
const error = ref('')
const message = ref('')

const availableMethods = computed(() => auth.pending2fa?.methods ?? [])
const maskedEmail = computed(() => auth.pending2fa?.email ?? '')

if (availableMethods.value.includes('email')) {
  method.value = 'email'
} else if (availableMethods.value.includes('totp')) {
  method.value = 'totp'
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const result = await auth.verify2fa(method.value, code.value.trim())
    if (result.mustSetupTotp) {
      await router.push({ name: 'setup-authenticator' })
      return
    }
    await router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Invalid verification code. Please try again.'
  } finally {
    loading.value = false
  }
}

async function resend() {
  resending.value = true
  error.value = ''
  message.value = ''
  try {
    message.value = await auth.resend2faEmail()
  } catch {
    error.value = 'Could not resend the verification code.'
  } finally {
    resending.value = false
  }
}

function backToLogin() {
  auth.clearPending2fa()
  router.push({ name: 'login' })
}
</script>

<template>
  <v-card rounded="md" elevation="10" class="login-card withbg mx-auto" max-width="480">
    <div class="login-card__accent" />
    <v-card-item class="pa-sm-8 pa-6">
      <div class="text-h5 font-weight-bold mb-1 login-card__title">
        Two-factor verification
      </div>
      <div class="text-body-2 login-card__tagline mb-6">
        Complete sign-in to {{ branding.branding.app_name }}
      </div>

      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">
        {{ error }}
      </v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">
        {{ message }}
      </v-alert>

      <v-btn-toggle
        v-if="availableMethods.length > 1"
        v-model="method"
        mandatory
        color="primary"
        class="mb-4"
        divided
      >
        <v-btn v-if="availableMethods.includes('email')" value="email" prepend-icon="mdi-email-outline">
          Email code
        </v-btn>
        <v-btn v-if="availableMethods.includes('totp')" value="totp" prepend-icon="mdi-cellphone-key">
          Authenticator
        </v-btn>
      </v-btn-toggle>

      <div v-if="method === 'email'" class="text-body-2 text-medium-emphasis mb-4">
        Enter the 6-digit code sent to <strong>{{ maskedEmail }}</strong>.
      </div>
      <div v-else class="text-body-2 text-medium-emphasis mb-4">
        Enter the 6-digit code from your authenticator app. Recovery codes are also accepted.
      </div>

      <v-form @submit.prevent="submit">
        <FormField :label="method === 'email' ? 'Email verification code' : 'Authenticator code'" required>
          <v-text-field
            v-model="code"
            variant="outlined"
            hide-details
            color="primary"
            autocomplete="one-time-code"
            inputmode="numeric"
          />
        </FormField>

        <v-btn block color="primary" size="large" type="submit" class="mt-4" :loading="loading">
          Verify and sign in
        </v-btn>
      </v-form>

      <div class="d-flex justify-space-between mt-4">
        <v-btn variant="text" class="login-card__link px-0" @click="backToLogin">
          Back to sign in
        </v-btn>
        <v-btn
          v-if="method === 'email' && availableMethods.includes('email')"
          variant="text"
          class="login-card__link px-0"
          :loading="resending"
          @click="resend"
        >
          Resend code
        </v-btn>
      </div>
    </v-card-item>
  </v-card>
</template>

<style scoped>
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

.login-card__title,
.login-card__link {
  color: var(--brand-primary, #0d7a3a);
}

.login-card__tagline {
  color: rgb(var(--v-theme-text-secondary));
}
</style>
