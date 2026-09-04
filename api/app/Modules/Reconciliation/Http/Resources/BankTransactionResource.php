<?php

namespace App\Modules\Reconciliation\Http\Resources;

use App\Modules\Reconciliation\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankTransaction
 */
class BankTransactionResource extends JsonResource
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
            'date' => $this->date?->toDateString(),
            'value' => (float) $this->value,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'matched_account' => $this->whenLoaded('reconciliation', fn () => $this->reconciliation?->account?->only(['uuid', 'description'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
