<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HelpdeskSetting;
use App\Models\HelpdeskTicket;
use App\Support\HelpdeskAttachmentUrl;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HelpdeskTicket */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attachments = [];
        if ($this->relationLoaded('attachments')) {
            foreach ($this->attachments as $a) {
                $attachments[] = [
                    'id' => $a->id,
                    'url' => HelpdeskAttachmentUrl::forAttachment($a),
                    'original_name' => $a->original_name,
                    'mime_type' => $a->mime_type,
                    'is_inline' => $a->isInlineImage(),
                ];
            }
        }

        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'subject' => $this->subject,
            'description' => $this->description,
            'resolution_summary' => $this->resolution_summary,
            'priority' => $this->priority,
            'status' => $this->status,
            'source' => $this->source,
            'agent_logged_for_requester' => (bool) $this->agent_logged_for_requester,
            'requester_staff_id' => $this->requester_staff_id,
            'requester_name' => $this->requester_name,
            'requester_email' => $this->requester_email,
            'is_anonymous' => (bool) $this->is_anonymous,
            'business_unit_id' => $this->business_unit_id,
            'linked_it_asset_id' => $this->linked_it_asset_id,
            'linked_information_system_id' => $this->linked_information_system_id,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_group_id' => $this->assigned_group_id,
            'directorate_id' => $this->directorate_id,
            'division_id' => $this->division_id,
            'country_id' => $this->country_id,
            'sla_response_due_at' => $this->sla_response_due_at,
            'sla_resolution_due_at' => $this->sla_resolution_due_at,
            'resolved_at' => $this->resolved_at,
            'resolution_confirmed_at' => $this->resolution_confirmed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'business_unit' => $this->when(
                $this->relationLoaded('businessUnit') && $this->businessUnit !== null,
                fn () => [
                    'id' => $this->businessUnit->id,
                    'name' => $this->businessUnit->name,
                    'slug' => $this->businessUnit->slug,
                    'description' => $this->businessUnit->description,
                    'allows_anonymous' => (bool) $this->businessUnit->allows_anonymous,
                    'allows_asset_link_on_resolve' => (bool) $this->businessUnit->allows_asset_link_on_resolve,
                    'allows_information_system_link_on_resolve' => (bool) $this->businessUnit->allows_information_system_link_on_resolve,
                ]
            ),
            'linked_it_asset' => $this->when(
                $this->relationLoaded('linkedItAsset') && $this->linkedItAsset !== null,
                fn () => [
                    'id' => $this->linkedItAsset->id,
                    'asset_tag' => $this->linkedItAsset->asset_tag,
                    'name' => $this->linkedItAsset->name,
                    'brand' => $this->linkedItAsset->brandRelation?->name ?? $this->linkedItAsset->brand,
                    'model' => $this->linkedItAsset->model,
                    'serial_number' => $this->linkedItAsset->serial_number,
                    'status' => $this->linkedItAsset->status,
                ]
            ),
            'linked_information_system' => $this->when(
                $this->relationLoaded('linkedInformationSystem') && $this->linkedInformationSystem !== null,
                fn () => [
                    'id' => $this->linkedInformationSystem->id,
                    'name' => $this->linkedInformationSystem->name,
                    'status' => $this->linkedInformationSystem->status,
                    'version' => $this->linkedInformationSystem->version,
                ]
            ),
            'attachments' => $attachments,
            'assignee' => $this->when(
                $this->relationLoaded('assignee') && $this->assignee !== null,
                fn () => [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                    'avatar_url' => StaffPhotoUrl::forUser($this->assignee),
                    'work_mode' => $this->assignee->relationLoaded('helpdeskProfile')
                        ? $this->assignee->helpdeskProfile?->work_mode
                        : null,
                ]
            ),
            'assignees' => $this->when(
                $this->relationLoaded('assignees'),
                fn () => $this->assignees->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => StaffPhotoUrl::forUser($user),
                    'is_primary' => (bool) ($user->pivot->is_primary ?? false),
                ])->values()
            ),
            'assigned_group' => $this->when(
                $this->relationLoaded('assignedGroup') && $this->assignedGroup !== null,
                fn () => [
                    'id' => $this->assignedGroup->id,
                    'name' => $this->assignedGroup->name,
                    'slug' => $this->assignedGroup->slug,
                ]
            ),
            'requester_unsatisfied_follow_up_enabled' => HelpdeskSetting::requesterUnsatisfiedFollowUpEnabled(),
        ];
    }
}
