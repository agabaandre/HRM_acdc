<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FormField from '@/components/forms/FormField.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref((route.query.email as string) ?? '')
const token = ref((route.query.token as string) ?? '')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const message = ref('')
const error = ref('')

const hasToken = computed(() => email.value.length > 0 && token.value.length >= 64)

async function submit() {
  loading.value = true
  message.value = ''
  error.value = ''
  try {
    message.value = await auth.resetPassword({
      email: email.value,
      token: token.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    await router.push({ name: 'login' })
  } catch {
    error.value = 'This reset link is invalid or expired. Request a new one.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-card rounded="md" elevation="10" class="login-card withbg mx-auto" max-width="480">
    <div class="login-card__accent" />
    <v-card-item class="pa-sm-8 pa-6">
      <div class="text-h5 font-weight-bold mb-1 login-card__title">Choose a new password</div>
      <div class="text-body-2 login-card__tagline mb-6">
        Use at least 10 characters with upper and lower case letters, a number, and a symbol.
      </div>

      <v-alert v-if="!hasToken" type="warning" variant="tonal" class="mb-4" density="compact">
        This reset link is incomplete. Request a new password reset email.
      </v-alert>
      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">
        {{ message }}
      </v-alert>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">
        {{ error }}
      </v-alert>

      <v-form @submit.prevent="submit">
        <div class="form-stack">
          <FormField label="Email" required>
            <v-text-field v-model="email" type="email" variant="outlined" hide-details color="primary" readonly />
          </FormField>
          <FormField label="New password" required>
            <v-text-field
              v-model="password"
              type="password"
              autocomplete="new-password"
              variant="outlined"
              hide-details
              color="primary"
              :disabled="!hasToken"
            />
          </FormField>
          <FormField label="Confirm password" required>
            <v-text-field
              v-model="passwordConfirmation"
              type="password"
              autocomplete="new-password"
              variant="outlined"
              hide-details
              color="primary"
              :disabled="!hasToken"
            />
          </FormField>
        </div>
        <v-btn
          block
          color="primary"
          size="large"
          type="submit"
          class="mt-6"
          :loading="loading"
          :disabled="!hasToken"
        >
          Update password
        </v-btn>
        <v-btn block variant="text" class="mt-2 login-card__link" :to="{ name: 'login' }">
          Back to sign in
        </v-btn>
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
</style>
