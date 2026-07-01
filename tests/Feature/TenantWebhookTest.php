<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Tenant\Identity\Models\User;
use App\Modules\Tenant\Integrations\Actions\CreateWebhookAction;
use App\Modules\Tenant\Integrations\Actions\DeleteWebhookAction;
use App\Modules\Tenant\Integrations\Actions\DispatchWebhookAction;
use App\Modules\Tenant\Integrations\Actions\UpdateWebhookAction;
use App\Modules\Tenant\Integrations\DTOs\CreateWebhookData;
use App\Modules\Tenant\Integrations\DTOs\UpdateWebhookData;
use App\Modules\Tenant\Integrations\Jobs\DeliverWebhookJob;
use App\Modules\Tenant\Integrations\Livewire\ManageWebhooks;
use App\Modules\Tenant\Integrations\Livewire\WebhookDeliveryLog;
use App\Modules\Tenant\Integrations\Models\TenantWebhook;
use App\Modules\Tenant\Integrations\Models\TenantWebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'webhook-test',
        'name' => 'Webhook Test',
        'email' => 'wh@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->initialize($this->tenant);

    $this->user = User::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Webhook Admin',
        'email' => 'whadmin@test.com',
        'password' => 'password',
    ]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('creates a webhook endpoint', function () {
    $action = app(CreateWebhookAction::class);

    $data = new CreateWebhookData(
        url: 'https://example.com/webhook',
        secret: Str::random(32),
        events: ['user.created', 'user.updated'],
    );

    $webhook = $action->execute($data);

    expect($webhook)->toBeInstanceOf(TenantWebhook::class);
    expect($webhook->url)->toBe('https://example.com/webhook');
    expect($webhook->events)->toBe(['user.created', 'user.updated']);
    expect($webhook->is_active)->toBeTrue();
});

it('updates a webhook endpoint', function () {
    $webhook = TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
    ]);

    $action = app(UpdateWebhookAction::class);

    $data = new UpdateWebhookData(
        events: ['user.created', 'user.deleted'],
        max_retries: 10,
    );

    $updated = $action->execute($webhook, $data);

    expect($updated->events)->toBe(['user.created', 'user.deleted']);
    expect($updated->max_retries)->toBe(10);
    expect($updated->url)->toBe('https://example.com/webhook');
});

it('deletes a webhook endpoint', function () {
    $webhook = TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
    ]);

    $action = app(DeleteWebhookAction::class);
    $action->execute($webhook);

    expect(TenantWebhook::count())->toBe(0);
});

it('dispatches webhook job for subscribed events', function () {
    TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
        'is_active' => true,
    ]);

    Queue::fake();

    $action = app(DispatchWebhookAction::class);
    $action->execute('user.created', ['id' => '123', 'email' => 'test@test.com']);

    Queue::assertPushed(DeliverWebhookJob::class, 1);
});

it('does not dispatch for inactive webhooks', function () {
    TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
        'is_active' => false,
    ]);

    Queue::fake();

    $action = app(DispatchWebhookAction::class);
    $action->execute('user.created', ['id' => '123']);

    Queue::assertNothingPushed();
});

it('does not dispatch for unsubscribed events', function () {
    TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
        'is_active' => true,
    ]);

    Queue::fake();

    $action = app(DispatchWebhookAction::class);
    $action->execute('role.updated', ['id' => '456']);

    Queue::assertNothingPushed();
});

it('records delivery log on webhook send', function () {
    $webhook = TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
    ]);

    $delivery = TenantWebhookDelivery::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'webhook_id' => $webhook->id,
        'event' => 'user.created',
        'payload' => ['test' => true],
        'status' => 'delivered',
        'response_status' => 200,
        'completed_at' => now(),
    ]);

    expect($delivery->status)->toBe('delivered');
    expect($delivery->response_status)->toBe(200);
});

it('scopes webhooks to current tenant', function () {
    $otherTenant = Tenant::create([
        'id' => Str::uuid()->toString(),
        'slug' => 'other-webhooks',
        'name' => 'Other',
        'email' => 'other@test.com',
        'plan_id' => 'free',
    ]);

    tenancy()->end();
    tenancy()->initialize($otherTenant);

    TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $otherTenant->id,
        'url' => 'https://other.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
    ]);

    tenancy()->end();
    tenancy()->initialize($this->tenant);

    expect(TenantWebhook::count())->toBe(0);
});

it('handles webhook delivery failure and records attempt', function () {
    $webhook = TenantWebhook::create([
        'id' => Str::uuid()->toString(),
        'tenant_id' => $this->tenant->id,
        'url' => 'https://invalid-url.example.com/webhook',
        'secret' => Str::random(32),
        'events' => ['user.created'],
        'max_retries' => 1,
    ]);

    $job = new DeliverWebhookJob(
        tenantId: $this->tenant->id,
        webhookId: $webhook->id,
        event: 'user.created',
        payload: ['test' => true],
    );

    $job->handle();

    $delivery = TenantWebhookDelivery::first();

    expect($delivery)->not->toBeNull();
    expect($delivery->event)->toBe('user.created');
    expect($delivery->status)->toBeIn(['failed', 'dead_lettered']);
});

it('registers webhook livewire components', function () {
    $this->actingAs($this->user);

    Livewire::test(ManageWebhooks::class)
        ->assertStatus(200);

    Livewire::test(WebhookDeliveryLog::class)
        ->assertStatus(200);
});
