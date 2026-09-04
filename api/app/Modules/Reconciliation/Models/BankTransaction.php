<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'bank_account_id',
        'date',
        'value',
        'type',
        'description',
        'transaction_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'uuid');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class)->latestOfMany();
    }
}
