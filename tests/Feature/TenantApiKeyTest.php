<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Actions\GenerateApiKeyAction;
use App\Modules\Tenant\Identity\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates a secure api key with specific scopes', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'api-test',
        'name' => 'API Test',
        'email' => 'api@test.com',
    ]);

    tenancy()->initialize($tenant);

    $action = app(GenerateApiKeyAction::class);
    $result = $action->execute(
        name: 'Test Key',
        scopes: ['orders:read', 'orders:write']
    );

    expect($result['key'])->toStartWith('tnt_');
    expect(strlen($result['key']))->toBe(68); // tnt_ + 64 hex chars
    
    $model = $result['model'];
    expect($model->name)->toBe('Test Key');
    expect($model->scopes)->toBe(['orders:read', 'orders:write']);
    expect($model->key_hash)->toBe(hash('sha256', $result['key']));
});

it('revokes an api key immediately', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'revoke-test',
        'name' => 'Revoke Test',
        'email' => 'revoke@test.com',
    ]);

    tenancy()->initialize($tenant);

    $action = app(GenerateApiKeyAction::class);
    $result = $action->execute('Revoke Me', ['identity:read']);
    $apiKey = $result['model'];

    expect($apiKey->isActive())->toBeTrue();

    $apiKey->update(['revoked_at' => now()]);
    
    expect($apiKey->isActive())->toBeFalse();
});
