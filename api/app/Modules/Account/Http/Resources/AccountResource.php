<?php

namespace App\Modules\Account\Http\Resources;

use App\Modules\Account\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialAccount
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'description' => $this->description,
            'counterparty' => $this->counterparty,
            'bank_account_id' => $this->bankAccount?->uuid,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            'company_id' => $this->company?->uuid,
            'company' => $this->whenLoaded('company', fn () => $this->company?->name),
            'cost_center_id' => $this->costCenter?->uuid,
            'cost_center' => $this->whenLoaded('costCenter', fn () => $this->costCenter?->name),
            'credit_card_id' => $this->creditCard?->uuid,
            'credit_card' => $this->whenLoaded('creditCard', fn () => $this->creditCard?->name),
            'credit_card_invoice_id' => $this->credit_card_invoice_id,
            'is_card_purchase' => $this->is_card_purchase,
            'is_card_invoice_payable' => $this->is_card_invoice_payable,
            'allocation_mode' => $this->allocation_mode,
            'category_id' => $this->category?->uuid,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category?->name,
                'color' => $this->category?->color,
                'type' => $this->category?->type?->value,
            ]),
            'subcategory_id' => $this->subcategory?->uuid,
            'subcategory' => $this->whenLoaded('subcategory', fn () => [
                'name' => $this->subcategory?->name,
            ]),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($allocation) => [
                'id' => $allocation->uuid,
                'cost_center_id' => $allocation->cost_center_id,
                'cost_center' => $allocation->costCenter?->name,
                'company_id' => $allocation->company_id,
                'company' => $allocation->company?->name,
                'category_id' => $allocation->category_id,
                'category' => $allocation->category?->name,
                'subcategory_id' => $allocation->subcategory_id,
                'value' => (float) $allocation->value,
                'percentage' => $allocation->percentage !== null ? (float) $allocation->percentage : null,
            ])),
            'value' => (float) $this->value,
            'settled_amount' => $this->settled_amount,
            'remaining_amount' => $this->remaining_amount,
            'due_date' => $this->due_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'paid_date' => $this->paid_date?->toDateString(),
            'observation' => $this->observation,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'installment_group_id' => $this->installment_group_id,
            'installment_number' => $this->installment_number,
            'installment_total' => $this->installment_total,
            'recurrence_id' => $this->whenLoaded('recurrence', fn () => $this->recurrence?->uuid),
            'transfer_id' => $this->transfer_id,
            'is_reconciled' => $this->isReconciled(),
            'settlements' => SettlementResource::collection($this->whenLoaded('settlements')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
