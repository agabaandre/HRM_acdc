import { defineStore } from 'pinia'
import { api, getToken, setToken } from '@/lib/api'

export type AdminUser = {
  id: number
  name: string
  email: string
  phone?: string | null
  organisation?: string | null
  division?: string | null
  directorate?: string | null
  staff_id?: number | null
  registration_source?: string | null
  is_admin: boolean
  is_active: boolean
  two_factor_email_enabled: boolean
  two_factor_totp_enabled: boolean
  totp_required: boolean
  must_setup_totp: boolean
}

export type TwoFactorChallenge = {
  challengeToken: string
  methods: Array<'email' | 'totp'>
  email: string
}

export type TwoFactorStatus = {
  two_factor_email_enabled: boolean
  two_factor_totp_enabled: boolean
  totp_required: boolean
  must_setup_totp: boolean
  has_recovery_codes: boolean
}

type LoginResult = { requires2fa: boolean; mustSetupTotp: boolean }

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AdminUser | null,
    bootstrapped: false,
    pending2fa: null as TwoFactorChallenge | null,
  }),
  getters: {
    isAuthenticated: (s) => s.user !== null,
    isAdmin: (s) => s.user?.is_admin === true,
    mustSetupTotp: (s) => s.user?.must_setup_totp === true,
  },
  actions: {
    normalizeUser(user: AdminUser): AdminUser {
      return {
        ...user,
        totp_required: Boolean(user.totp_required),
        must_setup_totp: Boolean(user.must_setup_totp),
        two_factor_email_enabled: Boolean(user.two_factor_email_enabled),
        two_factor_totp_enabled: Boolean(user.two_factor_totp_enabled),
      }
    },
    applyLoginResponse(data: {
      requires_2fa?: boolean
      challenge_token?: string
      methods?: Array<'email' | 'totp'>
      email?: string
      token?: string
      user?: AdminUser
    }): LoginResult {
      if (data.requires_2fa) {
        this.pending2fa = {
          challengeToken: data.challenge_token as string,
          methods: data.methods as Array<'email' | 'totp'>,
          email: data.email as string,
        }

        return { requires2fa: true, mustSetupTotp: false }
      }

      setToken(data.token as string)
      this.user = this.normalizeUser(data.user as AdminUser)
      this.pending2fa = null

      return { requires2fa: false, mustSetupTotp: this.user.must_setup_totp }
    },
    async login(
      email: string,
      password: string,
      captcha?: { captcha_key: string; captcha: string } | null,
    ): Promise<LoginResult> {
      const payload: Record<string, string> = { email, password }
      if (captcha?.captcha_key) {
        payload.captcha_key = captcha.captcha_key
        payload.captcha = captcha.captcha
      }

      const { data } = await api.post('/admin/auth/login', payload)

      return this.applyLoginResponse(data)
    },
    async ssoLogin(username: string, password: string): Promise<LoginResult> {
      const { data } = await api.post('/admin/auth/sso-login', { username, password })

      return this.applyLoginResponse(data)
    },
    async startMicrosoftLogin(loginHint?: string): Promise<string> {
      const { data } = await api.get('/admin/auth/microsoft/redirect', {
        params: loginHint ? { login_hint: loginHint } : undefined,
      })
      if (!data?.url) {
        throw new Error(data?.message || 'Microsoft 365 SSO is not available.')
      }

      return data.url as string
    },
    async microsoftCallback(code: string, state: string): Promise<LoginResult> {
      const { data } = await api.post('/admin/auth/microsoft/callback', { code, state })

      return this.applyLoginResponse(data)
    },
    async verify2fa(method: 'email' | 'totp', code: string): Promise<LoginResult> {
      if (!this.pending2fa) {
        throw new Error('No pending verification session.')
      }

      const { data } = await api.post('/admin/auth/verify-2fa', {
        challenge_token: this.pending2fa.challengeToken,
        method,
        code,
      })

      setToken(data.token)
      this.user = this.normalizeUser(data.user)
      this.pending2fa = null

      return { requires2fa: false, mustSetupTotp: this.user.must_setup_totp }
    },
    async resend2faEmail() {
      if (!this.pending2fa) {
        throw new Error('No pending verification session.')
      }

      const { data } = await api.post<{ message: string }>('/admin/auth/resend-2fa-email', {
        challenge_token: this.pending2fa.challengeToken,
      })

      return data.message
    },
    clearPending2fa() {
      this.pending2fa = null
    },
    async fetchMe() {
      const { data } = await api.get<AdminUser>('/admin/auth/me')
      this.user = this.normalizeUser(data)
    },
    async fetch2faStatus() {
      const { data } = await api.get<{ data: TwoFactorStatus }>('/admin/auth/2fa/status')
      return data.data
    },
    async enableEmail2fa(password: string) {
      const { data } = await api.post<{ data: TwoFactorStatus; message: string }>(
        '/admin/auth/2fa/email/enable',
        { password },
      )
      if (this.user) {
        this.user.two_factor_email_enabled = data.data.two_factor_email_enabled
      }
      return data
    },
    async disableEmail2fa(password: string) {
      const { data } = await api.post<{ data: TwoFactorStatus; message: string }>(
        '/admin/auth/2fa/email/disable',
        { password },
      )
      if (this.user) {
        this.user.two_factor_email_enabled = data.data.two_factor_email_enabled
      }
      return data
    },
    async setupTotp(password?: string) {
      const { data } = await api.post<{ data: { secret: string; otpauth_url: string } }>(
        '/admin/auth/2fa/totp/setup',
        password ? { password } : {},
      )
      return data.data
    },
    async confirmTotp(code: string) {
      const { data } = await api.post<{
        data: TwoFactorStatus & { recovery_codes?: string[] }
        message: string
      }>('/admin/auth/2fa/totp/confirm', { code })
      if (this.user) {
        this.user.two_factor_totp_enabled = data.data.two_factor_totp_enabled
        this.user.must_setup_totp = data.data.must_setup_totp
        this.user.totp_required = data.data.totp_required
      }
      return data
    },
    async disableTotp(password: string) {
      const { data } = await api.post<{ data: TwoFactorStatus; message: string }>(
        '/admin/auth/2fa/totp/disable',
        { password },
      )
      if (this.user) {
        this.user.two_factor_totp_enabled = data.data.two_factor_totp_enabled
      }
      return data
    },
    async logout() {
      try {
        await api.post('/admin/auth/logout')
      } finally {
        setToken(null)
        this.user = null
        this.pending2fa = null
      }
    },
    async forgotPassword(email: string) {
      const { data } = await api.post<{ message: string }>('/admin/auth/forgot-password', { email })
      return data.message
    },
    async resetPassword(payload: {
      email: string
      token: string
      password: string
      password_confirmation: string
    }) {
      const { data } = await api.post<{ message: string }>('/admin/auth/reset-password', payload)
      return data.message
    },
    async changePassword(payload: {
      current_password: string
      password: string
      password_confirmation: string
    }) {
      const { data } = await api.post<{ message: string }>('/admin/auth/change-password', payload)
      return data.message
    },
    async bootstrap() {
      if (!getToken()) {
        this.bootstrapped = true
        return
      }
      try {
        await this.fetchMe()
      } catch {
        setToken(null)
        this.user = null
      } finally {
        this.bootstrapped = true
      }
    },
  },
})
