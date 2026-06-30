<?php

declare(strict_types=1);

use App\Modules\Shared\Http\Middleware\TraceContext;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->middleware = app(TraceContext::class);
});

test('generates trace context when not present', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    expect($response->headers->has('traceparent'))->toBeTrue();
    expect($request->attributes->get('trace_id'))->not->toBeNull();
    expect($request->attributes->get('span_id'))->not->toBeNull();
});

test('traceparent format is valid w3c', function () {
    $request = Request::create('http://example.com');

    $response = $this->middleware->handle($request, fn () => response('ok'));

    $traceparent = $response->headers->get('traceparent');
    expect(preg_match('/^[0-9a-f]{2}-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $traceparent))->toBe(1);
});

test('preserves incoming valid traceparent', function () {
    $request = Request::create('http://example.com', 'GET', [], [], [], [
        'HTTP_traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
    ]);

    $response = $this->middleware->handle($request, fn () => response('ok'));

    $traceparent = $response->headers->get('traceparent');
    expect($traceparent)->toContain('4bf92f3577b34da6a3ce929d0e0e4736');
});

test('helper methods return trace context', function () {
    $request = Request::create('http://example.com');

    $this->middleware->handle($request, fn () => response('ok'));
    $this->app->instance('request', $request);

    expect(TraceContext::currentTraceId())->not->toBeNull();
    expect(TraceContext::currentSpanId())->not->toBeNull();
});
