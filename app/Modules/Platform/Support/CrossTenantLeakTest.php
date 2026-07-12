<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trait reutilizable para tests de aislamiento entre tenants.
 *
 * Uso en Pest:
 * ```php
 * uses(CrossTenantLeakTest::class);
 * uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);
 *
 * beforeEach(function () {
 *     $this->setUpCrossTenantLeakTest();
 * });
 *
 * it('prevents cross-tenant data leakage', function () {
 *     $this->assertTenantBSeesNoDataFromA(fn ($tenantA) =>
 *         \App\Modules\Tenant\Access\Domain\Models\ApiKey::where('tenant_id', $tenantA->id)
 *     );
 * });
 *
 * it('resets tenant context between units of work', function () {
 *     $this->assertNoLingeringTenantContext();
 * });
 * ```
 */
trait CrossTenantLeakTest
{
    public Tenant $tenantA;

    public Tenant $tenantB;

    /**
     * Creates two tenants (A and B) with sample data.
     * Call this in your beforeEach().
     */
    public function setUpCrossTenantLeakTest(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Cross-tenant RLS tests require PostgreSQL.');
        }

        $userStatus = DB::select('SELECT usename, usesuper FROM pg_user WHERE usename = CURRENT_USER')[0];

        if ($userStatus->usesuper) {
            $this->markTestSkipped('Current DB user is a SUPERUSER. PostgreSQL bypasses RLS for superusers.');
        }

        $this->tenantA = Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => 'test-tenant-a-'.Str::random(5),
            'name' => 'Test Tenant A',
            'email' => Str::random(8).'@a.test',
            'plan_id' => 'free',
        ]);

        $this->tenantB = Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => 'test-tenant-b-'.Str::random(5),
            'name' => 'Test Tenant B',
            'email' => Str::random(8).'@b.test',
            'plan_id' => 'free',
        ]);
    }

    /**
     * Verifies that Tenant B cannot read or write data belonging to Tenant A.
     *
     * @param  callable(Tenant): Builder  $queryBuilder
     *                                                   Receives $tenantA, must return a Builder scoped to that tenant's data.
     */
    public function assertTenantBSeesNoDataFromA(callable $queryBuilder): void
    {
        // 1. Insert sample data as Tenant A
        $this->simulateTenantContext($this->tenantA->id);

        $query = $queryBuilder($this->tenantA);
        $modelClass = $query->getModel()::class;
        $sampleData = $modelClass::factory()->make()->toArray();
        $sampleData['tenant_id'] = $this->tenantA->id;
        $sampleData['id'] ??= (string) Str::uuid();

        $modelClass::create($sampleData);
        $sampleId = $sampleData['id'];

        // 2. Switch to Tenant B and try to READ Tenant A's data
        $this->simulateTenantContext($this->tenantB->id);

        $stolenData = $modelClass::where('id', $sampleId)->get();
        expect($stolenData)->toHaveCount(0);

        // 3. Try to WRITE data claiming to be Tenant A from Tenant B's session
        $maliciousData = $modelClass::factory()->make()->toArray();
        $maliciousData['tenant_id'] = $this->tenantA->id;
        $maliciousData['id'] ??= (string) Str::uuid();

        try {
            $modelClass::create($maliciousData);
            $this->fail('RLS should have prevented inserting data for another tenant.');
        } catch (QueryException $e) {
            expect($e->getMessage())->toContain('row-level security policy');
        }
    }

    /**
     * Verifies that after simulating a tenant context, the context is properly
     * discarded and does not linger (simulates a new unit of work).
     */
    public function assertNoLingeringTenantContext(): void
    {
        // Simulate a unit of work for Tenant A
        $this->simulateTenantContext($this->tenantA->id);

        $valueA = DB::select("SELECT current_setting('app.tenant_id', true) AS tenant_id")[0]->tenant_id;
        expect($valueA)->toBe($this->tenantA->id);

        // Simulate transaction end (commit) which resets SET LOCAL
        DB::statement('COMMIT');
        DB::statement('BEGIN');

        // After commit, SET LOCAL should be reset
        $valueAfter = DB::select("SELECT current_setting('app.tenant_id', true) AS tenant_id")[0]->tenant_id;

        // Should be empty or different — SET LOCAL resets on COMMIT
        expect($valueAfter)->not->toBe($this->tenantA->id);
    }

    /**
     * Simulates a tenant context by setting SET LOCAL within a transaction.
     */
    public function simulateTenantContext(string $tenantId): void
    {
        DB::statement('BEGIN');
        DB::statement('SET LOCAL app.tenant_id = ?', [$tenantId]);
    }
}
