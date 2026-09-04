<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const CREATED_AT = 'created_at';

    public $timestamps = false;

    protected $fillable = [
        'bank_transaction_id',
        'account_id',
        'user_id',
        'created_at',
        'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->reversed_at === null;
    }
}
