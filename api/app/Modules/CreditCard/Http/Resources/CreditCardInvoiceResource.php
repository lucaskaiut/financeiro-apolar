<?php

namespace App\Modules\CreditCard\Http\Resources;

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
            'financial_account_id' => $this->whenLoaded('payable', fn () => $this->payable?->uuid),
            'purchases_count' => $this->whenLoaded('purchases', fn () => $this->purchases->count()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
