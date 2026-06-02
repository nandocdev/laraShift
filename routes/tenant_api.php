<?php

declare(strict_types=1);

use App\Modules\Tenant\Identity\Http\Middleware\AuthenticateApiKey;
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
    Route::get('/me', function () {
        return response()->json([
            'tenant' => tenant('name'),
            'api_key' => request()->attributes->get('api_key')->name,
            'scopes' => request()->attributes->get('api_key')->scopes,
        ]);
    });
});
