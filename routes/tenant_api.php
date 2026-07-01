<?php

declare(strict_types=1);

use App\Modules\Tenant\Identity\Http\Controllers\Api\IdentityApiController;
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
    // Info
    Route::get('/me', function () {
        return response()->json([
            'tenant' => tenant('name'),
            'api_key' => request()->attributes->get('api_key')->name,
            'scopes' => request()->attributes->get('api_key')->scopes,
        ]);
    });

    // Team & Members
    Route::get('/team/members', [IdentityApiController::class, 'listMembers']);
    Route::patch('/team/members/{id}', [IdentityApiController::class, 'updateMemberRole']);
    Route::delete('/team/members/{id}', [IdentityApiController::class, 'revokeMember']);

    // Invitations
    Route::get('/team/invitations', [IdentityApiController::class, 'listInvitations']);
    Route::post('/team/invitations', [IdentityApiController::class, 'inviteMember']);
    Route::delete('/team/invitations/{id}', [IdentityApiController::class, 'cancelInvitation']);

    // Roles
    Route::get('/settings/roles', [IdentityApiController::class, 'listRoles']);
    Route::post('/settings/roles', [IdentityApiController::class, 'createRole']);
    // Edit Permissions is handled via role creation/replacement in this simple API
    Route::delete('/settings/roles/{id}', [IdentityApiController::class, 'deleteRole']);
});
