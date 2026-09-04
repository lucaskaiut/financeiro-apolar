<?php

namespace App\Modules\CostCenter\Http\Resources;

use App\Modules\CostCenter\Models\CostCenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CostCenter
 */
class CostCenterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'bank' => $this->bank,
            'agency' => $this->agency,
            'account' => $this->account,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'initial_balance' => (float) $this->initial_balance,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
