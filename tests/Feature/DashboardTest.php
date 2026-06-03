<?php

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'test-dashboard',
        'name' => 'Test Dashboard',
        'email' => 'admin@test.com',
        'plan_id' => 'free',
    ]);
    $this->domain = 'test-dashboard.' . config('tenancy.central_domain');
    $this->tenant->domains()->create(['domain' => $this->domain]);
    config(['session.domain' => '.' . config('tenancy.central_domain')]);
    Illuminate\Support\Facades\URL::forceRootUrl('http://' . $this->domain);
    $this->withHeaders(['Host' => $this->domain]);
});

test('guests are redirected to the login page', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    tenancy()->initialize($this->tenant);
    $user = User::forceCreate([
        'tenant_id' => $this->tenant->id,
        'name' => 'Test User',
        'email' => 'test@test.com',
        'password' => 'password',
        'is_active' => true,
    ]);
    
    $this->actingAs($user);

    $response = $this->get('/dashboard');
    $response->assertOk();
});