<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import CbpPageHeading from '@cbp/common/CbpPageHeading.vue'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { changeMyPassword } from '@/lib/profileApi'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref<string | null>(null)

const form = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

async function submit() {
  if (!auth.passwordLoginAvailable) {
    await router.replace({ name: 'profile' })
    return
  }
  saving.value = true
  error.value = null
  success.value = null
  try {
    await changeMyPassword({ ...form })
    success.value = 'Password changed successfully.'
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
  } catch (e) {
    error.value = apiErrorMessage(e, 'Could not change password')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <CbpPageHeading title="Change password" subtitle="Update the password used for email sign-in." />

    <v-alert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = null">
      {{ error }}
    </v-alert>
    <v-alert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = null">
      {{ success }}
    </v-alert>

    <v-card max-width="480" variant="outlined">
      <v-card-text>
        <v-form @submit.prevent="submit">
          <v-text-field
            v-model="form.current_password"
            label="Current password"
            type="password"
            autocomplete="current-password"
            class="mb-2"
          />
          <v-text-field
            v-model="form.password"
            label="New password"
            type="password"
            autocomplete="new-password"
            class="mb-2"
          />
          <v-text-field
            v-model="form.password_confirmation"
            label="Confirm new password"
            type="password"
            autocomplete="new-password"
            class="mb-4"
          />
          <div class="d-flex ga-2">
            <v-btn type="submit" color="primary" :loading="saving">Save password</v-btn>
            <v-btn variant="text" :to="{ name: 'profile' }">Back to profile</v-btn>
          </div>
        </v-form>
      </v-card-text>
    </v-card>
  </div>
</template>
