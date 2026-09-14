<?php

namespace App\Services;

use App\Models\HelpdeskProfile;
use App\Models\HelpdeskSetting;

class HelpdeskPermissionService
{
    /**
     * Apply Helpdesk-local permission overrides after SSO role resolution.
     */
    public function applySsoRoleOverrides(HelpdeskProfile $existingProfile, string $ssoRole): string
    {
        if ($existingProfile->is_designated_agent === true && $ssoRole !== HelpdeskProfile::ROLE_ADMIN) {
            return HelpdeskProfile::ROLE_AGENT;
        }

        if ($existingProfile->grant_supervisor_access === true
            && $ssoRole === HelpdeskProfile::ROLE_USER) {
            return HelpdeskProfile::ROLE_SUPERVISOR;
        }

        return $ssoRole;
    }

    /**
     * Recompute helpdesk_profiles.role from stored portal signals (not grant_helpdesk_admin).
     */
    public function syncEffectiveRole(HelpdeskProfile $profile): void
    {
        if ($this->isPortalAdmin($profile)) {
            $profile->role = HelpdeskProfile::ROLE_ADMIN;

            return;
        }

        if ($profile->is_designated_agent === true) {
            $profile->role = HelpdeskProfile::ROLE_AGENT;

            return;
        }

        if ($profile->grant_supervisor_access === true) {
            $profile->role = HelpdeskProfile::ROLE_SUPERVISOR;

            return;
        }

        if ($this->isDefaultAgentDivision($profile)) {
            $profile->role = HelpdeskProfile::ROLE_AGENT;

            return;
        }

        $profile->role = HelpdeskProfile::ROLE_USER;
    }

    private function isPortalAdmin(HelpdeskProfile $profile): bool
    {
        $portalRole = (int) ($profile->staff_portal_role ?? 0);
        if ($portalRole <= 0) {
            return false;
        }

        $adminRoleIds = collect(explode(',', (string) config('helpdesk.sso_staff_role_ids_admin', '10')))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return in_array($portalRole, $adminRoleIds, true);
    }

    private function isDefaultAgentDivision(HelpdeskProfile $profile): bool
    {
        $divisionId = (int) ($profile->division_id ?? 0);
        if ($divisionId <= 0) {
            return false;
        }

        $agentDivCsv = HelpdeskSetting::getValue(HelpdeskSetting::KEY_DEFAULT_AGENT_DIVISION_IDS);
        if ($agentDivCsv === null || trim($agentDivCsv) === '') {
            $agentDivCsv = (string) config('helpdesk.default_agent_division_ids', '21');
        }

        $agentDivIds = collect(explode(',', $agentDivCsv))
            ->map(fn (string $v) => (int) trim($v))
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return in_array($divisionId, $agentDivIds, true);
    }
}
