<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HelpdeskTicket;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
                    'url' => $a->disk === 'public' ? Storage::disk('public')->url($a->path) : $a->path,
                    'original_name' => $a->original_name,
                    'mime_type' => $a->mime_type,
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
            'assigned_user_id' => $this->assigned_user_id,
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
        ];
    }
}
