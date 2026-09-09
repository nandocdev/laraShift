<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Domain\Models\Invoice;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Platform\Support\CrossTenantLeakTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Note: the CrossTenantLeakTest trait setup runs automatically via
// Laravel's setUpTraits() (setUp + trait basename). Do not call it manually.
// On sqlite these tests skip; they run in the pgsql RLS CI job.
uses(CrossTenantLeakTest::class, RefreshDatabase::class);

it('prevents cross-tenant leakage of payments (RLS)', function () {
    $this->assertTenantBSeesNoDataFromA(
        fn ($tenantA) => Payment::where('tenant_id', $tenantA->id)
    );
});

it('prevents cross-tenant leakage of invoices (RLS)', function () {
    $this->assertTenantBSeesNoDataFromA(
        fn ($tenantA) => Invoice::where('tenant_id', $tenantA->id)
    );
});

it('resets tenant context between units of work', function () {
    $this->assertNoLingeringTenantContext();
});
