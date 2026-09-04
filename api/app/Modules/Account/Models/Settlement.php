<?php

namespace App\Modules\Account\Models;

use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use App\Modules\User\Models\User;
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
            'settled_at' => 'date',
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
}
