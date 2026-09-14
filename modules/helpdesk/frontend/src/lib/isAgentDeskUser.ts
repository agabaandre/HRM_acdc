import type { MeProfile } from '../stores/auth'

/** True when the signed-in user sees the agent desk / kanban workflows. */
export function isAgentDeskUser(profile: MeProfile | null | undefined): boolean {
  if (!profile) {
    return false
  }
  return ['agent', 'supervisor', 'admin', 'auditor'].includes(profile.role)
}
