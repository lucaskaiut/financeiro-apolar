<?php

namespace App\Modules\CreditCard\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCardInvoiceImportDraft extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'user_id',
        'credit_card_id',
        'reference_month',
        'paid_date',
        'bank_account_id',
        'category_id',
        'cost_center_id',
        'due_date',
        'step',
        'source_filename',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'paid_date' => 'date',
            'due_date' => 'date',
            'step' => 'integer',
            'items' => 'array',
        ];
    }

    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class, 'credit_card_id', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
