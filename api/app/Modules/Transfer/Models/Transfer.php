<?php

namespace App\Modules\Transfer\Models;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'from_bank_account_id',
        'to_bank_account_id',
        'value',
        'date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'date' => 'date',
        ];
    }

    public function fromCostCenter(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id', 'uuid');
    }

    public function toCostCenter(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id', 'uuid');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'transfer_id');
    }
}
