<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Metering\Application\DTO\RecordUsageData;
use App\Modules\Platform\Metering\Application\Services\MeteringManager;
use App\Modules\Platform\Metering\Contracts\MeterBillingProvider;
use App\Modules\Platform\Metering\Domain\Models\UsageRollup;
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

it('aggregates sum usage into durable rollups', function () {
    $tenant = ($this->makeTenant)('agg-sum');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $period = now()->format('Y-m');

    $manager->record(new RecordUsageData(meter: 'bookings', quantity: 3), $tenant);
    $manager->record(new RecordUsageData(meter: 'bookings', quantity: 4), $tenant);

    $manager->aggregate((string) $tenant->id, $period);

    $rollup = UsageRollup::query()
        ->forTenant($tenant->id)
        ->forMeter('bookings')
        ->forPeriod($period)
        ->first();

    expect($rollup)->not->toBeNull();
    expect($rollup->value)->toBe(7);
    expect($rollup->aggregation)->toBe('sum');
    expect($rollup->billed_at)->toBeNull();
});

it('aggregates max usage for gauge meters', function () {
    $tenant = ($this->makeTenant)('agg-max');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $period = now()->format('Y-m');

    $manager->record(new RecordUsageData(meter: 'staff', quantity: 2), $tenant);
    $manager->record(new RecordUsageData(meter: 'staff', quantity: 9), $tenant);
    $manager->record(new RecordUsageData(meter: 'staff', quantity: 4), $tenant);

    $manager->aggregate((string) $tenant->id, $period);

    $rollup = UsageRollup::query()
        ->forTenant($tenant->id)
        ->forMeter('staff')
        ->forPeriod($period)
        ->first();

    expect($rollup->value)->toBe(9);
    expect($rollup->aggregation)->toBe('max');
});

it('skips meters without events and without existing rollups', function () {
    $tenant = ($this->makeTenant)('agg-empty');
    tenancy()->initialize($tenant);

    $period = now()->format('Y-m');

    app(MeteringManager::class)->aggregate((string) $tenant->id, $period);

    expect(UsageRollup::count())->toBe(0);
});

it('reports billable meters to the active provider exactly once per period', function () {
    config(['metering.provider' => 'fake']);

    $reported = [];
    $fakeProvider = new class($reported) implements MeterBillingProvider
    {
        public function __construct(public array &$reported) {}

        public function reportUsage($tenant, $meter, int $quantity, string $period): void
        {
            $this->reported[] = [
                'meter' => $meter->key,
                'quantity' => $quantity,
                'period' => $period,
            ];
        }
    };

    app()->bind(MeterBillingProvider::class, fn () => $fakeProvider);

    $tenant = ($this->makeTenant)('agg-billable');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $period = now()->format('Y-m');

    $manager->record(new RecordUsageData(meter: 'whatsapp_messages', quantity: 10), $tenant);

    $manager->aggregate((string) $tenant->id, $period);
    $manager->aggregate((string) $tenant->id, $period);

    $rollup = UsageRollup::query()
        ->forTenant($tenant->id)
        ->forMeter('whatsapp_messages')
        ->forPeriod($period)
        ->first();

    expect($rollup->billed_at)->not->toBeNull();
    expect($reported)->toHaveCount(1);
    expect($reported[0])->toBe([
        'meter' => 'whatsapp_messages',
        'quantity' => 10,
        'period' => $period,
    ]);
});

it('does not report billable meters when no provider is configured', function () {
    $tenant = ($this->makeTenant)('agg-noprovider');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $period = now()->format('Y-m');

    $manager->record(new RecordUsageData(meter: 'whatsapp_messages', quantity: 5), $tenant);
    $manager->aggregate((string) $tenant->id, $period);

    $rollup = UsageRollup::query()
        ->forTenant($tenant->id)
        ->forMeter('whatsapp_messages')
        ->forPeriod($period)
        ->first();

    expect($rollup->billed_at)->toBeNull();
});

it('falls back to durable rollups when the hot counter is lost', function () {
    $tenant = ($this->makeTenant)('agg-fallback');
    tenancy()->initialize($tenant);

    $manager = app(MeteringManager::class);
    $period = now()->format('Y-m');

    $manager->record(new RecordUsageData(meter: 'bookings', quantity: 4), $tenant);

    $manager->reset($tenant, 'bookings');
    $manager->aggregate((string) $tenant->id, $period);

    expect($manager->usage($tenant, 'bookings', $period))->toBe(4);
});
