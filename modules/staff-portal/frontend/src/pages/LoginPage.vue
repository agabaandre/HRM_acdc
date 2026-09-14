<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { microsoftLoginUrl } from '@/lib/auth'
import { api } from '@/lib/api'
import { notifyApiError, toast } from '@/features/toast'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const showAlternative = ref(false)
const loading = ref(false)

const allowAlternativeLogin = ref(false)
const microsoftEnabled = ref(true)
const apmBaseUrl = ref('')
const optionsReady = ref(false)

const staffBase = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff`
  }
  return '/staff'
})

const bgUrl = computed(() => `${staffBase.value}/assets/images/bg_login.jpg`)
const logoUrl = computed(() => `${staffBase.value}/assets/images/AU_CDC_Logo-800.png`)
const year = computed(() => new Date().getFullYear())
const msHref = computed(() => microsoftLoginUrl())

const faqUrl = computed(() =>
  apmBaseUrl.value ? `${apmBaseUrl.value}/faq` : `${staffBase.value}/apm/faq`,
)
const helpUrl = computed(() =>
  apmBaseUrl.value ? `${apmBaseUrl.value}/help` : `${staffBase.value}/apm/help`,
)

function applyQueryError() {
  const q = route.query.error
  const code = route.query.error_code
  if (typeof q !== 'string' || q.trim() === '') return

  const message = q.trim()
  const detail = typeof code === 'string' && code ? `${message} (${code})` : message
  toast.error(detail, 'Sign-in failed')
}

async function clearQueryError() {
  if (!route.query.error && !route.query.error_code) return
  const nextQuery = { ...route.query }
  delete nextQuery.error
  delete nextQuery.error_code
  await router.replace({ name: 'login', query: nextQuery })
}

async function loadOptions() {
  try {
    const { data } = await api.get<{
      data: {
        allow_alternative_login: boolean
        microsoft_enabled: boolean
        apm_base_url?: string
      }
    }>('/api/v1/auth/login-options')
    allowAlternativeLogin.value = !!data.data.allow_alternative_login
    microsoftEnabled.value = data.data.microsoft_enabled !== false
    apmBaseUrl.value = (data.data.apm_base_url || '').replace(/\/$/, '')
  } catch {
    allowAlternativeLogin.value = false
    microsoftEnabled.value = true
  } finally {
    optionsReady.value = true
  }
}

async function onSubmit() {
  if (!allowAlternativeLogin.value) return
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value, remember.value)
    await router.replace({ name: 'home' })
  } catch (e) {
    notifyApiError(e, 'Sign-in failed', 'Sign-in failed')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  applyQueryError()
  void loadOptions()
  if (route.query.error) {
    window.setTimeout(() => {
      void clearQueryError()
    }, 50)
  }
})

watch(
  () => [route.query.error, route.query.error_code] as const,
  ([error]) => {
    if (typeof error === 'string' && error.trim() !== '') {
      applyQueryError()
    }
  },
)
</script>

<template>
  <main class="portal-login" :style="{ backgroundImage: `url('${bgUrl}')` }">
    <div class="portal-login__container">
      <aside class="portal-login__brand d-none d-md-flex">
        <div class="portal-login__logo">
          <img
            :src="logoUrl"
            alt="Africa CDC"
            width="200"
            height="80"
            decoding="async"
            fetchpriority="low"
          />
        </div>
        <div class="portal-login__welcome">
          <h1>Welcome Back</h1>
          <p>
            Access your Africa CDC Central Business Platform account to manage staff operations and
            track activities efficiently.
          </p>
        </div>
      </aside>

      <section class="portal-login__panel">
        <div class="portal-login__mobile-logo d-md-none text-center mb-4">
          <img
            :src="logoUrl"
            alt="Africa CDC"
            width="140"
            height="56"
            decoding="async"
            fetchpriority="high"
          />
        </div>

        <div class="portal-login__title text-center mb-4">
          <h2>Sign In</h2>
          <p>Choose your preferred sign-in method</p>
        </div>

        <!-- Native link: avoids Vuetify default pill height blowing up to fill the panel -->
        <a
          v-if="microsoftEnabled"
          class="portal-login__ms-btn"
          :href="msHref"
        >
          <span class="portal-login__ms-icon" aria-hidden="true">
            <svg viewBox="0 0 23 23" width="18" height="18" focusable="false">
              <path fill="#f25022" d="M1 1h10v10H1z" />
              <path fill="#00a4ef" d="M12 1h10v10H12z" />
              <path fill="#7fba00" d="M1 12h10v10H1z" />
              <path fill="#ffb900" d="M12 12h10v10H12z" />
            </svg>
          </span>
          <span>Sign in with Staff Email</span>
        </a>
        <div
          v-else-if="optionsReady"
          class="portal-login__warn mb-3"
          role="status"
        >
          Microsoft sign-in is not configured. Set
          <code>TENANT_ID</code>, <code>CLIENT_ID</code>, and <code>CLIENT_SEC_VALUE</code> in
          <code>.env</code>.
        </div>

        <template v-if="allowAlternativeLogin">
          <label class="portal-login__alt-toggle">
            <input v-model="showAlternative" type="checkbox" />
            Use alternative sign-in method
          </label>

          <form v-show="showAlternative" class="portal-login__alt" @submit.prevent="onSubmit">
            <label class="portal-login__field">
              <span>Email Address</span>
              <input
                v-model="email"
                type="email"
                autocomplete="username"
                placeholder="Enter your email address"
                required
              />
            </label>
            <label class="portal-login__field">
              <span>Password</span>
              <input
                v-model="password"
                type="password"
                autocomplete="current-password"
                placeholder="Enter your password"
                required
              />
            </label>
            <label class="portal-login__remember">
              <input v-model="remember" type="checkbox" />
              Remember me
            </label>
            <button type="submit" class="portal-login__submit" :disabled="loading">
              {{ loading ? 'Signing in…' : 'Sign In' }}
            </button>
          </form>
        </template>

        <footer class="portal-login__footer">
          <p class="mb-2">
            <a :href="faqUrl" target="_blank" rel="noopener noreferrer">FAQ</a>
            <span class="mx-2">|</span>
            <a :href="helpUrl" target="_blank" rel="noopener noreferrer">Help</a>
          </p>
          <p class="mb-0">&copy; {{ year }} Africa CDC. All rights reserved.</p>
        </footer>
      </section>
    </div>
  </main>
</template>

<style scoped>
.portal-login {
  --login-primary: #119a48;
  --login-primary-dark: #0d7a3a;
  --login-text: #2c3e50;
  --login-muted: #6c757d;
  --login-ms: #0078d4;
  --login-ms-hover: #106ebe;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center;
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}

.portal-login__container {
  display: flex;
  width: 100%;
  max-width: 960px;
  min-height: 520px;
  background: #fff;
  box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.12);
  border-radius: 6px;
  overflow: hidden;
}

.portal-login__brand {
  flex: 1;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: #fff;
  padding: 3rem 2.25rem;
  background: linear-gradient(135deg, var(--login-primary) 0%, var(--login-primary-dark) 100%);
  position: relative;
  overflow: hidden;
}

.portal-login__brand::before {
  content: '';
  position: absolute;
  inset: -50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  pointer-events: none;
}

.portal-login__logo,
.portal-login__welcome {
  position: relative;
  z-index: 1;
}

.portal-login__logo {
  margin-bottom: 1.5rem;
}

.portal-login__logo img {
  max-width: 180px;
  height: auto;
  filter: brightness(0) invert(1);
}

.portal-login__welcome h1 {
  font-size: 2.25rem;
  font-weight: 700;
  line-height: 1.2;
  margin: 0 0 0.75rem;
}

.portal-login__welcome p {
  margin: 0;
  font-size: 1rem;
  line-height: 1.55;
  opacity: 0.92;
}

.portal-login__panel {
  flex: 1;
  padding: 3rem 2.25rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.portal-login__title h2 {
  margin: 0 0 0.35rem;
  font-size: 1.75rem;
  font-weight: 600;
  color: var(--login-text);
}

.portal-login__title p {
  margin: 0;
  color: var(--login-muted);
  font-size: 0.95rem;
}

.portal-login__ms-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  width: 100%;
  max-width: 100%;
  height: 44px;
  min-height: 44px;
  max-height: 44px;
  padding: 0 1.25rem;
  margin: 0 0 0.75rem;
  border: 0;
  border-radius: 8px;
  background: var(--login-ms);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  letter-spacing: 0.01em;
  text-decoration: none;
  box-sizing: border-box;
  flex: 0 0 auto;
  transition: background 0.15s ease;
}

.portal-login__ms-btn:hover {
  background: var(--login-ms-hover);
  color: #fff;
}

.portal-login__ms-btn:focus-visible {
  outline: 2px solid #004578;
  outline-offset: 2px;
}

.portal-login__ms-icon {
  display: inline-flex;
  flex-shrink: 0;
}

.portal-login__ms-icon svg {
  display: block;
  background: #fff;
  border-radius: 2px;
  padding: 1px;
}

.portal-login__warn {
  padding: 0.75rem 0.9rem;
  border-radius: 6px;
  background: #fff8e6;
  color: #7a4d00;
  font-size: 0.875rem;
  line-height: 1.4;
}

.portal-login__alt-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0 0.75rem;
  font-size: 0.9rem;
  color: var(--login-text);
  cursor: pointer;
}

.portal-login__alt {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.portal-login__field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: var(--login-muted);
}

.portal-login__field input {
  height: 42px;
  padding: 0 0.85rem;
  border: 1px solid #dfe5ef;
  border-radius: 6px;
  font-size: 0.95rem;
  color: var(--login-text);
  background: #fff;
}

.portal-login__field input:focus {
  outline: 2px solid rgba(13, 122, 58, 0.35);
  border-color: var(--login-primary);
}

.portal-login__remember {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-size: 0.9rem;
  color: var(--login-text);
}

.portal-login__submit {
  height: 44px;
  border: 0;
  border-radius: 8px;
  background: var(--login-primary);
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
}

.portal-login__submit:hover:not(:disabled) {
  background: var(--login-primary-dark);
}

.portal-login__submit:disabled {
  opacity: 0.7;
  cursor: wait;
}

.portal-login__footer {
  margin-top: 1.75rem;
  padding-top: 1rem;
  border-top: 1px solid #e9ecef;
  text-align: center;
  color: var(--login-muted);
  font-size: 0.875rem;
}

.portal-login__footer a {
  color: var(--login-primary);
  text-decoration: none;
  font-weight: 600;
}

.portal-login__footer a:hover {
  text-decoration: underline;
}

@media (max-width: 960px) {
  .portal-login__container {
    flex-direction: column;
    max-width: 420px;
    min-height: auto;
  }

  .portal-login__panel {
    padding: 2rem 1.5rem;
  }

  .portal-login__welcome h1 {
    font-size: 1.85rem;
  }
}
</style>
