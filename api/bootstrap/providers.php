<?php

use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantServiceProvider::class,
];
