<?php

use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Provisioning\Jobs\PurgeTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Security\RateLimiting\TenantRateLimiter;
use Illuminate\Support\Str;

it('purges all tenant rows, no orphans', function () {
    $tenant = Tenant::create([
        'id' => Str::uuid(),
        'slug' => 'test-purge-'.Str::random(5),
        'name' => 'Test Tenant',
        'email' => 'test@example.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
    Payment::factory()->create([
        'tenant_id' => $tenant->id,
        'slug' => Str::uuid(),
        'display_id' => 'x',
        'amount' => 10,
        'status' => 'approved',
    ]);
    (new PurgeTenantJob($tenant->id, $tenant->slug))->handle(app(TenantRateLimiter::class));
    expect(Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});
