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
 * Importa a fatura do cartão: cria as compras, fecha a fatura e baixa a conta a pagar.
 */
class InvoiceImportService
{
    public function __construct(
        private readonly CreditCardStatementParser $parser,
        private readonly CreditCardService $creditCards,
        private readonly AccountService $accounts,
    ) {}

    /**
     * @return array{imported: int, skipped: int, total: float, invoice_id: string}
     */
    public function import(
        CreditCard $creditCard,
        string $referenceMonth,
        string $bankAccountId,
        string $categoryId,
        ?string $costCenterId,
        string $paidDate,
        string $filePath,
        User $user,
    ): array {
        $items = $this->parser->parse($filePath);

        return DB::transaction(function () use (
            $creditCard,
            $referenceMonth,
            $bankAccountId,
            $categoryId,
            $costCenterId,
            $paidDate,
            $user,
            $items,
        ): array {
            $dueDate = $this->creditCards->invoiceDueDate($creditCard, $referenceMonth)->toDateString();

            $existing = FinancialAccount::query()
                ->where('credit_card_id', $creditCard->uuid)
                ->where('is_card_purchase', true)
                ->get(['purchase_date', 'value'])
                ->mapWithKeys(fn (FinancialAccount $purchase) => [
                    $this->dedupKey($purchase->purchase_date?->toDateString(), (float) $purchase->value) => true,
                ]);

            $imported = 0;
            $skipped = 0;

            foreach ($items as $item) {
                $key = $this->dedupKey($item['purchase_date'], $item['value']);

                if (isset($existing[$key])) {
                    $skipped++;

                    continue;
                }

                FinancialAccount::query()->create([
                    'type' => AccountType::Payable,
                    'description' => $item['description'],
                    'credit_card_id' => $creditCard->uuid,
                    'category_id' => $categoryId,
                    'cost_center_id' => $costCenterId,
                    'value' => $item['value'],
                    'purchase_date' => $item['purchase_date'],
                    'due_date' => $dueDate,
                    'status' => AccountStatus::Open,
                    'is_card_purchase' => true,
                    'is_card_invoice_payable' => false,
                ]);

                $existing[$key] = true;
                $imported++;
            }

            if ($imported === 0) {
                throw new \InvalidArgumentException('Nenhuma compra nova foi encontrada no arquivo para importar.');
            }

            $invoice = $this->creditCards->closeInvoice($creditCard, $referenceMonth, $bankAccountId);

            $this->accounts->settle($invoice->payable, $user, [
                'settled_at' => $paidDate,
                'method' => 'credit_card_invoice',
            ]);

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'total' => (float) $invoice->total_value,
                'invoice_id' => $invoice->uuid,
            ];
        });
    }

    private function dedupKey(?string $date, float $value): string
    {
        return ($date ?? '').'|'.number_format(round($value, 2), 2, '.', '');
    }
}
