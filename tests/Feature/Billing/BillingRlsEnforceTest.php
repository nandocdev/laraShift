<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Postgres-only isolation proof: raw SQL with no Eloquent scopes.
 * Skipped on SQLite (RLS policies only exist on pgsql).
 */
function rlsOnly(): void
{
    if (DB::getDriverName() !== 'pgsql') {
        test()->markTestSkipped('Requires PostgreSQL with RLS.');
    }
}

function rlsTenants(): array
{
    $make = fn (string $slug) => Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => 'RLS Test',
        'email' => $slug.'@test.com',
        'status' => 'active',
        'billing_gateway' => 'clave',
    ]);

    return [$make('rls-a'), $make('rls-b')];
}

it('fails closed with no tenant context at the SQL level', function () {
    rlsOnly();
    [$tenantA, $tenantB] = rlsTenants();

    seedTenantBilling($tenantA);
    seedTenantBilling($tenantB);

    // No SET LOCAL: the policy references an unset setting, so Postgres
    // raises instead of leaking rows. Fail-closed, never fail-open.
    expect(fn () => DB::transaction(fn () => DB::select('SELECT id FROM payments')))
        ->toThrow(QueryException::class);
})->group('RLSEnforce');

it('enforces tenant isolation with SET LOCAL per session', function () {
    rlsOnly();
    [$tenantA, $tenantB] = rlsTenants();

    seedTenantBilling($tenantA);
    seedTenantBilling($tenantB);

    $visibleToA = DB::transaction(function () use ($tenantA) {
        DB::statement('SET LOCAL app.tenant_id = ?', [$tenantA->id]);

        return DB::select('SELECT tenant_id FROM payments');
    });

    expect($visibleToA)->toHaveCount(1)
        ->and($visibleToA[0]->tenant_id)->toBe($tenantA->id);

    $visibleToB = DB::transaction(function () use ($tenantB) {
        DB::statement('SET LOCAL app.tenant_id = ?', [$tenantB->id]);

        return DB::select('SELECT tenant_id FROM payments');
    });

    expect($visibleToB)->toHaveCount(1)
        ->and($visibleToB[0]->tenant_id)->toBe($tenantB->id);
})->group('RLSEnforce');

it('rejects cross-tenant writes at the database level', function () {
    rlsOnly();
    [$tenantA, $tenantB] = rlsTenants();

    seedTenantBilling($tenantA);

    expect(fn () => DB::transaction(function () use ($tenantA, $tenantB) {
        DB::statement('SET LOCAL app.tenant_id = ?', [$tenantA->id]);

        DB::table('payments')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantB->id,
            'slug' => 'pay_RLS-X-1',
            'display_id' => 'DSP-RLS-X-1',
            'amount_cents' => 100,
            'currency' => 'USD',
            'status' => PaymentStatus::Pending,
            'gateway' => 'clave',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }))->toThrow(QueryException::class);
})->group('RLSEnforce');
