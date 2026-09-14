<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->helpdeskProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => StaffPhotoUrl::forUser($this->resource),
            'profile' => $p ? [
                'staff_id' => $p->staff_id,
                'sap_no' => $p->sap_no ? trim((string) $p->sap_no) : null,
                'role' => $p->role,
                'acts_as_agent' => $p->actsAsAgent(),
                'is_designated_agent' => (bool) $p->is_designated_agent,
                'is_helpdesk_admin' => $p->isHelpdeskAdmin(),
                'directorate_id' => $p->directorate_id,
                'division_id' => $p->division_id,
                'duty_station' => $p->duty_station ? trim((string) $p->duty_station) : null,
                'work_mode' => $p->work_mode,
                'work_mode_updated_at' => $p->work_mode_updated_at?->toIso8601String(),
                'can_manage_kb' => $p->canManageKnowledgeBase(),
                'can_reassign_tickets' => $p->canReassignTickets(),
                'can_delete_request_attachments' => $p->canDeleteRequestAttachments(),
                'can_change_ticket_category' => $p->canChangeTicketCategory(),
                'can_manage_it_assets' => $p->canManageItAssets(),
                'can_manage_licenses' => $p->canManageLicenses(),
                'can_manage_information_systems' => $p->canManageInformationSystems(),
                'can_submit_software_requests' => $p->canSubmitSoftwareRequests(),
                'can_approve_software_requests' => $p->canApproveSoftwareRequests(),
                'can_manage_software_requests' => $p->canManageSoftwareRequests(),
                'can_process_hosting_requests' => $p->canProcessHostingRequests(),
                'can_process_innovation_requests' => $p->canProcessInnovationRequests(),
                'has_tools_access' => $p->hasAnyToolsAccess(),
            ] : null,
        ];
    }
}
