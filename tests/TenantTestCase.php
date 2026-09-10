<?php

namespace Tests;

use App\Modules\Central\Provisioning\Models\Tenant;
use Illuminate\Support\Str;

abstract class TenantTestCase extends TestCase
{
    protected Tenant $tenant;

    protected string $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $id = (string) Str::uuid();
        $this->tenantId = $id;

        $this->tenant = Tenant::create([
            'id' => $id,
            'slug' => 'test-'.substr($id, 0, 8),
            'name' => 'Test Tenant',
            'email' => 'test-'.substr($id, 0, 8).'@tenant.com',
            'status' => 'active',
        ]);

        $centralDomain = parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost';
        $domain = $this->tenant->slug.'.'.$centralDomain;

        $this->tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($this->tenant);
    }
}
