<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HelpdeskCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HelpdeskCategory */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'default_priority' => $this->default_priority ?? 'medium',
            'business_unit_id' => $this->business_unit_id,
            'ai_description' => $this->ai_description,
            'business_unit' => $this->whenLoaded('businessUnit', fn () => $this->businessUnit ? [
                'id' => $this->businessUnit->id,
                'name' => $this->businessUnit->name,
                'slug' => $this->businessUnit->slug,
                'allows_anonymous' => (bool) $this->businessUnit->allows_anonymous,
            ] : null),
        ];
    }
}
