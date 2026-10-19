<script setup lang="ts">
import { ref } from 'vue'
import FormField from '@/components/forms/FormField.vue'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const email = ref('')
const loading = ref(false)
const message = ref('')
const error = ref('')

async function submit() {
  loading.value = true
  message.value = ''
  error.value = ''
  try {
    message.value = await auth.forgotPassword(email.value)
  } catch {
    error.value = 'Unable to process your request right now. Please try again later.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-card rounded="md" elevation="10" class="login-card withbg mx-auto" max-width="480">
    <div class="login-card__accent" />
    <v-card-item class="pa-sm-8 pa-6">
      <div class="text-h5 font-weight-bold mb-1 login-card__title">Reset password</div>
      <div class="text-body-2 login-card__tagline mb-6">
        Enter your account email and we will send a secure reset link.
      </div>

      <v-alert v-if="message" type="success" variant="tonal" class="mb-4" density="compact">
        {{ message }}
      </v-alert>
      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">
        {{ error }}
      </v-alert>

      <v-form @submit.prevent="submit">
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
        <v-btn block color="primary" size="large" type="submit" class="mt-6" :loading="loading">
          Send reset link
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
