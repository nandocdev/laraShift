<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\CorrelationId;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->middleware = app(CorrelationId::class);
});

test('generates correlation id when not present in request', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->headers->get('X-Correlation-Id'))->not->toBeNull();
    expect($request->attributes->get('correlation_id'))->not->toBeNull();
});

test('preserves incoming correlation id', function () {
    $request = Request::create('http://example.com', 'GET', [], [], [], [
        'HTTP_X-Correlation-Id' => 'incoming-corr-id',
    ]);

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->headers->get('X-Correlation-Id'))->toBe('incoming-corr-id');
    expect($request->attributes->get('correlation_id'))->toBe('incoming-corr-id');
});

test('correlation id helper returns current value', function () {
    $request = Request::create('http://example.com');
    $request->attributes->set('correlation_id', 'test-123');

    $this->app->instance('request', $request);

    expect(CorrelationId::current())->toBe('test-123');
});

test('correlation id helper returns null when not set', function () {
    $request = Request::create('http://example.com');

    $this->app->instance('request', $request);

    expect(CorrelationId::current())->toBeNull();
});

test('response includes correlation id header', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->headers->has('X-Correlation-Id'))->toBeTrue();
});
