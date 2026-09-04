<?php

namespace App\Modules\Tenant\Models;

use App\Modules\ACL\Models\Role;
use App\Modules\Shared\Models\Concerns\HasUuid;
use App\Modules\User\Models\User;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasUuid;

    protected $fillable = [
        'parent_id',
        'name',
        'document',
        'email',
        'phone',
        'domain',
        'ai_enabled',
        'ai_endpoint',
        'ai_api_key',
        'ai_model',
        'ai_temperature',
        'ai_max_tokens',
        'ai_system_prompt',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'ai_temperature' => 'decimal:2',
            'ai_max_tokens' => 'integer',
        ];
    }

    public function isUmbrella(): bool
    {
        return $this->parent_id === null;
    }

    public function getAiApiKeyAttribute(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAiApiKeyAttribute(?string $value): void
    {
        $this->attributes['ai_api_key'] = filled($value)
            ? Crypt::encryptString($value)
            : null;
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
