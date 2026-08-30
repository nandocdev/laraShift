<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\PaymentWebhookReceived;
use App\Modules\Platform\Integrations\Dlocal\Jobs\ResolveDlocalWebhookJob;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('resolves a tenant webhook into the platform integration event', function () {
    Event::fake([PaymentWebhookReceived::class]);

    $tenant = Tenant::create([
        'id' => (string) Str::uuid(),
        'slug' => 'dlocal-resolve',
        'name' => 'dLocal Resolve',
        'email' => 'resolve@test.com',
    ]);

    tenancy()->initialize($tenant);

    PaymentReference::create([
        'external_reference' => 'P-RESOLVE-1',
        'order_id' => 'sub_'.$tenant->id,
        'context' => 'tenant',
        'tenant_id' => $tenant->id,
    ]);

    (new ResolveDlocalWebhookJob(
        externalReference: 'P-RESOLVE-1',
        rawPayload: ['id' => 'P-RESOLVE-1', 'status' => 'PAID'],
        signature: 'sig',
        webhookSecret: 'secret',
    ))->handle();

    Event::assertDispatched(function (PaymentWebhookReceived $event) use ($tenant) {
        return $event->context === 'tenant'
            && $event->tenantId === $tenant->id
            && $event->signature === 'sig'
            && str_contains($event->rawPayload, 'P-RESOLVE-1');
    });
});

it('resolves a central webhook without tenant context', function () {
    Event::fake([PaymentWebhookReceived::class]);

    PaymentReference::create([
        'external_reference' => 'P-RESOLVE-2',
        'order_id' => 'central-order',
        'context' => 'central',
        'tenant_id' => null,
    ]);

    (new ResolveDlocalWebhookJob(
        externalReference: 'P-RESOLVE-2',
        rawPayload: ['id' => 'P-RESOLVE-2'],
    ))->handle();

    Event::assertDispatched(function (PaymentWebhookReceived $event) {
        return $event->context === 'central'
            && $event->tenantId === null;
    });
});

it('billing listener queues the processing job with resolved context', function () {
    Queue::fake();

    PaymentWebhookReceived::dispatch(
        'tenant',
        '00000000-0000-0000-0000-000000000099',
        '{"id":"P-1"}',
        'sig',
        'secret',
    );

    Queue::assertPushed(ProcessPaymentWebhookJob::class, function (ProcessPaymentWebhookJob $job) {
        return $job->tenantId === '00000000-0000-0000-0000-000000000099';
    });
});

it('ignores webhooks with unknown references', function () {
    Event::fake([PaymentWebhookReceived::class]);

    (new ResolveDlocalWebhookJob(externalReference: 'UNKNOWN', rawPayload: []))->handle();

    Event::assertNotDispatched(PaymentWebhookReceived::class);
});

it('rejects webhooks originating from non-allowed IPs', function () {
    config()->set('dlocal.webhook_allowed_ips', ['198.51.100.1', '203.0.113.0/24']);
    config()->set('dlocal.webhook_secret', 'secret');

    $payload = json_encode(['id' => 'P-FORBIDDEN']);
    $signature = hash_hmac('sha256', $payload, 'secret');

    $response = $this->withHeaders([
        'X-Signature' => $signature,
        'REMOTE_ADDR' => '192.0.2.1',
    ])->postJson('/webhooks/dlocal', ['id' => 'P-FORBIDDEN']);

    $response->assertStatus(403);
});

it('accepts webhooks originating from allowed IPs and valid signature', function () {
    config()->set('dlocal.webhook_allowed_ips', ['198.51.100.1', '203.0.113.0/24']);
    config()->set('dlocal.webhook_secret', 'secret');

    PaymentReference::create([
        'external_reference' => 'P-ALLOWED',
        'order_id' => 'order-allowed',
        'context' => 'central',
    ]);

    Queue::fake();

    $payload = json_encode(['id' => 'P-ALLOWED']);
    $signature = hash_hmac('sha256', $payload, 'secret');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.45'])
        ->call('POST', '/webhooks/dlocal', [], [], [], [
            'HTTP_X_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

    $response->assertStatus(204);
    Queue::assertPushed(ResolveDlocalWebhookJob::class);
});

it('idempotently deduplicates consecutive identical webhook resolutions', function () {
    Event::fake([PaymentWebhookReceived::class]);
    Cache::flush();

    PaymentReference::create([
        'external_reference' => 'P-DEDUP',
        'order_id' => 'order-dedup',
        'context' => 'central',
    ]);

    $job1 = new ResolveDlocalWebhookJob(
        externalReference: 'P-DEDUP',
        rawPayload: ['id' => 'P-DEDUP', 'status' => 'PAID'],
        signature: 'sig',
        webhookSecret: 'secret',
    );
    $job1->handle();

    $job2 = new ResolveDlocalWebhookJob(
        externalReference: 'P-DEDUP',
        rawPayload: ['id' => 'P-DEDUP', 'status' => 'PAID'],
        signature: 'sig',
        webhookSecret: 'secret',
    );
    $job2->handle();

    // Event should only have been dispatched once due to Cache deduplication
    Event::assertDispatchedTimes(PaymentWebhookReceived::class, 1);
});
