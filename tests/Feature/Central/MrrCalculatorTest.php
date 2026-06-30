<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Billing\Services\MrrCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mrr = app(MrrCalculator::class);

    Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);
});

test('mrr is zero with no paid tenants', function () {
    $mrr = $this->mrr->calculateMrr();

    expect($mrr)->toBe(0.0);
});

test('churn rate is zero with no churned tenants', function () {
    $rate = $this->mrr->churnRate(now()->subMonth());

    expect($rate)->toBe(0.0);
});

test('tenant status counts returns array', function () {
    $counts = $this->mrr->tenantStatusCounts();

    expect($counts)->toBeArray();
});

test('monthly breakdown returns expected structure', function () {
    $breakdown = $this->mrr->monthlyBreakdown(3);

    expect($breakdown)->toHaveCount(3);
    expect($breakdown[0])->toHaveKeys(['month', 'mrr', 'new_tenants', 'churned']);
});
