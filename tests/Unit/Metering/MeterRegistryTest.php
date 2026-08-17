<?php

declare(strict_types=1);

use App\Modules\Platform\Metering\Domain\Enums\AggregationType;
use App\Modules\Platform\Metering\Domain\Exceptions\MeterNotFoundException;
use App\Modules\Platform\Metering\Domain\MeterRegistry;

it('builds meters from the config registry', function () {
    $registry = new MeterRegistry([
        'bookings' => ['name' => 'Bookings', 'aggregation' => 'sum'],
        'staff' => [
            'name' => 'Staff',
            'unit' => 'member',
            'aggregation' => 'max',
            'billable' => true,
            'provider_event_name' => 'staff_meter',
        ],
    ]);

    expect($registry->has('bookings'))->toBeTrue();

    $bookings = $registry->get('bookings');
    expect($bookings->key)->toBe('bookings')
        ->and($bookings->name)->toBe('Bookings')
        ->and($bookings->aggregation)->toBe(AggregationType::Sum)
        ->and($bookings->billable)->toBeFalse()
        ->and($bookings->providerEventName)->toBeNull();

    $staff = $registry->get('staff');
    expect($staff->unit)->toBe('member')
        ->and($staff->aggregation)->toBe(AggregationType::Max)
        ->and($staff->billable)->toBeTrue()
        ->and($staff->providerEventName)->toBe('staff_meter');
});

it('defaults to sum aggregation and generic naming', function () {
    $registry = new MeterRegistry(['api_calls' => []]);

    expect($registry->get('api_calls')->aggregation)->toBe(AggregationType::Sum)
        ->and($registry->get('api_calls')->billable)->toBeFalse();
});

it('exposes all meters', function () {
    $registry = new MeterRegistry(['a' => [], 'b' => [], 'c' => []]);

    expect($registry->all())->toHaveCount(3)
        ->and(array_keys($registry->all()))->toBe(['a', 'b', 'c']);
});

it('throws when requesting an unknown meter', function () {
    (new MeterRegistry([]))->get('nope');
})->throws(MeterNotFoundException::class);
