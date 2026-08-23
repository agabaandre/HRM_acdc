<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import StatusText from '@/components/atoms/StatusText.vue'
import ModuleGrid from '@/components/organisms/ModuleGrid.vue'
import { fetchCbpModules, type CbpModuleLink } from '@/lib/cbpModules'
import { launchCbpModule, moduleLaunchKey } from '@/lib/cbpLaunch'
import { apiErrorMessage } from '@cbp/helpdesk-lib/lib/apiErrorMessage'
import { useLocaleStore } from '@/stores/locale'

const locale = useLocaleStore()

const modules = ref<CbpModuleLink[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const query = ref('')

const staffBase = computed(() => {
  if (typeof window !== 'undefined') {
    return `${window.location.protocol}//${window.location.host}/staff`
  }
  return '/staff'
})

const bgUrl = computed(() => `${staffBase.value}/assets/images/bg_login.jpg`)
const apmBase = computed(() => `${staffBase.value}/apm`)

function onModuleClick(mod: CbpModuleLink, e: Event) {
  if (!mod.sso_launch) return
  e.preventDefault()
  const key = moduleLaunchKey(mod)
  if (key) {
    void launchCbpModule(key, !!mod.opens_in_new_tab)
  }
}

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const payload = await fetchCbpModules({ path: 'home' })
    modules.value = payload.modules
  } catch (e) {
    error.value = apiErrorMessage(e, locale.t('chrome.load_modules_error', 'Could not load CBP modules'))
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="cbp-home-bleed" :style="{ backgroundImage: `url('${bgUrl}')` }">
    <div class="cbp-home-shell">
      <div class="cbp-home-shell-inner">
        <div class="cbp-home">
          <h1 class="cbp-home-title">{{ locale.t('home.welcome', 'Welcome to Africa CDC Central Business Platform') }}</h1>

          <div class="cbp-home-search">
            <label for="cbpHomeModuleSearch" class="visually-hidden">{{ locale.t('home.search_modules', 'Search modules') }}</label>
            <div class="cbp-home-search-input">
              <span class="cbp-home-search-icon" aria-hidden="true">
                <i class="fa-solid fa-search" />
              </span>
              <input
                id="cbpHomeModuleSearch"
                v-model="query"
                type="search"
                :placeholder="locale.t('home.search_placeholder', 'Search modules by name or description…')"
                autocomplete="off"
                spellcheck="false"
              />
            </div>
          </div>

          <StatusText v-if="loading" :message="locale.t('chrome.loading_modules', 'Loading modules…')" tone="muted" />
          <StatusText v-else-if="error" :message="error" tone="error" />
          <ModuleGrid v-else :modules="modules" :query="query" :on-module-click="onModuleClick" />

          <footer class="cbp-home-footer">
            <p>
              <a :href="`${apmBase}/faq`" target="_blank" rel="noopener noreferrer">{{ locale.t('home.faqs', 'FAQs') }}</a>
              <a :href="`${apmBase}/help`" target="_blank" rel="noopener noreferrer">{{ locale.t('home.help', 'Help') }}</a>
            </p>
          </footer>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.cbp-wrapper--home .cbp-page-wrapper--home,
.cbp-home-page {
  padding: 0;
  min-height: calc(100vh - 3.5rem);
}

.cbp-home-bleed {
  min-height: calc(100vh - 3.5rem);
  padding: 1rem 0.75rem 2rem;
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center center;
  background-attachment: fixed;
}

.cbp-home-shell {
  --cbp-primary: #119a48;
  --cbp-primary-light: #1bb85a;
  --cbp-text-dark: #2c3e50;
  --cbp-text-muted: #6c757d;
  --cbp-medium-grey: #e9ecef;
  --cbp-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  --cbp-shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  --cbp-transition: all 0.2s ease;
  max-width: 1200px;
  margin: 0 auto;
}

.cbp-home-shell-inner {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  padding: 2rem 1.25rem;
  box-shadow: var(--cbp-shadow-lg);
  position: relative;
  overflow: hidden;
}

.cbp-home-shell-inner::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, var(--cbp-primary) 0%, var(--cbp-primary-light) 100%);
}

.cbp-home {
  position: relative;
  z-index: 1;
}

.cbp-home-title {
  text-align: center;
  font-size: clamp(1.35rem, 2.5vw, 1.75rem);
  font-weight: 700;
  color: var(--cbp-text-dark);
  margin: 0 0 1.5rem;
  line-height: 1.3;
}

.cbp-home-search {
  max-width: 32rem;
  margin: 0 auto 1.75rem;
}

.cbp-home-search-input {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  background: #fff;
  border: 1px solid var(--cbp-medium-grey);
  border-radius: 999px;
  padding: 0.55rem 1rem;
  box-shadow: var(--cbp-shadow);
}

.cbp-home-search-icon {
  color: var(--cbp-text-muted);
  flex-shrink: 0;
}

.cbp-home-search-input input {
  flex: 1;
  border: 0;
  outline: none;
  background: transparent;
  font-size: 0.95rem;
  color: var(--cbp-text-dark);
}

.visually-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.cbp-home-footer {
  margin-top: 1.75rem;
  padding-top: 1rem;
  border-top: 1px solid var(--cbp-medium-grey);
  text-align: center;
}

.cbp-home-footer p {
  margin: 0;
  display: flex;
  justify-content: center;
  gap: 1.25rem;
}

.cbp-home-footer a {
  color: var(--cbp-primary);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 600;
}

.cbp-home-footer a:hover {
  text-decoration: underline;
}

.cbp-home .settings-card {
  min-height: 220px;
  height: 100%;
  padding: 1.75rem 1.25rem;
  transition: var(--cbp-transition);
  font-size: 0.9rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  background: #fff;
  border: 1px solid var(--cbp-medium-grey);
  box-shadow: var(--cbp-shadow);
  position: relative;
  overflow: hidden;
  width: 100%;
  max-width: 100%;
  border-radius: 0.5rem;
  animation: cbpFadeInUp 0.55s ease forwards;
}

.cbp-home .settings-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--cbp-primary);
  transform: scaleX(0);
  transition: var(--cbp-transition);
}

.cbp-home .settings-card:hover {
  box-shadow: var(--cbp-shadow-lg);
  transform: translateY(-2px);
  border-color: var(--cbp-primary);
}

.cbp-home .settings-card:hover::before {
  transform: scaleX(1);
}

.cbp-home .settings-card h6 {
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--cbp-text-dark);
  margin-bottom: 0.65rem;
  line-height: 1.3;
  position: relative;
  z-index: 2;
}

.cbp-home .settings-card p {
  font-size: 0.875rem;
  color: var(--cbp-text-muted);
  margin: 0;
  line-height: 1.5;
  flex-grow: 1;
  position: relative;
  z-index: 2;
}

.cbp-home .widgets-icons {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1;
  pointer-events: none;
}

.cbp-home .widgets-icons i {
  width: 120px;
  height: 120px;
  font-size: 4.25rem;
  color: rgba(17, 154, 72, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  transition: var(--cbp-transition);
}

.cbp-home .settings-card:hover .widgets-icons i {
  color: rgba(17, 154, 72, 0.16);
  transform: scale(1.02);
}

.cbp-home .setting-card-item a {
  text-decoration: none;
  color: inherit;
  display: block;
  height: 100%;
}

@keyframes cbpFadeInUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 767px) {
  .cbp-home-shell-inner {
    padding: 1.5rem 1rem;
  }

  .cbp-home .settings-card {
    min-height: 200px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .cbp-home .settings-card {
    animation: none;
  }
  .cbp-home .settings-card,
  .cbp-home .widgets-icons i {
    transition: none;
  }
}
</style>
