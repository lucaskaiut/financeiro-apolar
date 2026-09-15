<?php

namespace App\Modules\Installment\Http\Resources;

use App\Modules\Account\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentDetailResource extends JsonResource
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
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->bank_account,
            'company_id' => $this->company_id,
            'company' => $this->company,
            'cost_center_id' => $this->cost_center_id,
            'cost_center' => $this->cost_center,
            'category_id' => $this->category_id,
            'category' => $this->category,
            'subcategory_id' => $this->subcategory_id,
            'subcategory' => $this->subcategory,
            'installments_count' => $this->installments_count,
            'installment_total' => $this->installment_total,
            'total_value' => $this->total_value,
            'first_due_date' => $this->first_due_date,
            'last_due_date' => $this->last_due_date,
            'expected_date' => $this->expected_date,
            'observation' => $this->observation,
            'settled_count' => $this->settled_count,
            'cancelled_count' => $this->cancelled_count,
            'is_card_purchase' => $this->is_card_purchase,
            'installments' => AccountResource::collection($this->installments),
        ];
    }
}
