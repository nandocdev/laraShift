<?php

declare(strict_types=1);

use App\Modules\Platform\Tenancy\Interface\Http\Middleware\ApplyTenantRateLimits;
use App\Modules\Platform\Tenancy\Interface\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
*/
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantIsActive::class,
    ApplyTenantRateLimits::class,
    \App\Modules\Central\Support\Http\Middleware\AuditImpersonationActions::class,
])->group(function () {
    
    // Landings & Builders
    Route::get('/', \App\Modules\Tenant\Experience\Interface\Http\Controllers\ServeTenantLandingController::class)->name('tenant.home');

    // Access Web & Auth (Guest & Common routes)
    require base_path('app/Modules/Tenant/Access/Interface/Routes/web.php');

    // Support & Impersonation
    require base_path('app/Modules/Central/Support/Interface/Routes/tenant.php');

    // Authenticated Tenant Group
    Route::middleware([
        'auth', 
        \App\Modules\Tenant\Access\Interface\Http\Middleware\EnforceTenantMfa::class,
        \App\Modules\Tenant\Access\Interface\Http\Middleware\EnsureUserIsActive::class,
        \App\Modules\Tenant\Access\Interface\Http\Middleware\EnsureUserBelongsToTenant::class
    ])->group(function () {
        
        // Tenant Access (Authenticated routes like dashboard, team, roles, etc.)
        require base_path('app/Modules/Tenant/Access/Interface/Routes/web_auth.php');

        // Experience
        require base_path('app/Modules/Tenant/Experience/Interface/Routes/web.php');

        // Integrations
        require base_path('app/Modules/Tenant/Integrations/Interface/Routes/web.php');

        // Audit Logs
        require base_path('app/Modules/Tenant/Audit/Interface/Routes/web.php');
        
        // Central Billing SaaS
        require base_path('app/Modules/Central/Billing/Interface/Routes/tenant.php');
    });
});
