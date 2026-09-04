<?php

namespace App\Modules\Transfer\Models;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\CostCenter\Models\CostCenter;
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
        'from_cost_center_id',
        'to_cost_center_id',
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
        return $this->belongsTo(CostCenter::class, 'from_cost_center_id', 'uuid');
    }

    public function toCostCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'to_cost_center_id', 'uuid');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'transfer_id');
    }
}
