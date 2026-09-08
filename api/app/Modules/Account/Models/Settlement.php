<?php

namespace App\Modules\Account\Models;

use App\Modules\Shared\Casts\DateOnlyCast;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'account_id',
        'value',
        'settled_at',
        'method',
        'user_id',
        'reconciliation_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'settled_at' => DateOnlyCast::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Baixas que entram em totais financeiros (caixa, saldo, relatórios).
     * Conta as compras do cartão; ignora o payable da fatura (só centraliza conciliação).
     *
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     */
    public function scopeCountingFinancially(Builder $query): Builder
    {
        return $query->whereHas('account', function (Builder $accountQuery): void {
            $accountQuery->where('is_card_invoice_payable', false);
        });
    }

    /**
     * Filtra por conta bancária, atribuindo compras do cartão ao banco vinculado.
     *
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     */
    public function scopeForBankAccount(Builder $query, ?string $bankAccountId): Builder
    {
        if ($bankAccountId === null || $bankAccountId === '') {
            return $query;
        }

        return $query->whereHas('account', function (Builder $accountQuery) use ($bankAccountId): void {
            $accountQuery->where(function (Builder $inner) use ($bankAccountId): void {
                $inner->where('bank_account_id', $bankAccountId)
                    ->orWhere(function (Builder $card) use ($bankAccountId): void {
                        $card->where('is_card_purchase', true)
                            ->whereHas('creditCard', fn (Builder $c) => $c->where('bank_account_id', $bankAccountId));
                    });
            });
        });
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     */
    public function scopeForCostCenter(Builder $query, ?string $costCenterId): Builder
    {
        if ($costCenterId === null || $costCenterId === '') {
            return $query;
        }

        return $query->whereHas('account', function (Builder $accountQuery) use ($costCenterId): void {
            $accountQuery->forCostCenter($costCenterId);
        });
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     */
    public function scopeForCategory(Builder $query, ?string $categoryId): Builder
    {
        if ($categoryId === null || $categoryId === '') {
            return $query;
        }

        return $query->whereHas('account', function (Builder $accountQuery) use ($categoryId): void {
            $accountQuery->forCategory($categoryId);
        });
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     *
     * @deprecated Use countingFinancially()
     */
    public function scopeAffectingBankBalance(Builder $query): Builder
    {
        return $query->countingFinancially();
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     *
     * @deprecated Use countingFinancially()
     */
    public function scopeForEconomicReports(Builder $query): Builder
    {
        return $query->countingFinancially();
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     *
     * @deprecated Use forBankAccount()
     */
    public function scopeForBankAccountBalance(Builder $query, ?string $bankAccountId): Builder
    {
        return $query->forBankAccount($bankAccountId);
    }

    /**
     * @param  Builder<Settlement>  $query
     * @return Builder<Settlement>
     *
     * @deprecated Use forBankAccount()
     */
    public function scopeForBankAccountEconomic(Builder $query, ?string $bankAccountId): Builder
    {
        return $query->forBankAccount($bankAccountId);
    }
}
