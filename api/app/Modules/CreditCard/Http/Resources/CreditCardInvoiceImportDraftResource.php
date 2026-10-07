<?php

namespace App\Modules\CreditCard\Http\Resources;

use App\Modules\CreditCard\Models\CreditCardInvoiceImportDraft;
use App\Modules\CreditCard\Services\InvoiceImportDraftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditCardInvoiceImportDraft
 */
class CreditCardInvoiceImportDraftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<array<string, mixed>> $items */
        $items = is_array($this->items) ? $this->items : [];
        $summary = app(InvoiceImportDraftService::class)->summarizeItems($items);

        return [
            'id' => $this->uuid,
            'credit_card_id' => $this->credit_card_id,
            'reference_month' => $this->reference_month,
            'paid_date' => $this->paid_date?->toDateString(),
            'bank_account_id' => $this->bank_account_id,
            'category_id' => $this->category_id,
            'cost_center_id' => $this->cost_center_id,
            'due_date' => $this->due_date?->toDateString(),
            'step' => $this->step,
            'source_filename' => $this->source_filename,
            'items' => $items,
            'progress' => $summary,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
