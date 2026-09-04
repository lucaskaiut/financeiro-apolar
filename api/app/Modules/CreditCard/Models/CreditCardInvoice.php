<?php

namespace App\Modules\CreditCard\Models;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\CreditCard\Enums\CreditCardInvoiceStatus;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCardInvoice extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'credit_card_id',
        'reference_month',
        'closing_date',
        'due_date',
        'total_value',
        'status',
        'financial_account_id',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'closing_date' => 'date',
            'due_date' => 'date',
            'status' => CreditCardInvoiceStatus::class,
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id', 'uuid');
    }

    public function payable(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'credit_card_invoice_id', 'uuid');
    }
}
