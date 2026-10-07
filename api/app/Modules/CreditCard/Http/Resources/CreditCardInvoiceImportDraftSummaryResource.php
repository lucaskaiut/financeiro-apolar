<?php

namespace App\Modules\CreditCard\Http\Resources;

use App\Modules\CreditCard\Models\CreditCardInvoiceImportDraft;
use App\Modules\CreditCard\Services\InvoiceImportDraftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditCardInvoiceImportDraft
 */
class CreditCardInvoiceImportDraftSummaryResource extends JsonResource
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
            'reference_month' => $this->reference_month,
            'step' => $this->step,
            'source_filename' => $this->source_filename,
            'progress' => $summary,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
