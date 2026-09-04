<?php

namespace App\Modules\CreditCard\Models;

use App\Modules\BankAccount\Models\BankAccount;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCard extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'institution',
        'limit',
        'closing_day',
        'due_day',
        'bank_account_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'limit' => 'decimal:2',
            'closing_day' => 'integer',
            'due_day' => 'integer',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'uuid');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CreditCardInvoice::class, 'credit_card_id', 'uuid');
    }
}
