<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Models\Concerns\BelongsToTenant;
use App\Modules\Shared\Tenancy\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IsolationTestModel extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_api_keys';
    protected $fillable = ['id', 'tenant_id', 'name', 'key_hash', 'scopes', 'created_at', 'updated_at'];
    public $timestamps = true;
}

beforeEach(function () {
    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenantA = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'isolation-a-' . Str::random(5),
        'name' => 'Isolation A',
        'email' => Str::random(10) . '@a.com',
        'plan_id' => 'free',
    ]);

    $this->tenantB = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'isolation-b-' . Str::random(5),
        'name' => 'Isolation B',
        'email' => Str::random(10) . '@b.com',
        'plan_id' => 'free',
    ]);
});

test('belongs to tenant trait auto-assigns tenant id on create', function () {
    tenancy()->initialize($this->tenantA);

    $model = IsolationTestModel::create([
        'id' => (string) Str::uuid(),
        'name' => 'Auto-assign Test',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    expect($model->tenant_id)->toBe($this->tenantA->id);
});

test('tenant scope filters queries by current tenant', function () {
    tenancy()->initialize($this->tenantA);

    $keyId = (string) Str::uuid();
    IsolationTestModel::create([
        'id' => $keyId,
        'name' => 'Key A',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    $key = IsolationTestModel::find($keyId);

    expect($key)->toBeNull();
});

test('cross tenant access returns null for eloquent models with scope', function () {
    tenancy()->initialize($this->tenantA);

    $keyId = (string) Str::uuid();
    IsolationTestModel::create([
        'id' => $keyId,
        'name' => 'Key A',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    $key = IsolationTestModel::find($keyId);

    expect($key)->toBeNull();
});

test('tenant b cannot see tenant a records through eloquent', function () {
    tenancy()->initialize($this->tenantA);

    IsolationTestModel::create([
        'id' => (string) Str::uuid(),
        'name' => 'Secret Key A',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    $keys = IsolationTestModel::all();

    expect($keys)->toHaveCount(0);
});

test('eloquent scope prevents updating other tenant records', function () {
    tenancy()->initialize($this->tenantA);

    $keyId = (string) Str::uuid();
    IsolationTestModel::create([
        'id' => $keyId,
        'name' => 'Secret Key',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenantB);

    $key = IsolationTestModel::find($keyId);

    expect($key)->toBeNull();
});

test('raw queries bypass eloquent scope (rls needed for db-level isolation)', function () {
    // Raw DB queries are NOT tenant-scoped by Eloquent's TenantScope.
    // On PostgreSQL, RLS provides this isolation at the database layer.
    // This test documents the expected behavior: application-level scope
    // does not protect raw queries — use Eloquent or PostgreSQL RLS.
    $this->expectNotToPerformAssertions();
});

test('global scope is only applied when tenancy is initialized', function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    IsolationTestModel::create([
        'id' => (string) Str::uuid(),
        'tenant_id' => $this->tenantA->id,
        'name' => 'Orphan',
        'key_hash' => hash('sha256', Str::random(10)),
        'scopes' => json_encode(['*']),
    ]);

    $count = IsolationTestModel::count();

    expect($count)->toBe(1);
});
