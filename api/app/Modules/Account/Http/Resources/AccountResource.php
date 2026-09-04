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
            'cost_center_id' => $this->costCenter?->uuid,
            'cost_center' => $this->whenLoaded('costCenter', fn () => $this->costCenter?->name),
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
