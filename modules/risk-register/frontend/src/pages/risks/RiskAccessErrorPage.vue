<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const reason = computed(() => String(route.query.reason || route.query.risk_error_reason || ''))

const title = computed(() => {
  if (reason.value === 'post_required') return 'SSO launch required'
  if (reason.value === 'unauthorized') return 'Could not open Risk Register'
  if (reason.value === 'missing_token') return 'Missing SSO token'
  if (reason.value === 'invalid_token') return 'Invalid or expired SSO token'
  return 'Risk Register access error'
})

const detail = computed(() => {
  switch (reason.value) {
    case 'post_required':
      return 'Open Risk Register from Staff Portal → CBP Modules (POST SSO). Opening /sso/accept in the browser directly will not work.'
    case 'unauthorized':
      return 'The Staff Portal launch token was rejected. Confirm JWT_SECRET matches Staff Portal, your account has permission 118, and try launching again.'
    case 'missing_token':
      return 'No staff_sso_jwt was posted to the accept endpoint.'
    case 'invalid_token':
      return 'The SSO token could not be decoded or has expired. Launch again from CBP Modules.'
    default:
      return reason.value
        ? `Error code: ${reason.value}`
        : 'Sign in via Staff Portal CBP Modules → Risk Register.'
  }
})

const staffHome = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff/`
  }
  return '/staff/'
})
</script>

<template>
  <div class="rr-error-page">
    <h1>{{ title }}</h1>
    <p>{{ detail }}</p>
    <p class="rr-muted">If this keeps happening, ask an administrator to check Risk Register Apache rewrite, SPA build base path (<code>/staff/risk-register/</code>), and SSO secrets.</p>
    <a class="rr-btn" :href="staffHome">Back to Staff Portal</a>
  </div>
</template>

<style scoped>
.rr-error-page {
  max-width: 36rem;
  margin: 4rem auto;
  padding: 1.5rem;
  background: #fff;
  border: 1px solid #d8dee6;
  border-radius: 8px;
}
.rr-error-page h1 { margin: 0 0 0.75rem; font-size: 1.4rem; color: #1a2b3c; }
.rr-muted { color: #6a7a8a; font-size: 0.92rem; }
.rr-btn {
  display: inline-block;
  margin-top: 1rem;
  background: #0b6e4f;
  color: #fff;
  text-decoration: none;
  padding: 0.55rem 1rem;
  border-radius: 6px;
  font-weight: 600;
}
</style>
