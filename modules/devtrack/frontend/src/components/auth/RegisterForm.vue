<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import CaptchaWidget from '@/components/forms/CaptchaWidget.vue'
import FormField from '@/components/forms/FormField.vue'
import { api } from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { useBrandingStore } from '@/stores/branding'

const branding = useBrandingStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  phone: '',
  organisation: '',
  password: '',
  password_confirmation: '',
})
const captchaKey = ref<string | null>(null)
const captchaAnswer = ref('')
const captchaResetKey = ref(0)
const loading = ref(false)
const error = ref('')
const success = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  success.value = ''
  try {
    const payload: Record<string, string> = { ...form.value }
    if (captchaKey.value && captchaAnswer.value) {
      payload.captcha_key = captchaKey.value
      payload.captcha = captchaAnswer.value
    }
    const res = await api.post('/admin/auth/register', payload)
    success.value =
      res.data.message ||
      'Registration received. An administrator must approve your account before you can sign in.'
    form.value.password = ''
    form.value.password_confirmation = ''
    captchaResetKey.value += 1
  } catch (err) {
    captchaResetKey.value += 1
    error.value = apiErrorMessage(err, 'Could not register. Check the form and try again.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <v-card rounded="md" elevation="10" class="register-card withbg mx-auto" max-width="520">
    <div class="register-card__accent" />
    <v-card-item class="pa-sm-8 pa-6">
      <div class="text-h5 font-weight-bold mb-1 register-card__title">Create account</div>
      <div class="text-body-2 register-card__tagline mb-6">
        Request access to manage your organisation’s email clients.
        {{ branding.branding.app_name ? ` — ${branding.branding.app_name}` : '' }}
      </div>

      <v-alert v-if="success" type="success" variant="tonal" class="mb-4">
        {{ success }}
        <div class="mt-3">
          <v-btn color="primary" variant="tonal" :to="{ name: 'login' }">Back to sign in</v-btn>
        </div>
      </v-alert>

      <v-alert v-if="error" type="error" variant="tonal" class="mb-4" density="compact">
        {{ error }}
      </v-alert>

      <v-form v-if="!success" class="auth-form" @submit.prevent="submit">
        <v-row dense>
          <v-col cols="12">
            <FormField label="Full name" required>
              <v-text-field v-model="form.name" variant="outlined" hide-details autocomplete="name" />
            </FormField>
          </v-col>
          <v-col cols="12">
            <FormField label="Work email" required>
              <v-text-field
                v-model="form.email"
                type="email"
                variant="outlined"
                hide-details
                autocomplete="email"
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Phone number" required>
              <v-text-field v-model="form.phone" variant="outlined" hide-details autocomplete="tel" />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Organisation" required>
              <v-text-field
                v-model="form.organisation"
                variant="outlined"
                hide-details
                autocomplete="organization"
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Password" required>
              <v-text-field
                v-model="form.password"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="new-password"
              />
            </FormField>
          </v-col>
          <v-col cols="12" md="6">
            <FormField label="Confirm password" required>
              <v-text-field
                v-model="form.password_confirmation"
                type="password"
                variant="outlined"
                hide-details
                autocomplete="new-password"
              />
            </FormField>
          </v-col>
          <v-col cols="12">
            <CaptchaWidget
              :reset-key="captchaResetKey"
              @update:key="captchaKey = $event"
              @update:answer="captchaAnswer = $event"
            />
          </v-col>
          <v-col cols="12" class="pt-2">
            <v-btn block color="primary" size="large" type="submit" :loading="loading">
              Submit registration
            </v-btn>
          </v-col>
          <v-col cols="12" class="text-center">
            <v-btn variant="text" class="register-card__link" @click="router.push({ name: 'login' })">
              Already have an account? Sign in
            </v-btn>
          </v-col>
        </v-row>
      </v-form>
    </v-card-item>
  </v-card>
</template>

<style scoped>
.register-card {
  position: relative;
  overflow: hidden;
}

.register-card__accent {
  height: 4px;
  background: linear-gradient(
    90deg,
    var(--brand-primary, #0d7a3a) 0%,
    var(--brand-secondary, #c9a227) 100%
  );
}

.register-card__title,
.register-card__link {
  color: var(--brand-primary, #0d7a3a);
}

.register-card__tagline {
  color: rgb(var(--v-theme-text-secondary));
}
</style>
