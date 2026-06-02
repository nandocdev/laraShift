<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Actions\GenerateApiKeyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

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
    Route::middleware([\App\Modules\Tenant\Identity\Http\Middleware\AuthenticateApiKey::class . ':orders:write'])
        ->get('/api/protected', function () { return 'ok'; });

    $this->withToken($plainKey)
        ->getJson('http://scope.larashift.test/api/protected')
        ->assertStatus(403);
});
