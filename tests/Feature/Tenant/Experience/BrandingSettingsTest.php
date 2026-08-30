<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Experience\Domain\Models\TenantSetting;
use App\Modules\Tenant\Experience\Interface\Livewire\BrandingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'test-'.substr($id, 0, 8),
        'name' => 'Test Tenant',
        'email' => 'test-'.substr($id, 0, 8).'@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
});

it('renders branding settings and saves valid data', function () {
    $user = User::create([
        'tenant_id' => tenant('id'),
        'name' => 'Admin User',
        'email' => 'admin@test.com',
        'password' => 'password',
    ]);
    $user->assignRole('admin');

    $this->actingAs($user, 'web');

    Livewire::test(BrandingSettings::class)
        ->assertSet('name', 'Test Tenant')
        ->set('name', 'Updated Brand Name')
        ->set('theme_preset', 'startup')
        ->assertSet('primary_color', '#10b981')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $setting = TenantSetting::where('tenant_id', tenant('id'))->first();
    expect($setting->name)->toBe('Updated Brand Name');
    expect($setting->primary_color)->toBe('#10b981');
    expect(tenant()->fresh()->name)->toBe('Updated Brand Name');
});

it('validates theme preset strictly', function () {
    $user = User::create([
        'tenant_id' => tenant('id'),
        'name' => 'Admin User 2',
        'email' => 'admin2@test.com',
        'password' => 'password',
    ]);
    $user->assignRole('admin');

    $this->actingAs($user, 'web');

    Livewire::test(BrandingSettings::class)
        ->set('theme_preset', 'invalid-preset-hacked')
        ->call('save')
        ->assertHasErrors(['theme_preset']);
});
