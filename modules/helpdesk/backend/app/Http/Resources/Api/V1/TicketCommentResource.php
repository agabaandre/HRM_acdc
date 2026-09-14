<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HelpdeskTicketComment;
use App\Support\StaffPhotoUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HelpdeskTicketComment */
class TicketCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'created_at' => $this->created_at,
            'author' => $this->when(
                $this->relationLoaded('user') && $this->user !== null,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'avatar_url' => StaffPhotoUrl::forUser($this->user),
                ]
            ),
            'author_staff_id' => $this->author_staff_id,
        ];
    }
}
