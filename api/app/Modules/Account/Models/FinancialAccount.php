<?php

namespace App\Modules\Account\Models;

use App\Modules\Account\Enums\AccountStatus;
use App\Modules\Account\Enums\AccountType;
use App\Modules\Category\Models\Category;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Reconciliation\Models\Reconciliation;
use App\Modules\Recurrence\Models\Recurrence;
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
        'cost_center_id',
        'category_id',
        'subcategory_id',
        'value',
        'due_date',
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
            'value' => 'decimal:2',
            'due_date' => 'date',
            'expected_date' => 'date',
            'paid_date' => 'date',
            'reconciled_at' => 'datetime',
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

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id', 'uuid');
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

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }

    public function isSettled(): bool
    {
        return $this->status === AccountStatus::Settled;
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
