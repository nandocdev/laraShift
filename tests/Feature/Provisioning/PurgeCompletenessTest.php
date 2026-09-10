<?php

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
        'status' => 'active',
    ]);
    (new PurgeTenantJob($tenant->id, $tenant->slug))->handle(app(TenantRateLimiter::class));
    expect(Tenant::withoutGlobalScopes()->where('id', $tenant->id)->exists())->toBeFalse();
});
