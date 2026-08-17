<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Metering\Application\DTO\RecordUsageData;
use App\Modules\Platform\Metering\Application\Services\MeteringManager;
use App\Modules\Platform\Metering\Domain\Exceptions\MeterNotFoundException;
use App\Modules\Platform\Metering\Domain\Models\UsageEvent;
use App\Modules\Platform\Metering\Support\Facades\Meter;
use App\Modules\Platform\Tenancy\Domain\Exceptions\QuotaExceededException;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);

    $this->makeTenant = fn (string $slug): Tenant => Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => $slug,
        'name' => Str::headline($slug),
        'email' => $slug.'@test.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

it('records durable usage events and reflects usage through the hot counter', function () {
    $tenant = ($this->makeTenant)('meter-record');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $manager->reset($tenant, 'bookings');

    $first = $manager->record(new RecordUsageData(meter: 'bookings', quantity: 2), $tenant);
    $second = $manager->record(new RecordUsageData(meter: 'bookings', quantity: 3), $tenant);

    expect(UsageEvent::count())->toBe(2);
    expect($first)->not->toBeNull();
    expect($second)->not->toBeNull();
    expect($first->tenant_id)->toBe($tenant->id);
    expect($second->quantity)->toBe(3);
    expect($manager->usage($tenant, 'bookings'))->toBe(5);
});

it('records against the active tenant context when no tenant is passed', function () {
    $tenant = ($this->makeTenant)('meter-context');
    tenancy()->initialize($tenant);

    $event = Meter::record(new RecordUsageData(meter: 'bookings'));

    expect($event)->not->toBeNull();
    expect($event->tenant_id)->toBe($tenant->id);
    expect(Meter::usage($tenant, 'bookings'))->toBe(1);
});

it('deduplicates events sharing the same idempotency key', function () {
    $tenant = ($this->makeTenant)('meter-dedupe');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $manager->reset($tenant, 'bookings');

    $data = new RecordUsageData(meter: 'bookings', quantity: 1, dedupeKey: 'order-123');

    $first = $manager->record($data, $tenant);
    $second = $manager->record($data, $tenant);

    expect($first)->not->toBeNull();
    expect($second)->toBeNull();
    expect(UsageEvent::count())->toBe(1);
    expect($manager->usage($tenant, 'bookings'))->toBe(1);
});

it('enforces plan quotas before recording when enforceQuota is set', function () {
    $tenant = ($this->makeTenant)('meter-quota');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $manager->reset($tenant, 'invitations');

    for ($i = 0; $i < 5; $i++) {
        $manager->record(new RecordUsageData(meter: 'invitations', quantity: 1, enforceQuota: true), $tenant);
    }

    expect($manager->usage($tenant, 'invitations'))->toBe(5);

    expect(fn () => $manager->record(
        new RecordUsageData(meter: 'invitations', quantity: 1, enforceQuota: true),
        $tenant
    ))->toThrow(QuotaExceededException::class);

    expect(UsageEvent::count())->toBe(5);
    expect($manager->usage($tenant, 'invitations'))->toBe(5);
});

it('rejects meters that are not registered', function () {
    $tenant = ($this->makeTenant)('meter-unknown');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);

    expect(fn () => $manager->record(new RecordUsageData(meter: 'nope'), $tenant))
        ->toThrow(MeterNotFoundException::class);
});

it('tracks gauge meters via max aggregation', function () {
    $tenant = ($this->makeTenant)('meter-max');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);

    $manager->record(new RecordUsageData(meter: 'staff', quantity: 3), $tenant);
    $manager->record(new RecordUsageData(meter: 'staff', quantity: 5), $tenant);
    $manager->record(new RecordUsageData(meter: 'staff', quantity: 2), $tenant);

    expect($manager->usage($tenant, 'staff'))->toBe(5);
});

it('becomes a no-op when metering is disabled', function () {
    config(['metering.enabled' => false]);

    $tenant = ($this->makeTenant)('meter-disabled');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);

    $event = $manager->record(new RecordUsageData(meter: 'bookings', quantity: 1), $tenant);

    expect($event)->toBeNull();
    expect(UsageEvent::count())->toBe(0);
    expect($manager->usage($tenant, 'bookings'))->toBe(0);
});
