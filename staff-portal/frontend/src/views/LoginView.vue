<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { microsoftLoginUrl } from '@/lib/auth'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const remember = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)

async function onSubmit() {
  loading.value = true
  error.value = null
  try {
    await auth.login(email.value.trim(), password.value, remember.value)
    await router.replace({ name: 'home' })
  } catch (e) {
    error.value = apiErrorMessage(e, 'Sign-in failed')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="portal-login">
    <UCard class="portal-login__card">
      <template #header>
        <h1 class="text-h5 font-weight-bold mb-1">Staff Portal</h1>
        <p class="text-medium-emphasis mb-0">Africa CDC business platform</p>
      </template>

      <VAlert v-if="error" type="error" variant="tonal" class="mb-4" :text="error" />

      <UForm @submit.prevent="onSubmit">
        <UFormField label="Work email" required>
          <UInput v-model="email" type="email" autocomplete="username" />
        </UFormField>
        <UFormField label="Password" required class="mt-3">
          <UInput v-model="password" type="password" autocomplete="current-password" />
        </UFormField>
        <UCheckbox v-model="remember" label="Remember me" class="mt-2" />
        <UButton type="submit" color="primary" block class="mt-4" :loading="loading">
          Sign in
        </UButton>
      </UForm>

      <div class="portal-login__divider">or</div>

      <a :href="microsoftLoginUrl()" class="portal-login__ms-link">
        <UButton variant="outline" block>
          Sign in with Microsoft
        </UButton>
      </a>
    </UCard>
  </main>
</template>

<style scoped>
.portal-login {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
  background: #f4f6f8;
}

.portal-login__card {
  width: 100%;
  max-width: 26rem;
}

.portal-login__divider {
  text-align: center;
  margin: 1.25rem 0;
  color: #64748b;
  font-size: 0.875rem;
}

.portal-login__ms-link {
  display: block;
  text-decoration: none;
}
</style>
