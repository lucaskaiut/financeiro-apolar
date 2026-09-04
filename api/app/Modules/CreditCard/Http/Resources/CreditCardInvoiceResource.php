<?php

namespace App\Modules\CreditCard\Http\Resources;

use App\Modules\Account\Http\Resources\AccountResource;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditCardInvoice
 */
class CreditCardInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'credit_card_id' => $this->credit_card_id,
            'reference_month' => $this->reference_month,
            'closing_date' => $this->closing_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'total_value' => (float) $this->total_value,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'financial_account_id' => $this->relationLoaded('payable')
                ? $this->payable?->uuid
                : null,
            'purchases_count' => $this->purchases_count
                ?? ($this->relationLoaded('purchases') ? $this->purchases->count() : null),
            'purchases' => AccountResource::collection($this->whenLoaded('purchases')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
