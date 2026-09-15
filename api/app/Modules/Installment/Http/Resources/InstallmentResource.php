<?php

namespace App\Modules\Installment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'counterparty' => $this->counterparty,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'bank_account' => $this->bank_account,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'installments_count' => $this->installments_count,
            'installment_total' => $this->installment_total,
            'total_value' => $this->total_value,
            'first_due_date' => $this->first_due_date,
            'last_due_date' => $this->last_due_date,
            'settled_count' => $this->settled_count,
            'cancelled_count' => $this->cancelled_count,
            'is_card_purchase' => $this->is_card_purchase,
        ];
    }
}
