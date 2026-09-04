<?php

namespace App\Modules\CreditCard\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Services\AllocationService;
use App\Modules\Account\Models\Settlement;
use App\Modules\CreditCard\Enums\CreditCardInvoiceStatus;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditCardService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return CreditCard::query()
            ->with('bankAccount:id,uuid,name')
            ->when(filled($search), fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CreditCard
    {
        return CreditCard::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CreditCard $creditCard, array $data): CreditCard
    {
        $creditCard->fill($data);
        $creditCard->save();

        return $creditCard->refresh();
    }

    public function delete(CreditCard $creditCard): void
    {
        $creditCard->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPurchase(CreditCard $creditCard, array $data): FinancialAccount
    {
        return DB::transaction(function () use ($creditCard, $data): FinancialAccount {
            $account = FinancialAccount::query()->create([
                ...$data,
                'type' => AccountType::Payable,
                'credit_card_id' => $creditCard->uuid,
                'bank_account_id' => null,
                'is_card_purchase' => true,
                'is_card_invoice_payable' => false,
                'status' => AccountStatus::Open,
            ]);

            if (! empty($data['allocations'])) {
                app(AllocationService::class)->sync($account, $data['allocations']);
            }

            return $account->load(['allocations.costCenter', 'allocations.company', 'company', 'costCenter']);
        });
    }

    public function closeInvoice(CreditCard $creditCard, string $referenceMonth): CreditCardInvoice
    {
        return DB::transaction(function () use ($creditCard, $referenceMonth): CreditCardInvoice {
            $existing = CreditCardInvoice::query()
                ->where('credit_card_id', $creditCard->uuid)
                ->where('reference_month', $referenceMonth)
                ->first();

            if ($existing !== null) {
                throw new InvalidArgumentException('Já existe fatura para este período.');
            }

            [$closingDate, $dueDate] = $this->resolveInvoiceDates($creditCard, $referenceMonth);

            $purchases = FinancialAccount::query()
                ->where('credit_card_id', $creditCard->uuid)
                ->where('is_card_purchase', true)
                ->whereNull('credit_card_invoice_id')
                ->whereDate('due_date', '<=', $closingDate->toDateString())
                ->get();

            if ($purchases->isEmpty()) {
                throw new InvalidArgumentException('Não há compras pendentes para fechar a fatura.');
            }

            $total = round((float) $purchases->sum('value'), 2);

            $payable = FinancialAccount::query()->create([
                'type' => AccountType::Payable,
                'description' => "Fatura {$creditCard->name} — {$referenceMonth}",
                'counterparty' => $creditCard->institution ?? $creditCard->name,
                'bank_account_id' => $creditCard->bank_account_id,
                'credit_card_id' => $creditCard->uuid,
                'value' => $total,
                'due_date' => $dueDate,
                'status' => AccountStatus::Open,
                'is_card_purchase' => false,
                'is_card_invoice_payable' => true,
            ]);

            $invoice = CreditCardInvoice::query()->create([
                'credit_card_id' => $creditCard->uuid,
                'reference_month' => $referenceMonth,
                'closing_date' => $closingDate,
                'due_date' => $dueDate,
                'total_value' => $total,
                'status' => CreditCardInvoiceStatus::Closed,
                'financial_account_id' => $payable->getKey(),
            ]);

            foreach ($purchases as $purchase) {
                $purchase->update([
                    'credit_card_invoice_id' => $invoice->uuid,
                    'status' => AccountStatus::Settled,
                    'paid_date' => $closingDate,
                ]);

                Settlement::query()->create([
                    'tenant_id' => $purchase->tenant_id,
                    'account_id' => $purchase->getKey(),
                    'value' => $purchase->value,
                    'settled_at' => $closingDate,
                    'method' => 'credit_card_invoice',
                ]);
            }

            return $invoice->load(['payable', 'purchases', 'creditCard']);
        });
    }

    public function markInvoicePaid(CreditCardInvoice $invoice): CreditCardInvoice
    {
        if ($invoice->status === CreditCardInvoiceStatus::Paid) {
            return $invoice;
        }

        $invoice->update(['status' => CreditCardInvoiceStatus::Paid]);

        return $invoice->refresh();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveInvoiceDates(CreditCard $creditCard, string $referenceMonth): array
    {
        $month = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $closingDay = min((int) ($creditCard->closing_day ?? 1), $month->daysInMonth);
        $dueDay = min((int) ($creditCard->due_day ?? $closingDay), $month->copy()->addMonth()->daysInMonth);

        $closingDate = $month->copy()->day($closingDay);
        $dueDate = $month->copy()->addMonth()->day($dueDay);

        return [$closingDate, $dueDate];
    }
}
