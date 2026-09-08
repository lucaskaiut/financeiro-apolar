<?php

namespace App\Modules\Account\Http\Resources;

use App\Modules\Account\Models\AccountAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountAllocation
 */
class AccountAllocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->whenLoaded('costCenter', fn () => $this->costCenter?->name),
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->name),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category?->name,
                'color' => $this->category?->color,
                'type' => $this->category?->type?->value,
            ]),
            'subcategory_id' => $this->subcategory_id,
            'subcategory' => $this->whenLoaded('subcategory', fn () => [
                'name' => $this->subcategory?->name,
            ]),
            'value' => (float) $this->value,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
        ];
    }
}
