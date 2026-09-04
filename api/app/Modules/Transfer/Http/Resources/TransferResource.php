<?php

namespace App\Modules\Transfer\Http\Resources;

use App\Modules\Transfer\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transfer
 */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'from_bank_account_id' => $this->fromCostCenter?->uuid,
            'from_bank_account' => $this->whenLoaded('fromCostCenter', fn () => $this->fromCostCenter?->name),
            'to_bank_account_id' => $this->toCostCenter?->uuid,
            'to_bank_account' => $this->whenLoaded('toCostCenter', fn () => $this->toCostCenter?->name),
            'value' => (float) $this->value,
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
