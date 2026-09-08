<?php

namespace App\Modules\Account\Models;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Account\Enums\AllocationMode;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Category\Models\Category;
use App\Modules\Company\Models\Company;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\CreditCard\Models\CreditCard;
use App\Modules\CreditCard\Models\CreditCardInvoice;
use App\Modules\Reconciliation\Models\Reconciliation;
use App\Modules\Recurrence\Models\Recurrence;
use App\Modules\Shared\Casts\DateOnlyCast;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\Transfer\Models\Transfer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FinancialAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'description',
        'counterparty',
        'bank_account_id',
        'company_id',
        'cost_center_id',
        'credit_card_id',
        'credit_card_invoice_id',
        'is_card_purchase',
        'is_card_invoice_payable',
        'allocation_mode',
        'category_id',
        'subcategory_id',
        'value',
        'due_date',
        'purchase_date',
        'expected_date',
        'paid_date',
        'observation',
        'status',
        'installment_group_id',
        'installment_number',
        'installment_total',
        'recurrence_id',
        'transfer_id',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'allocation_mode' => AllocationMode::class,
            'value' => 'decimal:2',
            'due_date' => DateOnlyCast::class,
            'purchase_date' => DateOnlyCast::class,
            'expected_date' => DateOnlyCast::class,
            'paid_date' => DateOnlyCast::class,
            'reconciled_at' => 'datetime',
            'is_card_purchase' => 'boolean',
            'is_card_invoice_payable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinancialAccount $account): void {
            if (blank($account->installment_group_id) && ! blank($account->installment_number)) {
                $account->installment_group_id = (string) Str::uuid();
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id', 'uuid');
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id', 'uuid');
    }

    public function creditCardInvoice(): BelongsTo
    {
        return $this->belongsTo(CreditCardInvoice::class, 'credit_card_invoice_id', 'uuid');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'uuid');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id', 'uuid');
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'account_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AccountDocument::class, 'account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class, 'account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(AccountAllocation::class, 'account_id');
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }

    public function isSettled(): bool
    {
        return $this->status === AccountStatus::Settled;
    }

    public function isCardPurchase(): bool
    {
        return (bool) $this->is_card_purchase;
    }

    public function isCardInvoicePayable(): bool
    {
        return (bool) $this->is_card_invoice_payable;
    }

    public function isSplit(): bool
    {
        if ($this->allocation_mode === AllocationMode::Split) {
            return true;
        }

        return $this->relationLoaded('allocations') && $this->allocations->isNotEmpty();
    }

    public function countsForEconomicReports(): bool
    {
        return ! $this->is_card_invoice_payable;
    }

    public function countsForBankBalance(Settlement $settlement): bool
    {
        return ! $this->is_card_invoice_payable;
    }

    /**
     * Contas que entram em provisão, projetado e abertos financeiros.
     * Exclui o payable da fatura (agregador de conciliação).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<FinancialAccount>  $query
     * @return \Illuminate\Database\Eloquent\Builder<FinancialAccount>
     */
    public function scopeCountingFinancially($query)
    {
        return $query->where('is_card_invoice_payable', false);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinancialAccount>  $query
     * @return \Illuminate\Database\Eloquent\Builder<FinancialAccount>
     */
    public function scopeForBankAccount($query, ?string $bankAccountId)
    {
        if ($bankAccountId === null || $bankAccountId === '') {
            return $query;
        }

        return $query->where(function ($inner) use ($bankAccountId): void {
            $inner->where('bank_account_id', $bankAccountId)
                ->orWhere(function ($card) use ($bankAccountId): void {
                    $card->where('is_card_purchase', true)
                        ->whereHas('creditCard', fn ($c) => $c->where('bank_account_id', $bankAccountId));
                });
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinancialAccount>  $query
     * @return \Illuminate\Database\Eloquent\Builder<FinancialAccount>
     */
    public function scopeForCostCenter($query, ?string $costCenterId)
    {
        if ($costCenterId === null || $costCenterId === '') {
            return $query;
        }

        return $query->where(function ($inner) use ($costCenterId): void {
            $inner->where('cost_center_id', $costCenterId)
                ->orWhereHas('allocations', fn ($a) => $a->where('cost_center_id', $costCenterId));
        });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<FinancialAccount>  $query
     * @return \Illuminate\Database\Eloquent\Builder<FinancialAccount>
     */
    public function scopeForCategory($query, ?string $categoryId)
    {
        if ($categoryId === null || $categoryId === '') {
            return $query;
        }

        return $query->where(function ($inner) use ($categoryId): void {
            $inner->where('category_id', $categoryId)
                ->orWhereHas('allocations', fn ($a) => $a->where('category_id', $categoryId));
        });
    }

    public function getSettledAmountAttribute(): float
    {
        if (array_key_exists('settlements_sum_value', $this->attributes)) {
            return round((float) ($this->attributes['settlements_sum_value'] ?? 0), 2);
        }

        if ($this->relationLoaded('settlements')) {
            return round((float) $this->settlements->sum('value'), 2);
        }

        return round((float) $this->settlements()->sum('value'), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return round((float) $this->value - $this->settled_amount, 2);
    }
}
