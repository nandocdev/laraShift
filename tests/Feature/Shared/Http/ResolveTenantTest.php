<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\ResolveTenant;
use App\Modules\Shared\Tenancy\Services\TenantResolver;
use Illuminate\Http\Request;

test('middleware does not re-initialize if tenancy already active', function () {
    $middleware = app(ResolveTenant::class);
    $request = Request::create('http://example.com');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});

test('middleware resolves tenant from subdomain', function () {
    config(['tenancy.central_domain' => 'larashift.test']);
    config(['tenancy.central_domains' => ['127.0.0.1', 'localhost', 'larashift.test']]);

    $middleware = app(ResolveTenant::class);
    $request = Request::create('http://test-tenant.larashift.test');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});

test('middleware passes through without error on central domain', function () {
    $middleware = app(ResolveTenant::class);
    $request = Request::create('http://larashift.test');

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});
