<?php

namespace App\Modules\Transfer\Services;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Account\Models\Settlement;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Transfer\Models\Transfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Transfer::query()
            ->with(['fromCostCenter:id,uuid,name', 'toCostCenter:id,uuid,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array{from_bank_account_id: string, to_bank_account_id: string, value: numeric, date: string, description?: ?string}  $data
     */
    public function create(array $data): Transfer
    {
        return DB::transaction(function () use ($data): Transfer {
            $transfer = Transfer::query()->create($data);

            $from = $this->bankAccountId($data['from_bank_account_id']);
            $to = $this->bankAccountId($data['to_bank_account_id']);
            $value = round((float) $data['value'], 2);

            $outgoing = FinancialAccount::query()->create([
                'type' => AccountType::Payable,
                'description' => $data['description'] ?? "Transferência para {$to->name}",
                'bank_account_id' => $from->uuid,
                'category_id' => null,
                'value' => $value,
                'due_date' => $data['date'],
                'status' => AccountStatus::Settled,
                'paid_date' => $data['date'],
                'transfer_id' => $transfer->getKey(),
            ]);

            $incoming = FinancialAccount::query()->create([
                'type' => AccountType::Receivable,
                'description' => $data['description'] ?? "Transferência de {$from->name}",
                'bank_account_id' => $to->uuid,
                'category_id' => null,
                'value' => $value,
                'due_date' => $data['date'],
                'status' => AccountStatus::Settled,
                'paid_date' => $data['date'],
                'transfer_id' => $transfer->getKey(),
            ]);

            Settlement::query()->create([
                'account_id' => $outgoing->getKey(),
                'value' => $value,
                'settled_at' => $data['date'],
                'method' => 'transfer',
            ]);

            Settlement::query()->create([
                'account_id' => $incoming->getKey(),
                'value' => $value,
                'settled_at' => $data['date'],
                'method' => 'transfer',
            ]);

            return $transfer;
        });
    }

    public function delete(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $transfer->accounts()->each(fn (FinancialAccount $account) => $account->settlements()->delete());
            $transfer->accounts()->delete();
            $transfer->delete();
        });
    }

    private function bankAccountId(string $uuid): BankAccount
    {
        return BankAccount::query()->where('uuid', $uuid)->firstOrFail();
    }
}
