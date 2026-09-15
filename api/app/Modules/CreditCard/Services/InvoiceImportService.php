<?php

namespace App\Modules\CreditCard\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Services\AccountService;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Support\CreditCardStatementParser;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Importa a fatura do cartão: extrai as compras (preview), cria as compras,
 * fecha a fatura e baixa a conta a pagar.
 */
class InvoiceImportService
{
    public function __construct(
        private readonly CreditCardStatementParser $parser,
        private readonly CreditCardService $creditCards,
        private readonly AccountService $accounts,
    ) {}

    /**
     * Extrai as compras do arquivo e detecta possíveis duplicatas, sem persistir nada.
     *
     * @return array{due_date: string, total: float, items: list<array<string, mixed>>}
     */
    public function preview(
        CreditCard $creditCard,
        string $referenceMonth,
        string $categoryId,
        ?string $costCenterId,
        string $filePath,
    ): array {
        $items = $this->parser->parse($filePath);

        $existing = FinancialAccount::query()
            ->where('credit_card_id', $creditCard->uuid)
            ->where('is_card_purchase', true)
            ->get(['uuid', 'purchase_date', 'value', 'description'])
            ->mapWithKeys(fn (FinancialAccount $purchase) => [
                $this->dedupKey($purchase->purchase_date?->toDateString(), (float) $purchase->value) => $purchase,
            ]);

        $preview = [];

        foreach ($items as $index => $item) {
            $key = $this->dedupKey($item['purchase_date'], $item['value']);
            $duplicate = $existing->get($key);

            $preview[] = [
                'id' => $index,
                'purchase_date' => $item['purchase_date'],
                'description' => $item['description'],
                'value' => $item['value'],
                'category_id' => $categoryId,
                'subcategory_id' => null,
                'cost_center_id' => $costCenterId,
                'status' => $duplicate ? 'ignored' : 'normal',
                'is_duplicate' => $duplicate !== null,
                'existing' => $duplicate !== null ? [
                    'date' => $duplicate->purchase_date?->toDateString(),
                    'value' => (float) $duplicate->value,
                    'description' => $duplicate->description,
                ] : null,
            ];
        }

        return [
            'due_date' => $this->creditCards->invoiceDueDate($creditCard, $referenceMonth)->toDateString(),
            'total' => round(array_sum(array_column($items, 'value')), 2),
            'items' => $preview,
        ];
    }

    /**
     * @param  list<array{description: string, purchase_date: string, value: numeric, category_id: string, subcategory_id?: ?string, cost_center_id?: ?string, status?: string, splits?: list<array{description: string, value: numeric, category_id: string, subcategory_id?: ?string, cost_center_id?: ?string}>}>  $items
     * @return array{imported: int, ignored: int, total: float, invoice_id: string}
     */
    public function import(
        CreditCard $creditCard,
        string $referenceMonth,
        string $bankAccountId,
        string $paidDate,
        array $items,
        User $user,
    ): array {
        return DB::transaction(function () use ($creditCard, $referenceMonth, $bankAccountId, $paidDate, $user, $items): array {
            $dueDate = $this->creditCards->invoiceDueDate($creditCard, $referenceMonth)->toDateString();

            $imported = 0;
            $ignored = 0;

            foreach ($items as $item) {
                if (($item['status'] ?? 'normal') === 'ignored') {
                    $ignored++;

                    continue;
                }

                $splits = $item['splits'] ?? [];

                if ($splits !== []) {
                    foreach ($splits as $split) {
                        $this->createPurchase($creditCard, $dueDate, [
                            'description' => $split['description'],
                            'value' => (float) $split['value'],
                            'purchase_date' => $item['purchase_date'],
                            'category_id' => $split['category_id'],
                            'subcategory_id' => $split['subcategory_id'] ?? null,
                            'cost_center_id' => $split['cost_center_id'] ?? null,
                        ]);
                    }

                    $imported += count($splits);

                    continue;
                }

                $this->createPurchase($creditCard, $dueDate, [
                    'description' => $item['description'],
                    'value' => (float) $item['value'],
                    'purchase_date' => $item['purchase_date'],
                    'category_id' => $item['category_id'],
                    'subcategory_id' => $item['subcategory_id'] ?? null,
                    'cost_center_id' => $item['cost_center_id'] ?? null,
                ]);

                $imported++;
            }

            if ($imported === 0) {
                throw new \InvalidArgumentException('Nenhuma compra selecionada para importar.');
            }

            $invoice = $this->creditCards->closeInvoice($creditCard, $referenceMonth, $bankAccountId);

            $this->accounts->settle($invoice->payable, $user, [
                'settled_at' => $paidDate,
                'method' => 'credit_card_invoice',
            ]);

            return [
                'imported' => $imported,
                'ignored' => $ignored,
                'total' => (float) $invoice->total_value,
                'invoice_id' => $invoice->uuid,
            ];
        });
    }

    /**
     * @param  array{description: string, value: float, purchase_date: string, category_id: string, subcategory_id: ?string, cost_center_id: ?string}  $data
     */
    private function createPurchase(CreditCard $creditCard, string $dueDate, array $data): FinancialAccount
    {
        return FinancialAccount::query()->create([
            'type' => AccountType::Payable,
            'description' => $data['description'],
            'credit_card_id' => $creditCard->uuid,
            'category_id' => $data['category_id'],
            'subcategory_id' => $data['subcategory_id'],
            'cost_center_id' => $data['cost_center_id'],
            'value' => $data['value'],
            'purchase_date' => $data['purchase_date'],
            'due_date' => $dueDate,
            'status' => AccountStatus::Open,
            'is_card_purchase' => true,
            'is_card_invoice_payable' => false,
        ]);
    }

    private function dedupKey(?string $date, float $value): string
    {
        return ($date ?? '').'|'.number_format(round($value, 2), 2, '.', '');
    }
}
