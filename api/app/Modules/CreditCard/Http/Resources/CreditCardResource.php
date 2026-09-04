<?php

namespace App\Modules\CreditCard\Http\Resources;

use App\Modules\CreditCard\Models\CreditCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditCard
 */
class CreditCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'institution' => $this->institution,
            'limit' => $this->limit !== null ? (float) $this->limit : null,
            'closing_day' => $this->closing_day,
            'due_day' => $this->due_day,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
