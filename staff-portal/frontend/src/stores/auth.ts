import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, getStoredToken, setStoredToken } from '../lib/api'
import { loginUrl, logoutUrl } from '../lib/auth'
import { persistStaffSsoToken } from '../lib/cbpSystems'

export interface PortalProfile {
  staff_id: number
  role: string
  role_id: number
  division_id: number | null
  permissions: Array<number | string>
}

export interface PortalUser {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  profile: PortalProfile | null
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getStoredToken())
  const me = ref<PortalUser | null>(null)

  const isAuthenticated = computed(() => !!token.value)

  function applyToken(t: string | null) {
    token.value = t
    setStoredToken(t)
  }

  async function login(email: string, password: string, remember = false) {
    const { data } = await api.post('/api/v1/auth/login', { email, password, remember })
    applyToken(data.token as string)
    me.value = data.user as PortalUser
    if (data.sso_token) {
      persistStaffSsoToken(data.sso_token as string)
    }
    return data
  }

  async function bootstrapFromBridgeToken(bridgeToken: string) {
    applyToken(bridgeToken)
    await fetchMe()
  }

  async function fetchMe() {
    const { data } = await api.get('/api/v1/me')
    me.value = data.data as PortalUser
  }

  function hasPermission(code: number | string): boolean {
    const perms = me.value?.profile?.permissions ?? []
    return perms.includes(code) || perms.includes(String(code)) || perms.includes(Number(code))
  }

  function logout() {
    applyToken(null)
    me.value = null
    window.location.href = logoutUrl()
  }

  function invalidateSession() {
    applyToken(null)
    me.value = null
  }

  function redirectToLogin() {
    window.location.href = loginUrl()
  }

  return {
    token,
    me,
    isAuthenticated,
    login,
    bootstrapFromBridgeToken,
    fetchMe,
    hasPermission,
    logout,
    invalidateSession,
    redirectToLogin,
    applyToken,
  }
})
