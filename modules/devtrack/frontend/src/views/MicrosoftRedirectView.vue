<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AppLogo from '@/components/layout/AppLogo.vue'
import { apiErrorMessage } from '@/lib/apiError'
import {
  hardNavigateAppRoot,
  isChunkLoadError,
  recoverFromChunkLoadError,
} from '@/lib/chunkLoadRecovery'
import { safeInternalRedirect } from '@/lib/safeRedirect'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const route = useRoute()
const router = useRouter()
const error = ref('')
const status = ref('Completing Microsoft sign-in…')

async function afterLogin(result: { requires2fa: boolean; mustSetupTotp: boolean }) {
  if (result.requires2fa) {
    await router.replace({ name: 'verify-2fa' })
    return
  }
  if (result.mustSetupTotp) {
    await router.replace({ name: 'setup-authenticator' })
    return
  }

  const redirect = safeInternalRedirect(
    typeof route.query.redirect === 'string' ? route.query.redirect : null,
  )
  await router.replace(redirect ?? { name: 'dashboard' })
}

onMounted(async () => {
  const code = typeof route.query.code === 'string' ? route.query.code : ''
  const state = typeof route.query.state === 'string' ? route.query.state : ''
  const oauthError = typeof route.query.error === 'string' ? route.query.error : ''
  const oauthDescription =
    typeof route.query.error_description === 'string' ? route.query.error_description : ''

  if (oauthError) {
    error.value = oauthDescription || oauthError || 'Microsoft sign-in was cancelled.'
    status.value = 'Sign-in failed'
    return
  }

  if (!code || !state) {
    error.value = 'Missing Microsoft authorization response. Start again from the login page.'
    status.value = 'Sign-in failed'
    return
  }

  try {
    const result = await auth.microsoftCallback(code, state)
    status.value = 'Signed in. Redirecting…'
    try {
      await afterLogin(result)
    } catch (navError: unknown) {
      // Auth already succeeded; stale chunks must not look like an SSO failure.
      if (isChunkLoadError(navError)) {
        hardNavigateAppRoot()
        return
      }
      throw navError
    }
  } catch (e: unknown) {
    if (recoverFromChunkLoadError(e)) return
    error.value = apiErrorMessage(e, 'Microsoft sign-in failed.')
    status.value = 'Sign-in failed'
  }
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
    <v-card rounded="md" elevation="10" class="withbg mx-auto pa-8" max-width="480">
      <div class="text-h6 mb-2">{{ status }}</div>
      <v-progress-linear v-if="!error" indeterminate color="primary" class="mb-4" />
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</v-alert>
      <v-btn v-if="error" color="primary" variant="tonal" :to="{ name: 'login' }">Back to login</v-btn>
    </v-card>
  </AuthLayout>
</template>

<style scoped>
.login-brand__logo :deep(img) {
  height: 56px;
  max-width: 240px;
}

.login-brand__name {
  color: var(--brand-primary, #0d7a3a);
}
</style>
