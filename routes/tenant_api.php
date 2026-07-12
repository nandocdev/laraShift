<?php

declare(strict_types=1);

use App\Modules\Tenant\Access\Interface\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
*/
Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    AuthenticateApiKey::class,
])->group(function () {

    // Access / Identity API endpoints
    require base_path('app/Modules/Tenant/Access/Interface/Routes/api.php');
});
