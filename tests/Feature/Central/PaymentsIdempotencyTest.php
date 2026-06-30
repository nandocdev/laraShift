<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Models\Plan;
use App\Modules\Central\Payments\Actions\RefundPaymentAction;
use App\Modules\Central\Payments\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Infrastructure\Http\IdempotencyMiddleware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->middleware = app(IdempotencyMiddleware::class);

    Plan::firstOrCreate(['slug' => 'free'], [
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
        'slug' => 'idempotency-test-'.Str::random(4),
        'name' => 'Idempotency Test',
        'email' => 'idempotency@test.com',
        'plan_id' => 'free',
    ]);
});

test('idempotency middleware caches response for post requests', function () {
    $request = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => 'test-key-12345',
    ]);

    $response = $this->middleware->handle($request, fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('idempotency middleware returns cached response on duplicate key', function () {
    $key = 'dup-key-'.Str::random(8);

    $request = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => $key,
    ]);

    $first = $this->middleware->handle($request, fn () => response('first-response', 201));

    $request2 = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => $key,
    ]);

    $second = $this->middleware->handle($request2, fn () => response('should-not-be-called', 200));

    expect($second->getStatusCode())->toBe(201);
    expect($second->getContent())->toBe('first-response');
});

test('idempotency middleware rejects invalid key format', function () {
    $request = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => 'short',
    ]);

    $response = $this->middleware->handle($request, fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(400);
});

test('idempotency middleware does not cache 5xx responses', function () {
    $key = 'error-key-'.Str::random(8);

    $request = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => $key,
    ]);

    $first = $this->middleware->handle($request, fn () => response('error', 500));

    $request2 = Request::create('http://example.com/api/test', 'POST', [], [], [], [
        'HTTP_Idempotency-Key' => $key,
    ]);

    $second = $this->middleware->handle($request2, fn () => response('retried', 200));

    expect($second->getStatusCode())->toBe(200);
    expect($second->getContent())->toBe('retried');
});

test('idempotency middleware is skipped for get requests', function () {
    $request = Request::create('http://example.com/api/test', 'GET', [], [], [], [
        'HTTP_Idempotency-Key' => 'get-key',
    ]);

    $response = $this->middleware->handle($request, fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

test('refund action marks payment as refunded', function () {
    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'display_id' => 'refund-test-'.Str::random(6),
        'slug' => 'refund-test',
        'amount' => 29.99,
        'description' => 'Refund Test',
        'email' => 'refund@test.com',
        'status' => 'approved',
        'gateway' => 'paguelofacil',
    ]);

    Event::fake();

    $action = app(RefundPaymentAction::class);
    $result = $action->execute($payment, $this->tenant, 'Customer request', 'admin@test.com');

    expect($result['status'])->toBe('refunded');

    $payment->refresh();
    expect($payment->status)->toBe('refunded');
    expect($payment->refunded_at)->not->toBeNull();
});

test('refund action rejects already refunded payment', function () {
    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'display_id' => 'double-refund-'.Str::random(6),
        'slug' => 'double-refund',
        'amount' => 10.00,
        'description' => 'Double Refund',
        'email' => 'double@test.com',
        'status' => 'refunded',
        'refunded_at' => now(),
        'gateway' => 'paguelofacil',
    ]);

    $action = app(RefundPaymentAction::class);

    expect(fn () => $action->execute($payment, $this->tenant, 'Double', 'admin'))
        ->toThrow(RuntimeException::class, 'already been refunded');
});

test('refund action rejects non-approved payment', function () {
    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'display_id' => 'pending-refund-'.Str::random(6),
        'slug' => 'pending-refund',
        'amount' => 10.00,
        'description' => 'Pending Refund',
        'email' => 'pending@test.com',
        'status' => 'pending',
        'gateway' => 'paguelofacil',
    ]);

    $action = app(RefundPaymentAction::class);

    expect(fn () => $action->execute($payment, $this->tenant, 'Not approved', 'admin'))
        ->toThrow(RuntimeException::class, 'approved payments');
});

test('refund dispatches PaymentRefunded event', function () {
    Event::fake();

    $payment = Payment::create([
        'tenant_id' => $this->tenant->id,
        'display_id' => 'event-refund-'.Str::random(6),
        'slug' => 'event-refund',
        'amount' => 15.00,
        'description' => 'Event Refund',
        'email' => 'event@test.com',
        'status' => 'approved',
        'gateway' => 'paguelofacil',
    ]);

    $action = app(RefundPaymentAction::class);
    $action->execute($payment, $this->tenant, 'Audit test', 'admin');

    Event::assertDispatched(App\Modules\Shared\Events\PaymentRefunded::class);
});

test('process payment webhook job uses exponential backoff', function () {
    $job = new ProcessPaymentWebhookJob(
        tenantId: $this->tenant->id,
        rawPayload: '{}',
        signature: 'sig',
        webhookSecret: 'secret',
    );

    $backoff = $job->backoff();

    expect($backoff)->toBe([30, 120, 480, 1920, 7200]);
    expect($job->tries)->toBe(5);
});
