<?php

declare(strict_types=1);

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Events\PaymentWebhookReceived;
use App\Modules\Platform\Integrations\Dlocal\Jobs\ResolveDlocalWebhookJob;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
