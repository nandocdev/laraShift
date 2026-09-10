<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Access\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $id = (string) Str::uuid();
    $tenant = Tenant::create([
        'id' => $id,
        'slug' => 'test-'.substr($id, 0, 8),
        'name' => 'Test Tenant',
        'email' => 'test-'.substr($id, 0, 8).'@tenant.com',
        'status' => 'active',
    ]);

    $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
    $tenant->domains()->create(['domain' => $tenant->slug.'.'.$centralDomain]);
    tenancy()->initialize($tenant);
});

test('tenant users exist after setup', function () {
    User::factory()->create(['tenant_id' => tenant('id'), 'name' => 'Test User']);

    expect(User::where('tenant_id', tenant('id'))->count())->toBe(1);
});
