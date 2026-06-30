<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Tenancy\Services\TenantResolver;
use Illuminate\Http\Request;

beforeEach(function () {
    config(['tenancy.central_domain' => 'larashift.test']);
    config(['tenancy.central_domains' => ['127.0.0.1', 'localhost', 'larashift.test']]);

    $this->resolver = app(TenantResolver::class);

    $this->plan = Plan::firstOrCreate(['slug' => 'free'], [
        'name' => 'Free Plan',
        'price_monthly' => 0,
        'price_yearly' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'is_active' => true,
        'features' => [],
    ]);

    $this->tenant = Tenant::firstOrCreate(['id' => '00000000-0000-0000-0000-000000000001'], [
        'slug' => 'test-tenant',
        'name' => 'Test Tenant',
        'email' => 'test@tenant.com',
        'plan_id' => 'free',
        'status' => 'active',
    ]);
});

test('resolves tenant by subdomain', function () {
    $request = Request::create('http://test-tenant.larashift.test');

    $tenant = $this->resolver->resolveBySubdomain($request);

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($this->tenant->id);
});

test('returns null for central domain', function () {
    $request = Request::create('http://larashift.test');

    $tenant = $this->resolver->resolveBySubdomain($request);

    expect($tenant)->toBeNull();
});

test('returns null for www subdomain', function () {
    $request = Request::create('http://www.larashift.test');

    $tenant = $this->resolver->resolveBySubdomain($request);

    expect($tenant)->toBeNull();
});

test('resolves tenant by header', function () {
    $request = Request::create('http://example.com', 'GET', [], [], [], [
        'HTTP_X-Tenant-Id' => $this->tenant->id,
    ]);

    $tenant = $this->resolver->resolveByHeader($request);

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($this->tenant->id);
});

test('resolves tenant by X-Tenant header', function () {
    $request = Request::create('http://example.com', 'GET', [], [], [], [
        'HTTP_X-Tenant' => $this->tenant->slug,
    ]);

    $tenant = $this->resolver->resolveByHeader($request);

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($this->tenant->id);
});

test('returns null when no header present', function () {
    $request = Request::create('http://example.com');

    $tenant = $this->resolver->resolveByHeader($request);

    expect($tenant)->toBeNull();
});

test('resolves tenant by session', function () {
    $request = Request::create('http://example.com');
    $request->setLaravelSession($this->app['session']->driver());
    $request->session()->put('tenant_id', $this->tenant->id);

    $tenant = $this->resolver->resolveBySession($request);

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($this->tenant->id);
});

test('resolve method tries all strategies', function () {
    $request = Request::create('http://test-tenant.larashift.test');
    $request->setLaravelSession($this->app['session']->driver());

    $tenant = $this->resolver->resolve($request);

    expect($tenant)->not->toBeNull();
});

test('findById returns tenant from cache', function () {
    $tenant = $this->resolver->findById($this->tenant->id);

    expect($tenant)->not->toBeNull();
    expect($tenant->id)->toBe($this->tenant->id);
});

test('findBySlug returns tenant from cache', function () {
    $tenant = $this->resolver->findBySlug('test-tenant');

    expect($tenant)->not->toBeNull();
    expect($tenant->slug)->toBe('test-tenant');
});

test('forgetCache clears resolver cache', function () {
    $this->resolver->findById($this->tenant->id);

    $this->resolver->forgetCache($this->tenant);

    expect(\Illuminate\Support\Facades\Cache::get("tenant:id:{$this->tenant->id}"))->toBeNull();
});
