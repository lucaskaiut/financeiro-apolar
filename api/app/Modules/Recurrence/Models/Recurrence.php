<?php

namespace App\Modules\Recurrence\Models;

use App\Modules\Account\Models\FinancialAccount;
use App\Modules\Category\Models\Category;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Recurrence\Enums\RecurrenceFrequency;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recurrence extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'type',
        'description',
        'counterparty',
        'cost_center_id',
        'category_id',
        'subcategory_id',
        'value',
        'frequency',
        'start_date',
        'end_date',
        'max_occurrences',
        'day_of_month',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id', 'uuid');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'uuid');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id', 'uuid');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(FinancialAccount::class, 'recurrence_id');
    }
}
