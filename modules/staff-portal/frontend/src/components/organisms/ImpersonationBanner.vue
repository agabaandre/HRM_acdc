<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { revertImpersonation } from '@/lib/authAdminApi'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const auth = useAuthStore()
const locale = useLocaleStore()
const router = useRouter()
const reverting = ref(false)
const error = ref<string | null>(null)
const remaining = ref<number | null>(null)
let timer: number | undefined

const active = computed(() => !!auth.impersonation?.active)
const userName = computed(() => auth.impersonation?.user_name || auth.me?.name || 'another user')
const originalName = computed(() => auth.impersonation?.original_user_name || 'your admin account')

const timerLabel = computed(() => {
  if (remaining.value == null) return ''
  const s = Math.max(0, remaining.value)
  const m = Math.floor(s / 60)
  const sec = String(s % 60).padStart(2, '0')
  return `${m}:${sec}`
})

function syncRemaining() {
  const fromApi = auth.impersonation?.remaining_seconds
  if (typeof fromApi === 'number') {
    remaining.value = fromApi
    return
  }
  const expires = auth.impersonation?.expires_at
  if (typeof expires === 'number') {
    remaining.value = Math.max(0, expires - Math.floor(Date.now() / 1000))
  }
}

function startTimer() {
  window.clearInterval(timer)
  syncRemaining()
  if (!active.value) return
  timer = window.setInterval(() => {
    if (remaining.value == null) {
      syncRemaining()
      return
    }
    remaining.value = Math.max(0, remaining.value - 1)
    if (remaining.value <= 0) {
      void onRevert(true)
    }
  }, 1000)
}

async function onRevert(auto = false) {
  if (reverting.value) return
  reverting.value = true
  error.value = null
  try {
    const payload = await revertImpersonation()
    auth.applyImpersonationPayload(payload)
    await router.push('/auth/users')
  } catch (e) {
    if (!auto) {
      error.value = apiErrorMessage(e, 'Could not revert impersonation')
    } else {
      // Force re-auth if auto-revert failed after expiry.
      auth.invalidateSession()
      auth.redirectToLogin()
    }
  } finally {
    reverting.value = false
  }
}

watch(active, (on) => {
  if (on) startTimer()
  else {
    window.clearInterval(timer)
    remaining.value = null
  }
})

onMounted(() => {
  if (active.value) startTimer()
})

onUnmounted(() => window.clearInterval(timer))
</script>

<template>
  <div v-if="active" class="impersonation-banner" role="status">
    <div class="impersonation-banner__inner">
      <div class="impersonation-banner__copy">
        <i class="fa-solid fa-user-secret me-2" aria-hidden="true" />
        <span>
          {{ locale.t('chrome.impersonating', 'Impersonating') }} <strong>{{ userName }}</strong>
          <span class="text-medium-emphasis"> · {{ locale.t('chrome.return_to', 'return to') }} {{ originalName }}</span>
          <span v-if="timerLabel" class="impersonation-banner__timer"> · {{ timerLabel }} {{ locale.t('chrome.left', 'left') }}</span>
        </span>
      </div>
      <div class="impersonation-banner__actions">
        <span v-if="error" class="impersonation-banner__error">{{ error }}</span>
        <v-btn
          size="small"
          color="warning"
          variant="flat"
          :loading="reverting"
          @click="onRevert(false)"
        >
          {{ locale.t('chrome.revert_to_admin', 'Revert to admin') }}
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.impersonation-banner {
  background: linear-gradient(90deg, #7c2d12 0%, #b45309 55%, #92400e 100%);
  color: #fff7ed;
  border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}

.impersonation-banner__inner {
  max-width: 1400px;
  margin: 0 auto;
  padding: 0.55rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.impersonation-banner__copy {
  display: flex;
  align-items: center;
  font-size: 0.9rem;
}

.impersonation-banner__timer {
  font-variant-numeric: tabular-nums;
  font-weight: 650;
}

.impersonation-banner__actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.impersonation-banner__error {
  font-size: 0.8rem;
  color: #fecaca;
}
</style>
