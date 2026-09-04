<?php

namespace App\Modules\BankAccount\Models;

use App\Modules\BankAccount\Enums\BankAccountType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'name',
        'bank',
        'agency',
        'account',
        'type',
        'initial_balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => BankAccountType::class,
            'initial_balance' => 'decimal:2',
        ];
    }
}
