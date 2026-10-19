<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppLogo from '@/components/layout/AppLogo.vue'
import {
  applyLocale,
  fallbackLanguages,
  fetchCbpModules,
  fetchLanguages,
  launchCbpModule,
  readLocale,
  staffPortalBase,
  type CbpModuleLink,
  type CbpNavPayload,
  type PortalLanguage,
} from '@/lib/cbpChrome'
import { CBP_PROGRAM_COUNT, CBP_PROGRAM_GROUPS, moduleMenuIcon } from '@/lib/cbpProgramMenu'
import { currentThemePreference, setThemePreference, type ThemeName } from '@/lib/themePreference'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'

const auth = useAuthStore()
const branding = useBrandingStore()
const router = useRouter()

const theme = ref<ThemeName>(currentThemePreference())
const moduleScope = ref<'operations' | 'programs'>('operations')
const portalOpen = ref(false)
const userOpen = ref(false)
const langOpen = ref(false)
const chromeRef = ref<HTMLElement | null>(null)
const nav = ref<CbpNavPayload | null>(null)
const navLoading = ref(false)
const navError = ref('')
const languages = ref<PortalLanguage[]>(fallbackLanguages())
const localeCode = ref(readLocale())

const userName = computed(() => auth.user?.name?.trim() || '')
const initials = computed(() => {
  const parts = userName.value.split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  return (parts[0] || '?').slice(0, 2).toUpperCase()
})
const avatarColor = computed(() => {
  const colors = ['#119a48', '#1bb85a', '#0d7a3a', '#2c3e50', '#0d47a1', '#6a1b9a', '#9f2240']
  let hash = 0
  for (let i = 0; i < userName.value.length; i += 1) hash = (hash * 31 + userName.value.charCodeAt(i)) >>> 0
  return colors[hash % colors.length]
})
const currentLanguage = computed(
  () => languages.value.find((lang) => lang.code === localeCode.value) || languages.value[0],
)
const homeHref = computed(() => nav.value?.home?.href || `${staffPortalBase()}/`)
const programGroups = CBP_PROGRAM_GROUPS
const programCount = CBP_PROGRAM_COUNT
const operationsCount = computed(() => nav.value?.modules?.length ?? 0)

function closeMenus() {
  portalOpen.value = false
  userOpen.value = false
  langOpen.value = false
}

function toggle(which: 'portal' | 'user' | 'lang') {
  const next = which === 'portal' ? !portalOpen.value : which === 'user' ? !userOpen.value : !langOpen.value
  closeMenus()
  if (which === 'portal') portalOpen.value = next
  if (which === 'user') userOpen.value = next
  if (which === 'lang') langOpen.value = next
}

function chooseTheme(next: ThemeName) {
  theme.value = next
  setThemePreference(next)
}

async function chooseLanguage(code: string) {
  localeCode.value = code
  closeMenus()
  try {
    await applyLocale(code)
  } catch {
    /* the button still shows the choice; other modules read the same cookie */
  }
}

async function openModule(mod: CbpModuleLink, event: Event) {
  closeMenus()
  if (mod.module_key === 'devtrack' || mod.is_active) {
    event.preventDefault()
    return
  }
  if (!mod.sso_launch) return
  event.preventDefault()
  try {
    await launchCbpModule(mod.module_key || mod.id || '')
  } catch {
    window.alert('Could not open that module. Open it from CBP Home and try again.')
  }
}

async function logout() {
  closeMenus()
  await auth.logout()
  await router.push({ name: 'login' })
}

function onDocClick(event: MouseEvent) {
  const target = event.target
  if (!(target instanceof Node)) return
  if (chromeRef.value?.contains(target)) return
  closeMenus()
}

onMounted(() => {
  document.addEventListener('click', onDocClick)
  navLoading.value = true
  void fetchCbpModules()
    .then((payload) => {
      nav.value = payload
      navError.value = ''
    })
    .catch(() => {
      navError.value = 'Could not load CBP modules'
    })
    .finally(() => {
      navLoading.value = false
    })
  void fetchLanguages()
    .then((payload) => {
      languages.value = payload.languages
      if (payload.locale) localeCode.value = payload.locale
    })
    .catch(() => {
      /* keep the shared language list */
    })
})

onUnmounted(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <v-app-bar elevation="0" height="60" class="devtrack-topbar">
    <AppLogo class="d-none d-sm-flex" :inverse="branding.branding.admin_logo_inverse" />
    <v-spacer />
    <div ref="chromeRef" class="devtrack-chrome">
      <div class="devtrack-dd" :class="{ 'is-open': portalOpen }">
        <button type="button" class="devtrack-chip" aria-haspopup="true" :aria-expanded="portalOpen" @click.stop="toggle('portal')">
          <svg class="devtrack-grid" viewBox="0 0 16 16" aria-hidden="true">
            <rect x="1" y="1" width="6" height="6" rx="1" />
            <rect x="9" y="1" width="6" height="6" rx="1" />
            <rect x="1" y="9" width="6" height="6" rx="1" />
            <rect x="9" y="9" width="6" height="6" rx="1" />
          </svg>
          <span>CBP Modules</span>
          <span class="devtrack-caret" aria-hidden="true">▼</span>
        </button>
        <div class="devtrack-panel" role="menu">
          <a class="devtrack-home" :href="homeHref" role="menuitem" @click="closeMenus">CBP HOME</a>
          <div class="devtrack-scope">
            <button
              type="button"
              :class="{ 'is-active': moduleScope === 'operations' }"
              @click.stop="moduleScope = 'operations'"
            >
              CBP Operations
              <span class="devtrack-count">{{ navLoading ? '…' : operationsCount }}</span>
            </button>
            <button
              type="button"
              :class="{ 'is-active': moduleScope === 'programs' }"
              @click.stop="moduleScope = 'programs'"
            >
              CBP Programs
              <span class="devtrack-count">{{ programCount }}</span>
            </button>
          </div>
          <template v-if="moduleScope === 'operations'">
            <p v-if="navLoading" class="devtrack-panel-note">Loading modules…</p>
            <p v-else-if="navError" class="devtrack-panel-note">{{ navError }}</p>
            <template v-else>
              <a
                v-for="mod in nav?.modules || []"
                :key="mod.module_key || mod.href"
                class="devtrack-panel-item"
                :class="{ 'is-active': mod.is_active || mod.module_key === 'devtrack' }"
                :href="mod.sso_launch ? '#' : mod.href || '#'"
                role="menuitem"
                @click="openModule(mod, $event)"
              >
                <i :class="moduleMenuIcon(mod.module_key || mod.id, mod.icon)" aria-hidden="true" />
                <span>{{ mod.label }}</span>
              </a>
            </template>
          </template>
          <template v-else>
            <template v-for="group in programGroups" :key="group.title">
              <p class="devtrack-panel-label">{{ group.title }} <span class="devtrack-count">{{ group.items.length }}</span></p>
              <span v-for="item in group.items" :key="item.label" class="devtrack-panel-item is-soon">
                <i :class="moduleMenuIcon(null, item.icon)" aria-hidden="true" />
                <span>{{ item.label }}</span>
              </span>
            </template>
          </template>
        </div>
      </div>

      <div class="devtrack-dd" :class="{ 'is-open': langOpen }">
        <button type="button" class="devtrack-lang" aria-haspopup="listbox" :aria-expanded="langOpen" @click.stop="toggle('lang')">
          <span aria-hidden="true">{{ currentLanguage?.flag }}</span>
          <span>{{ (currentLanguage?.code || 'en').toUpperCase() }}</span>
          <span class="devtrack-caret" aria-hidden="true">▼</span>
        </button>
        <ul v-show="langOpen" class="devtrack-lang-menu" role="listbox">
          <li v-for="lang in languages" :key="lang.code">
            <button
              type="button"
              class="devtrack-lang-item"
              :class="{ 'is-active': lang.code === localeCode }"
              @click="chooseLanguage(lang.code)"
            >
              <span aria-hidden="true">{{ lang.flag }}</span>
              <span>{{ lang.name }}</span>
            </button>
          </li>
        </ul>
      </div>

      <div class="devtrack-theme" role="group" aria-label="Colour theme">
        <button type="button" :class="{ active: theme === 'dark' }" @click="chooseTheme('dark')">Dark</button>
        <button type="button" :class="{ active: theme === 'light' }" @click="chooseTheme('light')">Light</button>
      </div>

      <div v-if="userName" class="devtrack-user" :class="{ 'is-open': userOpen }">
        <button type="button" class="devtrack-user-btn" aria-haspopup="true" :aria-expanded="userOpen" @click.stop="toggle('user')">
          <span class="devtrack-avatar" :style="{ backgroundColor: avatarColor }">{{ initials }}</span>
          <span class="devtrack-user-name">{{ userName }}</span>
          <span class="devtrack-caret" aria-hidden="true">▼</span>
        </button>
        <div class="devtrack-panel devtrack-user-panel" role="menu">
          <a class="devtrack-panel-item" :href="homeHref" role="menuitem" @click="closeMenus">
            <span>Home</span>
            <small>Staff portal overview</small>
          </a>
          <RouterLink class="devtrack-panel-item" :to="{ name: 'security' }" role="menuitem" @click="closeMenus">
            <span>Security</span>
            <small>Password and authenticator</small>
          </RouterLink>
          <button type="button" class="devtrack-panel-item" role="menuitem" @click="logout">
            <span>Log out</span>
            <small>End session</small>
          </button>
        </div>
      </div>
    </div>
  </v-app-bar>
</template>

<style scoped>
.devtrack-topbar {
  background: linear-gradient(90deg, #119a48 0%, color-mix(in srgb, #119a48 72%, #0d7a3a) 100%) !important;
  color: #fff !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08) !important;
  overflow: visible !important;
}

.devtrack-topbar :deep(.v-toolbar__content) {
  overflow: visible !important;
}

.devtrack-chrome {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  margin-right: 8px;
}

.devtrack-chip,
.devtrack-lang,
.devtrack-user-btn,
.devtrack-theme button,
.devtrack-panel-item {
  font-family: inherit;
}

.devtrack-chip,
.devtrack-lang,
.devtrack-user-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 0;
  background: transparent;
  color: #fff;
  cursor: pointer;
  white-space: nowrap;
}

.devtrack-chip {
  padding: 0.4rem 0.7rem;
  border-radius: 4px;
  font-size: 14px;
  font-weight: 500;
}

.devtrack-chip:hover,
.devtrack-dd.is-open .devtrack-chip {
  background: rgba(255, 255, 255, 0.12);
}

.devtrack-grid {
  width: 16px;
  height: 16px;
  fill: currentColor;
}

.devtrack-caret {
  font-size: 0.55rem;
  opacity: 0.85;
}

.devtrack-dd,
.devtrack-user {
  position: relative;
}

.devtrack-lang {
  padding: 0.28rem 0.7rem;
  border-radius: 6px;
  border: 1px solid rgba(255, 255, 255, 0.45);
  background: rgba(255, 255, 255, 0.12);
  font-size: 13px;
  font-weight: 600;
}

.devtrack-lang:hover,
.devtrack-dd.is-open .devtrack-lang {
  background: rgba(255, 255, 255, 0.22);
}

.devtrack-theme {
  display: inline-flex;
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 999px;
  padding: 2px;
  background: rgba(0, 0, 0, 0.12);
}

.devtrack-theme button {
  border: 0;
  background: transparent;
  color: rgba(255, 255, 255, 0.78);
  border-radius: 999px;
  padding: 0.12rem 0.55rem;
  font-size: 0.68rem;
  font-weight: 700;
  cursor: pointer;
  line-height: 1.4;
}

.devtrack-theme button.active {
  background: #fff;
  color: #0d7a3a;
}

.devtrack-user {
  margin-left: 0.15rem;
  padding-left: 0.75rem;
  border-left: 1px solid rgba(255, 255, 255, 0.35);
}

.devtrack-user-btn {
  padding: 0.15rem 0.3rem 0.15rem 0.15rem;
  border-radius: 4px;
  max-width: 240px;
}

.devtrack-user-btn:hover,
.devtrack-user.is-open .devtrack-user-btn {
  background: rgba(255, 255, 255, 0.12);
}

.devtrack-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 0.85rem;
  font-weight: 700;
  border: 2px solid rgba(255, 255, 255, 0.55);
  flex-shrink: 0;
}

.devtrack-user-name {
  font-size: 15px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
}

.devtrack-panel,
.devtrack-lang-menu {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  z-index: 40;
  min-width: 320px;
  max-height: min(70vh, 520px);
  overflow: auto;
  margin: 0;
  padding: 0.4rem 0;
  list-style: none;
  background: #fff;
  color: #2c3e50;
  border: 1px solid #e9ecef;
  border-radius: 8px;
  box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
}

.devtrack-dd.is-open .devtrack-panel,
.devtrack-user.is-open .devtrack-user-panel,
.devtrack-dd.is-open .devtrack-lang-menu {
  display: block;
}

.devtrack-panel-home,
.devtrack-panel-item {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  width: 100%;
  padding: 0.55rem 0.9rem;
  border: 0;
  background: transparent;
  color: #2c3e50;
  text-align: left;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.devtrack-home {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0.35rem 0.4rem 0.1rem;
  padding: 0.6rem 0.35rem;
  border-radius: 4px;
  border: 1px solid rgba(17, 154, 72, 0.22);
  background: linear-gradient(135deg, rgba(17, 154, 72, 0.18), rgba(17, 154, 72, 0.08));
  color: #0d7a3a;
  text-decoration: none;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.04em;
}

.devtrack-home:hover {
  background: #119a48;
  color: #fff;
}

.devtrack-scope {
  display: flex;
  gap: 0.35rem;
  padding: 0.25rem 0.4rem 0.45rem;
}

.devtrack-scope button {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border-radius: 4px;
  border: 1px solid rgba(17, 154, 72, 0.22);
  background: #f7faf8;
  color: #2c3e50;
  font: inherit;
  font-size: 0.78rem;
  font-weight: 700;
  padding: 0.6rem 0.35rem;
  cursor: pointer;
}

.devtrack-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 1.25rem;
  height: 1.15rem;
  padding: 0 0.32rem;
  border-radius: 999px;
  background: rgba(17, 154, 72, 0.14);
  color: #0d7a3a;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;
}

.devtrack-scope button.is-active .devtrack-count {
  background: #119a48;
  color: #fff;
}

.devtrack-scope button.is-active {
  background: linear-gradient(135deg, rgba(17, 154, 72, 0.18), rgba(17, 154, 72, 0.08));
  color: #0d7a3a;
}

.devtrack-panel-item.is-soon {
  color: #8b98a5;
  cursor: default;
}

.devtrack-panel-home {
  margin: 0.15rem 0.4rem 0.35rem;
  width: auto;
  border-radius: 4px;
  font-weight: 700;
  background: linear-gradient(135deg, rgba(17, 154, 72, 0.14), rgba(17, 154, 72, 0.06));
  border: 1px solid rgba(17, 154, 72, 0.22);
}

.devtrack-user-panel .devtrack-panel-item {
  flex-direction: column;
  align-items: flex-start;
  gap: 0.1rem;
}

.devtrack-panel-item small {
  display: block;
  font-size: 0.72rem;
  font-weight: 500;
  opacity: 0.75;
}

.devtrack-panel-item span,
.devtrack-panel-item small {
  display: block;
}

.devtrack-panel-item i {
  width: 28px;
  height: 28px;
  margin-top: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  background: rgba(17, 154, 72, 0.12);
  color: #119a48;
  font-size: 0.85rem;
  flex-shrink: 0;
}

.devtrack-panel-item.is-soon i {
  background: #f1f5f4;
  color: #8b98a5;
}

.devtrack-panel-item:hover:not(.is-soon),
.devtrack-panel-item.is-active,
.devtrack-panel-home:hover,
.devtrack-lang-item:hover,
.devtrack-lang-item.is-active {
  background: #e8f5ee;
  color: #0d7a3a;
}

.devtrack-panel-label,
.devtrack-panel-note {
  margin: 0.35rem 0.9rem;
  font-size: 0.72rem;
  color: #6c757d;
}

.devtrack-panel-label {
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.devtrack-lang-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.7rem;
  padding: 0.6rem 1rem;
  border: 0;
  background: transparent;
  color: #1e293b;
  font: inherit;
  font-size: 0.9rem;
  text-align: left;
  cursor: pointer;
}
</style>
