import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api, getStoredToken, setStoredToken } from '../lib/api'
import { staffPortalHomeUrl } from '../lib/sso'

export type WorkMode = 'remote' | 'onsite'

export interface MeProfile {
  staff_id: number | null
  sap_no?: string | null
  role: string
  directorate_id: number | null
  division_id: number | null
  duty_station?: string | null
  work_mode?: WorkMode | null
  work_mode_updated_at?: string | null
  can_manage_kb?: boolean
  can_reassign_tickets?: boolean
}

export interface MeUser {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  profile: MeProfile | null
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(getStoredToken())
  const me = ref<MeUser | null>(null)

  const isAuthenticated = computed(() => !!token.value)

  function applyToken(t: string | null) {
    token.value = t
    setStoredToken(t)
  }

  async function exchange(payload: {
    staff_id: number
    email: string
    name: string
    role?: string
    ts: number
    sig: string
    directorate_id?: number
    division_id?: number
  }) {
    const { data } = await api.post('/api/v1/auth/exchange', payload)
    applyToken(data.token as string)
    me.value = data.user as MeUser
    return data
  }

  /** Same JWT as Finance/APM from Staff `home/index` (?token=). */
  async function exchangeStaffSso(ciJwt: string) {
    const { data } = await api.post('/api/v1/auth/staff-sso', { token: ciJwt })
    applyToken(data.token as string)
    me.value = data.user as MeUser
    return data
  }

  async function fetchMe() {
    const { data } = await api.get('/api/v1/me')
    me.value = data.data as MeUser
  }

  /** Update the signed-in agent's current location (remote/onsite/null=clear). */
  async function updateWorkMode(mode: WorkMode | null): Promise<void> {
    const { data } = await api.put('/api/v1/me/work-mode', { work_mode: mode })
    me.value = data.data as MeUser
  }

  function logout() {
    applyToken(null)
    me.value = null
    window.location.href = staffPortalHomeUrl()
  }

  /** Clear token and profile without redirect (e.g. expired session). */
  function invalidateSession() {
    applyToken(null)
    me.value = null
  }

  return { token, me, isAuthenticated, exchange, exchangeStaffSso, fetchMe, updateWorkMode, logout, applyToken, invalidateSession }
})
