<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Actions\GenerateApiKeyAction;
use App\Modules\Tenant\Identity\Http\Middleware\AuthenticateApiKey;
use App\Modules\Tenant\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

uses(RefreshDatabase::class);

it('authenticates a request via bearer token and api key', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'api-auth',
        'name' => 'API Auth Test',
        'email' => 'api-auth@test.com',
        'plan_id' => 'free',
    ]);
    $tenant->domains()->create(['domain' => 'api-auth.larashift.test']);

    $action = app(GenerateApiKeyAction::class);

    // We must initialize tenancy to generate the key in the right context
    tenancy()->initialize($tenant);
    $result = $action->execute('Integration Key', ['identity:read']);
    $plainKey = $result['key'];
    tenancy()->end();

    // 1. Unauthorized request (no token)
    $this->getJson('http://api-auth.larashift.test/api/me')
        ->assertStatus(401);

    // 2. Authorized request
    $this->withToken($plainKey)
        ->getJson('http://api-auth.larashift.test/api/me')
        ->assertStatus(200)
        ->assertJson([
            'tenant' => 'API Auth Test',
            'api_key' => 'Integration Key',
        ]);

    // 3. Verify last_used_at update
    tenancy()->initialize($tenant);
    expect($result['model']->fresh()->last_used_at)->not->toBeNull();
});

it('denies access if scope is missing', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'scope-test',
        'name' => 'Scope Test',
        'email' => 'scope@test.com',
    ]);
    $tenant->domains()->create(['domain' => 'scope.larashift.test']);

    tenancy()->initialize($tenant);
    $result = app(GenerateApiKeyAction::class)->execute('Limited Key', ['identity:read']);
    $plainKey = $result['key'];
    tenancy()->end();

    // Mock a route requiring a specific scope
    Route::middleware([AuthenticateApiKey::class.':orders:write'])
        ->get('/api/protected', function () {
            return 'ok';
        });

    $this->withToken($plainKey)
        ->getJson('http://scope.larashift.test/api/protected')
        ->assertStatus(403);
});

it('maps api key scopes to laravel gates', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'gate-test',
        'name' => 'Gate Test',
        'email' => 'gate@test.com',
    ]);
    $domain = 'gate.'.config('tenancy.central_domain');
    $tenant->domains()->create(['domain' => $domain]);

    tenancy()->initialize($tenant);
    $user = User::create([
        'name' => 'Creator',
        'email' => 'creator@test.com',
        'password' => 'password',
    ]);

    $result = app(GenerateApiKeyAction::class)->execute('Gate Key', ['orders:read'], $user);
    $plainKey = $result['key'];
    tenancy()->end();

    // Mock a route that uses standard Gate check
    Route::middleware([
        InitializeTenancyByDomain::class,
        AuthenticateApiKey::class,
    ])->get('/api/orders', function () {
        return auth()->user()->can('orders:read') ? 'allowed' : 'denied';
    });

    $this->withToken($plainKey)
        ->getJson("http://{$domain}/api/orders")
        ->assertStatus(200)
        ->assertSee('allowed');
});
