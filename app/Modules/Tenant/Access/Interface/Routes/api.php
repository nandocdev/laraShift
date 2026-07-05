<?php

declare(strict_types=1);

use App\Modules\Tenant\Access\Interface\Http\Controllers\Api\IdentityApiController;
use Illuminate\Support\Facades\Route;

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
Route::delete('/settings/roles/{id}', [IdentityApiController::class, 'deleteRole']);
