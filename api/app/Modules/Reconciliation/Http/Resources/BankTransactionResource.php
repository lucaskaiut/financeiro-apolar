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
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            'date' => $this->date?->toDateString(),
            'value' => (float) $this->value,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_id' => $this->transaction_id,
            'status' => $this->status,
            'matched_account' => $this->whenLoaded('reconciliation', fn () => $this->reconciliation?->account?->only(['uuid', 'description'])),
            'matched_accounts' => $this->whenLoaded('reconciliations', function () {
                return $this->reconciliations
                    ->filter(fn ($item) => $item->reversed_at === null && $item->account !== null)
                    ->map(fn ($item) => $item->account->only(['uuid', 'description']))
                    ->values();
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
