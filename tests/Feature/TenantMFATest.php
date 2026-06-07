<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Http\Middleware\EnforceTenantMfa;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('redirects to 2fa setup when mfa is mandatory and user has not enrolled', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'mfa-test',
        'name' => 'MFA Test',
        'email' => 'mfa@test.com',
    ]);

    tenancy()->initialize($tenant);

    // Set MFA as mandatory
    TenantSetting::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'mfa_required' => true,
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'John Doe',
        'email' => 'john@test.com',
        'password' => 'password',
        'mfa_enabled' => false,
    ]);

    $this->actingAs($user);

    // Mock a protected route
    Route::get('/test-protected', function () {
        return 'success';
    })->middleware(['web', EnforceTenantMfa::class]);

    $response = $this->get('/test-protected');

    $response->assertRedirect(route('tenant.settings.security.2fa'));
});

it('allows access when mfa is mandatory and user is enrolled', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'mfa-ok',
        'name' => 'MFA OK',
        'email' => 'ok@test.com',
    ]);

    tenancy()->initialize($tenant);

    TenantSetting::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $tenant->id,
        'mfa_required' => true,
    ]);

    $user = User::create([
        'tenant_id' => $tenant->id,
        'name' => 'John Enrolled',
        'email' => 'john@enrolled.com',
        'password' => 'password',
        'mfa_enabled' => true,
    ]);

    $this->actingAs($user);

    Route::get('/test-ok', function () {
        return 'success';
    })->middleware(['web', EnforceTenantMfa::class]);

    $response = $this->get('/test-ok');

    $response->assertStatus(200);
    $response->assertSee('success');
});
