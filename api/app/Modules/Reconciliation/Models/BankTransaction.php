<?php

namespace App\Modules\Reconciliation\Models;

use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankTransaction extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'cost_center_id',
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

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id', 'uuid');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class)->latestOfMany();
    }
}
