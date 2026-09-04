<?php

namespace App\Modules\Account\Models;

use Illuminate\Database\Eloquent\Model;

class AccountImport extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'bank_account_id',
        'filename',
        'content',
    ];
}
