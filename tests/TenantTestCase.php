<?php

namespace Tests;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Catalog\Domain\Models\Plan;
use Illuminate\Support\Str;

abstract class TenantTestCase extends TestCase
{
    protected Tenant $tenant;
    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free Plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'amount' => 0,
            'currency' => 'USD',
            'is_active' => true,
            'features' => [],
        ]);

        $id = (string) Str::uuid();
        $this->tenantId = $id;

        $this->tenant = Tenant::create([
            'id' => $id,
            'slug' => 'test-' . substr($id, 0, 8),
            'name' => 'Test Tenant',
            'email' => 'test-' . substr($id, 0, 8) . '@tenant.com',
            'plan_id' => 'free',
            'status' => 'active',
            'billing_gateway' => 'paguelofacil',
        ]);

        $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $domain = $this->tenant->slug . '.' . $centralDomain;
        
        $this->tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($this->tenant);
    }
}
