<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Settings\Models\TenantSetting;
use App\Modules\Tenant\Settings\Services\ConfigResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'settings-svc-' . Str::random(4),
        'name' => 'Settings Test',
        'email' => 'settings-svc@test.com',
        'plan_id' => 'free',
    ]);
});

test('config resolver returns default values', function () {
    $resolver = app(ConfigResolver::class);

    $config = $resolver->getAll($this->tenant);

    expect($config['timezone'])->toBe('UTC');
    expect($config['locale'])->toBe('en');
    expect($config['currency'])->toBe('USD');
    expect($config['plan_name'])->toBe('Free');
});

test('config resolver prefers tenant settings over defaults', function () {
    tenancy()->initialize($this->tenant);

    TenantSetting::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'timezone' => 'America/New_York',
        'locale' => 'es',
        'currency' => 'EUR',
    ]);

    $resolver = app(ConfigResolver::class);

    $config = $resolver->getAll($this->tenant);

    expect($config['timezone'])->toBe('America/New_York');
    expect($config['locale'])->toBe('es');
    expect($config['currency'])->toBe('EUR');
});

test('config resolver returns default for missing key', function () {
    $resolver = app(ConfigResolver::class);

    expect($resolver->get($this->tenant, 'nonexistent', 'fallback'))->toBe('fallback');
});
