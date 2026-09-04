<?php

namespace App\Modules\Category\Http\Resources;

use App\Modules\Category\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'color' => $this->color,
            'status' => $this->status,
            'parent_id' => $this->parent?->uuid,
            'parent_name' => $this->whenLoaded('parent', fn () => $this->parent?->name),
            'subcategories_count' => $this->subcategories_count ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
