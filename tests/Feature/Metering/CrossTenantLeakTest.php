<?php

declare(strict_types=1);

use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
use App\Modules\Platform\Support\CrossTenantLeakTest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(CrossTenantLeakTest::class, RefreshDatabase::class);

beforeEach(function () {
    $this->setUpCrossTenantLeakTest();
});

it('prevents cross-tenant leakage of usage events', function () {
    $this->assertTenantBSeesNoDataFromA(
        fn ($tenantA) => UsageEvent::where('tenant_id', $tenantA->id)
    );
});

it('prevents cross-tenant leakage of usage rollups', function () {
    $this->assertTenantBSeesNoDataFromA(
        fn ($tenantA) => UsageRollup::where('tenant_id', $tenantA->id)
    );
});

it('resets tenant context between units of work', function () {
    $this->assertNoLingeringTenantContext();
});
