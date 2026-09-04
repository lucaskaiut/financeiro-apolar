<?php

namespace App\Modules\Account\Models;

use App\Modules\Category\Models\Category;
use App\Modules\Company\Models\Company;
use App\Modules\CostCenter\Models\CostCenter;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAllocation extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $fillable = [
        'account_id',
        'cost_center_id',
        'company_id',
        'category_id',
        'subcategory_id',
        'value',
        'percentage',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'percentage' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id', 'uuid');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id', 'uuid');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id', 'uuid');
    }
}
