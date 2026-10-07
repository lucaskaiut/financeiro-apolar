<?php

namespace App\Modules\CreditCard\Services;

use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoiceImportDraft;
use App\Modules\User\Models\User;
use Illuminate\Support\Collection;

class InvoiceImportDraftService
{
    /**
     * @return Collection<int, CreditCardInvoiceImportDraft>
     */
    public function listForCard(CreditCard $creditCard): Collection
    {
        return CreditCardInvoiceImportDraft::query()
            ->where('credit_card_id', $creditCard->uuid)
            ->orderByDesc('updated_at')
            ->get();
    }

    public function findForCard(CreditCard $creditCard, string $referenceMonth): ?CreditCardInvoiceImportDraft
    {
        return CreditCardInvoiceImportDraft::query()
            ->where('credit_card_id', $creditCard->uuid)
            ->where('reference_month', $referenceMonth)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(CreditCard $creditCard, User $user, array $data): CreditCardInvoiceImportDraft
    {
        $referenceMonth = $data['reference_month'];

        $draft = CreditCardInvoiceImportDraft::query()->firstOrNew([
            'credit_card_id' => $creditCard->uuid,
            'reference_month' => $referenceMonth,
        ]);

        $draft->fill([
            'user_id' => $user->id,
            'paid_date' => $data['paid_date'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'cost_center_id' => $data['cost_center_id'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'step' => (int) ($data['step'] ?? 2),
            'source_filename' => $data['source_filename'] ?? null,
            'items' => $data['items'],
        ]);

        $draft->save();

        return $draft->refresh();
    }

    public function delete(CreditCard $creditCard, string $referenceMonth): void
    {
        CreditCardInvoiceImportDraft::query()
            ->where('credit_card_id', $creditCard->uuid)
            ->where('reference_month', $referenceMonth)
            ->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{classified: int, pending: int, ignored: int, total: int}
     */
    public function summarizeItems(array $items): array
    {
        $classified = 0;
        $pending = 0;
        $ignored = 0;

        foreach ($items as $item) {
            if (($item['status'] ?? 'normal') === 'ignored') {
                $ignored++;

                continue;
            }

            if ($this->isItemClassified($item)) {
                $classified++;
            } else {
                $pending++;
            }
        }

        return [
            'classified' => $classified,
            'pending' => $pending,
            'ignored' => $ignored,
            'total' => count($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isItemClassified(array $item): bool
    {
        $splits = $item['splits'] ?? [];

        if (is_array($splits) && $splits !== []) {
            foreach ($splits as $split) {
                if (! is_array($split)) {
                    return false;
                }

                if (blank($split['description'] ?? null) || blank($split['category_id'] ?? null)) {
                    return false;
                }

                if ((float) ($split['value'] ?? 0) <= 0) {
                    return false;
                }
            }

            $sum = round(array_sum(array_map(
                fn ($split) => (float) ($split['value'] ?? 0),
                $splits,
            )), 2);

            $total = round((float) ($item['value'] ?? 0), 2);

            return abs($sum - $total) < 0.01;
        }

        return filled($item['category_id'] ?? null);
    }
}
