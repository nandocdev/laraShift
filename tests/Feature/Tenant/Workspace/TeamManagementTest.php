<?php

declare(strict_types=1);

use App\Modules\Central\Catalog\Domain\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Workspace\Interface\Livewire\TeamManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
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
});

test('renders the team management page', function () {
    $user = User::factory()->create(['tenant_id' => tenant('id')]);

    $this->actingAs($user);

    Livewire::test(TeamManagement::class)
        ->assertSuccessful();
});

test('displays current team members', function () {
    $admin = User::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Admin User',
    ]);

    User::factory()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Member One',
    ]);

    $this->actingAs($admin);

    Livewire::test(TeamManagement::class)
        ->assertSee('Admin User')
        ->assertSee('Member One');
});
