<?php

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| RLS Isolation Test
|--------------------------------------------------------------------------
|
| This test validates that PostgreSQL Row-Level Security (RLS) is correctly
| configured to isolate tenant data at the database level.
|
| IMPORTANT: RLS is bypassed by SUPERUSERS. If this test runs as a superuser
| (common in local dev/Docker), the isolation check will be skipped.
|
*/

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('RLS tests require PostgreSQL.');
    }
});

test('database prevents access to other tenant data at rls layer', function () {
    $userStatus = DB::select('SELECT usename, usesuper FROM pg_user WHERE usename = CURRENT_USER')[0];

    if ($userStatus->usesuper) {
        $this->markTestSkipped("Current DB user ({$userStatus->usename}) is a SUPERUSER. PostgreSQL bypasses RLS for superusers, so isolation cannot be tested in this environment.");
    }

    // 1. Setup two tenants
    $tenantA = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'tenant-a-'.Str::random(5),
        'name' => 'Tenant A',
        'email' => Str::random(10).'@test.com',
        'plan_id' => 'free',
    ]);

    $tenantB = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'tenant-b-'.Str::random(5),
        'name' => 'Tenant B',
        'email' => Str::random(10).'@test.com',
        'plan_id' => 'free',
    ]);

    // 2. Set session to Tenant A for insertion
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantA->id]);

    $apiKeyId = (string) Str::uuid();
    DB::table('tenant_api_keys')->insert([
        'id' => $apiKeyId,
        'tenant_id' => $tenantA->id,
        'name' => 'Secret Key A',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 3. Change session to Tenant B and try to read Tenant A's data
    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantB->id]);

    $visibleKeys = DB::table('tenant_api_keys')->where('id', $apiKeyId)->get();

    expect($visibleKeys)->toHaveCount(0);

    // 4. Try to insert data for Tenant A from Tenant B session (Should fail RLS CHECK)
    try {
        DB::table('tenant_api_keys')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantA->id,
            'name' => 'Malicious Key',
            'key_hash' => hash('sha256', Str::random(10)),
            'scopes' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->fail('RLS should have prevented inserting data for another tenant.');
    } catch (QueryException $e) {
        expect($e->getMessage())->toContain('new row violates row-level security policy');
    }
});

test('empty tenant id returns no results when rls is forced', function () {
    $userStatus = DB::select('SELECT usename, usesuper FROM pg_user WHERE usename = CURRENT_USER')[0];
    if ($userStatus->usesuper) {
        $this->markTestSkipped('Skipped: Superuser bypasses RLS.');
    }

    $tenantA = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'tenant-a-'.Str::random(5),
        'name' => 'Tenant A',
        'email' => Str::random(10).'@test.com',
        'plan_id' => 'free',
    ]);

    DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantA->id]);

    DB::table('tenant_api_keys')->insert([
        'id' => (string) Str::uuid(),
        'tenant_id' => $tenantA->id,
        'name' => 'Secret Key A',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Ensure RLS is active but app.tenant_id is not set
    DB::statement("SELECT set_config('app.tenant_id', '', false)");

    $count = DB::table('tenant_api_keys')->count();
    expect($count)->toBe(0);
});
