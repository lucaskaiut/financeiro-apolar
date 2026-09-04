<?php

namespace App\Modules\CostCenter\Models;

use App\Modules\CostCenter\Enums\CostCenterType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

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
            'type' => CostCenterType::class,
            'initial_balance' => 'decimal:2',
        ];
    }
}
