<?php

namespace App\Modules\Category\Models;

use App\Modules\Category\Enums\CategoryType;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'type',
        'color',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'uuid');
    }

    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }
}
