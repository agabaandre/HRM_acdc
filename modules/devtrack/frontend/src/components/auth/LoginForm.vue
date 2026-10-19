<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import { apiErrorMessage } from '@/lib/apiError'
import { recoverFromChunkLoadError } from '@/lib/chunkLoadRecovery'
import { safeInternalRedirect } from '@/lib/safeRedirect'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const REMEMBER_KEY = 'devtrack.login.remember'

type RememberedLogin = {
  email?: string
  password?: string
}

const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const rememberMe = ref(false)
const loading = ref(false)
const microsoftLoading = ref(false)
const error = ref('')

function loadRemembered() {
  try {
    const raw = localStorage.getItem(REMEMBER_KEY)
    if (!raw) return
    const saved = JSON.parse(raw) as RememberedLogin & { username?: string; mode?: string }
    // Prefer email; fall back to legacy SSO username if that was what was saved.
    if (typeof saved.email === 'string' && saved.email !== '') {
      email.value = saved.email
    } else if (typeof saved.username === 'string' && saved.username !== '') {
      email.value = saved.username
    }
    if (typeof saved.password === 'string') password.value = saved.password
    rememberMe.value = true
  } catch {
    localStorage.removeItem(REMEMBER_KEY)
  }
}

function persistRemembered() {
  if (!rememberMe.value) {
    localStorage.removeItem(REMEMBER_KEY)
    return
  }

  localStorage.setItem(
    REMEMBER_KEY,
    JSON.stringify({
      email: email.value,
      password: password.value,
    } satisfies RememberedLogin),
  )
}

watch(rememberMe, (on) => {
  if (!on) localStorage.removeItem(REMEMBER_KEY)
})

async function afterLogin(result: { requires2fa: boolean; mustSetupTotp: boolean }) {
  if (result.requires2fa) {
    await router.push({ name: 'verify-2fa' })
    return
  }
  if (result.mustSetupTotp) {
    await router.push({ name: 'setup-authenticator' })
    return
  }

  const redirect = safeInternalRedirect(
    typeof route.query.redirect === 'string' ? route.query.redirect : null,
  )
  await router.push(redirect ?? { name: 'dashboard' })
}

async function submit() {
  loading.value = true
  error.value = ''
  try {
    const result = await auth.login(email.value, password.value, null)
    persistRemembered()
    await afterLogin(result)
  } catch (e: unknown) {
    const axiosErr = e as {
      response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } }
    }
    const apiMessage = axiosErr.response?.data?.message
    const fieldError =
      axiosErr.response?.data?.errors?.email?.[0] ||
      axiosErr.response?.data?.errors?.password?.[0]
    if (axiosErr.response?.status === 503 && apiMessage) {
      error.value = apiMessage
    } else {
      error.value = fieldError ?? apiMessage ?? 'Invalid credentials or account inactive.'
    }
  } finally {
    loading.value = false
  }
}

async function signInWithMicrosoft() {
  microsoftLoading.value = true
  error.value = ''
  try {
    const url = await auth.startMicrosoftLogin(email.value.trim() || undefined)
    window.location.assign(url)
  } catch (e: unknown) {
    if (recoverFromChunkLoadError(e)) return
    error.value = apiErrorMessage(
      e,
      'Microsoft 365 sign-in is unavailable. Use your @africacdc.org account, or sign in with email and password.',
    )
    microsoftLoading.value = false
  }
}

onMounted(loadRemembered)
</script>

<template>
  <v-card rounded="md" elevation="10" class="login-card withbg mx-auto" max-width="480">
    <div class="login-card__accent" />
    <v-card-item class="pa-sm-8 pa-6">
      <div class="text-h5 font-weight-bold mb-1 login-card__title">
        Sign in
      </div>
      <div class="text-body-2 login-card__tagline mb-4">
        {{ branding.branding.tagline || 'Software delivery, assignment and reporting' }}
      </div>

      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">
        {{ error }}
      </v-alert>

      <v-form class="auth-form" @submit.prevent="submit">
        <v-row class="d-flex mb-1">
          <v-col cols="12">
            <FormField label="Email" required>
              <v-text-field
                v-model="email"
                type="email"
                autocomplete="username"
                variant="outlined"
                hide-details
                color="primary"
              />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Password" required>
              <v-text-field
                v-model="password"
                type="password"
                autocomplete="current-password"
                variant="outlined"
                hide-details
                color="primary"
              />
            </FormField>
          </v-col>
          <v-col cols="12" class="d-flex align-center justify-space-between pt-0 pb-0 flex-wrap ga-1">
            <v-checkbox
              v-model="rememberMe"
              label="Remember me"
              color="primary"
              hide-details
              density="compact"
              class="login-card__remember"
            />
            <v-btn variant="text" size="small" class="login-card__link px-0" :to="{ name: 'forgot-password' }">
              Forgot password?
            </v-btn>
          </v-col>
          <v-col cols="12" class="pt-2">
            <v-btn block color="primary" size="large" type="submit" :loading="loading">
              Sign in
            </v-btn>
          </v-col>
          <v-col cols="12" class="pt-1">
            <div class="d-flex align-center ga-3 mb-3">
              <v-divider />
              <span class="text-caption text-medium-emphasis text-no-wrap">or</span>
              <v-divider />
            </div>
            <v-btn
              block
              variant="tonal"
              color="primary"
              size="large"
              :loading="microsoftLoading"
              prepend-icon="mdi-microsoft"
              @click="signInWithMicrosoft"
            >
              Sign in with Microsoft 365
            </v-btn>
          </v-col>
        </v-row>
      </v-form>
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

.login-card__remember {
  margin-inline-start: -2px;
}
</style>
