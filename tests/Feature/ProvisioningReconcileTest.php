<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->makeTenant = function (string $slug, string $status, ?CarbonInterface $createdAt = null): Tenant {
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'slug' => $slug,
            'name' => Str::headline($slug),
            'email' => $slug.'@test.com',
            'status' => $status,
        ]);

        if ($createdAt !== null) {
            // The stancl Tenant model routes non-custom attributes into the
            // `data` JSON column, so created_at must be forced at SQL level to
            // simulate an old record.
            DB::table('tenants')->where('id', $tenant->id)->update(['created_at' => $createdAt]);
        }

        return $tenant;
    };
});

it('re-dispatches provisioning for failed tenants', function () {
    Bus::fake();

    $tenant = ($this->makeTenant)('reconcile-failed', 'failed');

    $this->artisan('provisioning:reconcile')->assertExitCode(0);

    Bus::assertDispatched(ProvisionTenantJob::class, function (ProvisionTenantJob $job) use ($tenant) {
        return $job->tenantId === $tenant->id && $job->finalStatus === 'active';
    });

    expect($tenant->fresh()->status)->toBe('provisioning');
});

it('re-dispatches provisioning for stale provisioning tenants', function () {
    Bus::fake();

    $tenant = ($this->makeTenant)('reconcile-stale', 'provisioning', now()->subHours(2));

    $this->artisan('provisioning:reconcile')->assertExitCode(0);

    Bus::assertDispatched(ProvisionTenantJob::class, fn (ProvisionTenantJob $job) => $job->tenantId === $tenant->id);
});

it('does not re-dispatch fresh provisioning tenants', function () {
    Bus::fake();

    ($this->makeTenant)('reconcile-fresh', 'provisioning');

    $this->artisan('provisioning:reconcile')->assertExitCode(0);

    Bus::assertNotDispatched(ProvisionTenantJob::class);
});

it('reconciles a single tenant with the --tenant option', function () {
    Bus::fake();

    $tenant = ($this->makeTenant)('reconcile-single', 'failed');

    $this->artisan('provisioning:reconcile', ['--tenant' => $tenant->id])->assertExitCode(0);

    Bus::assertDispatched(ProvisionTenantJob::class, fn (ProvisionTenantJob $job) => $job->tenantId === $tenant->id);
});
