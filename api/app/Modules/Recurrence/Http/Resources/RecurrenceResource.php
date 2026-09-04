<?php

namespace App\Modules\Recurrence\Http\Resources;

use App\Modules\Recurrence\Models\Recurrence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Recurrence
 */
class RecurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type,
            'description' => $this->description,
            'counterparty' => $this->counterparty,
            'cost_center_id' => $this->costCenter?->uuid,
            'cost_center' => $this->whenLoaded('costCenter', fn () => $this->costCenter?->name),
            'category_id' => $this->category?->uuid,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'subcategory_id' => $this->subcategory?->uuid,
            'subcategory' => $this->whenLoaded('subcategory', fn () => $this->subcategory?->name),
            'value' => (float) $this->value,
            'frequency' => $this->frequency?->value,
            'frequency_label' => $this->frequency?->label(),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'max_occurrences' => $this->max_occurrences,
            'day_of_month' => $this->day_of_month,
            'status' => $this->status,
            'occurrences_count' => $this->accounts_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
