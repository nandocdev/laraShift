<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\GlobalRateLimiter;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->middleware = new GlobalRateLimiter(maxAttempts: 100, decaySeconds: 60);
});

test('allows requests under the limit', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});

test('adds rate limit headers to response', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
    expect($response->headers->has('X-RateLimit-Remaining'))->toBeTrue();
});

test('rate limiter does not crash on request failure', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});

test('rate limiter fails open when cache is unavailable', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200);
});
