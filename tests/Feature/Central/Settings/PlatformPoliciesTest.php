<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Growth\Application\Actions\QuarantineTenantAction;
use App\Modules\Central\Growth\Domain\ValueObjects\FraudSignals;
use App\Modules\Central\Growth\Infrastructure\Notifications\SecOpsAlertNotification;
use App\Modules\Central\Provisioning\Jobs\ProvisionTenantJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Central\Settings\Infrastructure\Services\PlatformPolicies;
use App\Modules\Central\Settings\Interface\Livewire\ManagePolicies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

function policyTenant(string $slug, string $status = 'active'): Tenant
{
    return Tenant::create([
        'id' => (string) Str::uuid(), 'slug' => $slug, 'name' => Str::headline($slug),
        'email' => $slug.'@test.com', 'status' => $status,
    ]);
}

it('reads config defaults on a fresh install and round-trips overrides', function () {
    expect(PlatformPolicies::fraudThreshold())->toBe((int) config('fraud.quarantine_threshold', 80))
        ->and(PlatformPolicies::staleProvisioningMinutes())->toBe((int) config('provisioning.stale_provisioning_minutes', 30));

    PlatformPolicies::set(PlatformPolicies::FRAUD_THRESHOLD, 50, 'int');
    PlatformPolicies::set(PlatformPolicies::SECOPS_EMAIL, 'secops@test.com');
    PlatformPolicies::set(PlatformPolicies::STALE_MINUTES, 120, 'int');

    expect(PlatformPolicies::fraudThreshold())->toBe(50)
        ->and(PlatformPolicies::secopsEmail())->toBe('secops@test.com')
        ->and(PlatformPolicies::staleProvisioningMinutes())->toBe(120);
});
it('applies the policy threshold to fraud quarantine decisions', function () {
    $signals = new FraudSignals(score: 60, signals: [], ip: '127.0.0.1', email: 'user@test.com');

    expect($signals->exceedsThreshold(PlatformPolicies::fraudThreshold()))->toBeFalse();

    PlatformPolicies::set(PlatformPolicies::FRAUD_THRESHOLD, 50, 'int');

    expect($signals->exceedsThreshold(PlatformPolicies::fraudThreshold()))->toBeTrue();
});

it('notifies the policy SecOps email on quarantine', function () {
    Notification::fake();
    PlatformPolicies::set(PlatformPolicies::SECOPS_EMAIL, 'policy-soc@test.com');

    $tenant = policyTenant('policy-quarantine');
    app(QuarantineTenantAction::class)->execute(
        $tenant,
        new FraudSignals(score: 90, signals: [], ip: '127.0.0.1', email: 'evil@test.com')
    );

    Notification::assertSentOnDemand(SecOpsAlertNotification::class);
    expect($tenant->fresh()->status)->toBe('quarantine');
});

it('honours the policy stale window in the reconciler', function () {
    Bus::fake();

    $tenant = policyTenant('policy-stale', 'provisioning');
    DB::table('tenants')->where('id', $tenant->id)->update(['created_at' => now()->subHours(2)]);

    PlatformPolicies::set(PlatformPolicies::STALE_MINUTES, 10000, 'int');

    $this->artisan('provisioning:reconcile')->assertExitCode(0);

    Bus::assertNotDispatched(ProvisionTenantJob::class);
});

it('recovers invalid JSON settings to the default instead of crashing', function () {
    DB::table('central_settings')->insert([
        'key' => PlatformPolicies::FRAUD_THRESHOLD,
        'value' => '{not json',
        'type' => 'json',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(PlatformPolicies::fraudThreshold())->toBe((int) config('fraud.quarantine_threshold', 80));
});
