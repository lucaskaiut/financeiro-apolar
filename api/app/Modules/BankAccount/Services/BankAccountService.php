<?php

namespace App\Modules\BankAccount\Services;

use App\Modules\BankAccount\Models\BankAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BankAccountService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return BankAccount::query()
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('bank', 'like', "%{$search}%")
                        ->orWhere('agency', 'like', "%{$search}%")
                        ->orWhere('account', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @return list<BankAccount>
     */
    public function all(): array
    {
        return BankAccount::query()
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param  array{name: string, bank?: ?string, agency?: ?string, account?: ?string, type: string, initial_balance?: numeric|string, status?: string}  $data
     */
    public function create(array $data): BankAccount
    {
        return BankAccount::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BankAccount $bankAccount, array $data): BankAccount
    {
        $bankAccount->fill($data);
        $bankAccount->save();

        return $bankAccount->refresh();
    }

    public function delete(BankAccount $bankAccount): void
    {
        $bankAccount->delete();
    }
}
