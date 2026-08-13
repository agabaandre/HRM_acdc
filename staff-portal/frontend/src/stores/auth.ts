import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, getStoredToken, setStoredToken } from '../lib/api'
import { loginUrl, navigateToLogout } from '../lib/auth'
import type { ImpersonationAuthPayload, ImpersonationStatus } from '../lib/authAdminApi'
import { persistStaffSsoToken } from '../lib/cbpSystems'

export interface PortalProfile {
  staff_id: number
  role: string
  role_id: number
  division_id: number | null
  permissions: Array<number | string>
  is_hr?: boolean
  is_hr_admin?: boolean
  is_system_admin?: boolean
  allow_email_login?: boolean
  password_login_available?: boolean
  is_impersonated?: boolean
}

export interface PortalUser {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  profile: PortalProfile | null
  impersonation?: ImpersonationStatus | null
  /** SPA feature flags from Settings → Portal modules */
  enabled_modules?: Record<string, boolean>
}

const ME_CACHE_KEY = 'staff_portal_me_cache_v2'
const ME_CACHE_TTL_MS = 5 * 60_000

type MeCachePayload = { savedAt: number; user: PortalUser }

function readMeCache(): PortalUser | null {
  try {
    const raw = sessionStorage.getItem(ME_CACHE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as MeCachePayload
    if (!parsed?.user || !parsed.savedAt) return null
    if (Date.now() - parsed.savedAt > ME_CACHE_TTL_MS) {
      sessionStorage.removeItem(ME_CACHE_KEY)
      return null
    }
    return parsed.user
  } catch {
    return null
  }
}

function writeMeCache(user: PortalUser | null): void {
  try {
    if (!user) {
      sessionStorage.removeItem(ME_CACHE_KEY)
      return
    }
    const payload: MeCachePayload = { savedAt: Date.now(), user }
    sessionStorage.setItem(ME_CACHE_KEY, JSON.stringify(payload))
  } catch {
    /* ignore quota / private mode */
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getStoredToken())
  const me = ref<PortalUser | null>(token.value ? readMeCache() : null)
  let meInflight: Promise<PortalUser> | null = null

  const isAuthenticated = computed(() => !!token.value)
  const passwordLoginAvailable = computed(() => !!me.value?.profile?.password_login_available)
  const impersonation = computed<ImpersonationStatus | null>(() => {
    const fromMe = me.value?.impersonation
    if (fromMe?.active) return fromMe
    if (me.value?.profile?.is_impersonated) {
      return {
        active: true,
        user_name: me.value.name,
        user_id: me.value.id,
        original_user_name: null,
        remaining_seconds: null,
      }
    }
    return fromMe ?? null
  })
  const isImpersonating = computed(() => !!impersonation.value?.active)

  function applyToken(t: string | null) {
    token.value = t
    setStoredToken(t)
    if (!t) {
      me.value = null
      writeMeCache(null)
      meInflight = null
    }
  }

  async function login(email: string, password: string, remember = false) {
    const { data } = await api.post('/api/v1/auth/login', { email, password, remember })
    applyToken(data.token as string)
    me.value = data.user as PortalUser
    writeMeCache(me.value)
    if (data.sso_token) {
      persistStaffSsoToken(data.sso_token as string)
    }
    return data
  }

  async function bootstrapFromBridgeToken(bridgeToken: string) {
    applyToken(bridgeToken)
    await fetchMe(true)
  }

  /**
   * Load current user. Uses short session cache + in-flight dedupe so route
   * guards and bootstrap never serialize duplicate /me calls.
   */
  async function fetchMe(force = false): Promise<PortalUser> {
    if (!force && me.value) {
      return me.value
    }
    if (!force) {
      const cached = readMeCache()
      if (cached) {
        me.value = cached
        return cached
      }
    }
    if (meInflight) {
      return meInflight
    }

    meInflight = api
      .get('/api/v1/me')
      .then(({ data }) => {
        const user = data.data as PortalUser
        me.value = user
        writeMeCache(user)
        return user
      })
      .finally(() => {
        meInflight = null
      })

    return meInflight
  }

  /** Background refresh — never blocks UI. */
  function refreshMeInBackground(): void {
    if (!token.value) return
    void fetchMe(true).catch(() => {
      /* keep cached me; router/API 401 handler will clear session if needed */
    })
  }

  function hasPermission(code: number | string): boolean {
    const perms = me.value?.profile?.permissions ?? []
    return perms.includes(code) || perms.includes(String(code)) || perms.includes(Number(code))
  }

  /** Defaults: payroll off, everything else on (matches backend catalog). */
  function isModuleEnabled(moduleKey: string): boolean {
    if (!moduleKey) return true
    const map = me.value?.enabled_modules
    if (!map || !(moduleKey in map)) {
      return moduleKey !== 'payroll'
    }
    return !!map[moduleKey]
  }

  function logout() {
    applyToken(null)
    me.value = null
    writeMeCache(null)
    navigateToLogout()
  }

  function invalidateSession() {
    applyToken(null)
    me.value = null
    writeMeCache(null)
  }

  function redirectToLogin() {
    window.location.href = loginUrl()
  }

  function applyImpersonationPayload(payload: ImpersonationAuthPayload) {
    applyToken(payload.token)
    me.value = payload.user as PortalUser
    writeMeCache(me.value)
    if (payload.sso_token) {
      persistStaffSsoToken(payload.sso_token)
    }
  }

  return {
    token,
    me,
    isAuthenticated,
    passwordLoginAvailable,
    impersonation,
    isImpersonating,
    login,
    bootstrapFromBridgeToken,
    fetchMe,
    refreshMeInBackground,
    hasPermission,
    isModuleEnabled,
    logout,
    invalidateSession,
    redirectToLogin,
    applyToken,
    applyImpersonationPayload,
  }
})
