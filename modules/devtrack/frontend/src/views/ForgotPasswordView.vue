<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue'
import ForgotPasswordForm from '@/components/auth/ForgotPasswordForm.vue'
import AppLogo from '@/components/layout/AppLogo.vue'
import { useBrandingStore } from '@/stores/branding'

const branding = useBrandingStore()
</script>

<template>
  <AuthLayout>
    <div class="login-brand text-center mb-6">
      <AppLogo class="login-brand__logo mb-3" />
      <div class="text-h5 font-weight-bold login-brand__name">
        {{ branding.branding.app_name }}
      </div>
    </div>
    <ForgotPasswordForm />
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
