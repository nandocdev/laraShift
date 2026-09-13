<?php

declare(strict_types=1);

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Platform\Security\Hmac\HmacSigner;
use App\Modules\Tenant\Access\Application\Actions\EnsureTenantRolesExist;
use App\Modules\Tenant\Access\Application\Actions\GenerateApiKey;
use App\Modules\Tenant\Access\Domain\Models\User;
use App\Modules\Tenant\Integrations\Application\Actions\DispatchTenantWebhook;
use App\Modules\Tenant\Integrations\Application\Jobs\DispatchTenantWebhookJob;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookDelivery;
use App\Modules\Tenant\Integrations\Domain\Models\WebhookEndpoint;
use App\Modules\Tenant\Integrations\Interface\Livewire\ManageWebhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function webhookContext(string $slug): array
{
    $tenant = Tenant::create([
        'id' => Str::uuid()->toString(), 'slug' => $slug, 'name' => Str::headline($slug),
        'email' => $slug.'@test.com',
    ]);
    tenancy()->initialize($tenant);

    app(EnsureTenantRolesExist::class)->execute($tenant);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('admin');

    return [$tenant, $user];
}

function makeEndpoint(string $tenantId, string $url = 'https://erp.test.com/webhooks'): WebhookEndpoint
{
    return WebhookEndpoint::create([
        'id' => Str::uuid()->toString(), 'tenant_id' => $tenantId, 'url' => $url,
        'events' => ['api_key.created'], 'secret' => 'whsec_testsecret1234567890abcdef',
        'is_active' => true,
    ]);
}

it('registers an endpoint showing the secret only once', function () {
    [$tenant, $user] = webhookContext('wh-reg');

    $test = Livewire::actingAs($user)->test(ManageWebhooks::class)
        ->set('url', 'https://erp.test.com/webhooks')
        ->set('events', ['api_key.created'])
        ->call('register')
        ->assertHasNoErrors();

    $secret = $test->get('plainSecret');

    expect($secret)->toStartWith('whsec_');

    $endpoint = WebhookEndpoint::where('tenant_id', $tenant->id)->firstOrFail();

    // Stored encrypted, never in plain text.
    expect(DB::table('tenant_webhook_endpoints')->where('id', $endpoint->id)->value('secret'))
        ->not->toBe($secret)
        ->and($endpoint->secret)->toBe($secret);

    $test->call('closeSecretModal')->assertSet('plainSecret', '');
});

it('rejects non-https urls and unknown events', function () {
    [, $user] = webhookContext('wh-val');

    Livewire::actingAs($user)->test(ManageWebhooks::class)
        ->set('url', 'http://plain.test.com/hook')
        ->set('events', ['api_key.created'])
        ->call('register')
        ->assertHasErrors(['url']);

    Livewire::actingAs($user)->test(ManageWebhooks::class)
        ->set('url', 'https://erp.test.com/webhooks')
        ->set('events', ['booking.deleted'])
        ->call('register')
        ->assertHasErrors(['events']);

    expect(WebhookEndpoint::count())->toBe(0);
});

it('revokes an endpoint and cascades its deliveries', function () {
    [$tenant, $user] = webhookContext('wh-revoke');

    $endpoint = makeEndpoint($tenant->id);
    WebhookDelivery::create([
        'id' => Str::uuid()->toString(), 'tenant_id' => $tenant->id,
        'endpoint_id' => $endpoint->id, 'event_type' => 'api_key.created', 'status' => 'delivered',
    ]);

    Livewire::actingAs($user)->test(ManageWebhooks::class)
        ->call('revoke', $endpoint->id)
        ->assertHasNoErrors();

    expect(WebhookEndpoint::find($endpoint->id))->toBeNull()
        ->and(WebhookDelivery::where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('dispatches api_key.created to subscribed endpoints with a valid signature', function () {
    [$tenant] = webhookContext('wh-flow');
    $endpoint = makeEndpoint($tenant->id);

    Queue::fake();
    Http::fake(['*' => Http::response([], 200)]);

    app(GenerateApiKey::class)->execute('ERP key', ['identity:read']);

    Queue::assertPushed(DispatchTenantWebhookJob::class, function (DispatchTenantWebhookJob $job) use ($tenant) {
        return $job->tenantId === $tenant->id;
    });

    // Run the job synchronously: signed POST + delivered status.
    $delivery = WebhookDelivery::where('tenant_id', $tenant->id)->firstOrFail();
    (new DispatchTenantWebhookJob($tenant->id, (string) $delivery->id))->handle();

    Http::assertSent(function ($request) use ($endpoint) {
        $body = $request->body();

        return $request->hasHeader('X-Signature-SHA256')
            && $request['type'] === 'api_key.created'
            && HmacSigner::verify($body, $endpoint->secret, $request->header('X-Signature-SHA256')[0]);
    });

    expect($delivery->fresh()->status)->toBe('delivered')
        ->and($delivery->fresh()->response_status)->toBe(200);
});

it('records failures and retries with backoff on gateway errors', function () {
    [$tenant] = webhookContext('wh-fail');
    makeEndpoint($tenant->id);

    Http::fake(['*' => Http::response([], 500)]);
    Queue::fake();

    // Unknown delivery: no-op, no throw.
    (new DispatchTenantWebhookJob($tenant->id, 'missing-delivery'))->handle();

    expect(app(DispatchTenantWebhook::class)->execute($tenant->id, 'api_key.created'))->toBe(1);

    $delivery = WebhookDelivery::where('tenant_id', $tenant->id)->firstOrFail();

    try {
        (new DispatchTenantWebhookJob($tenant->id, (string) $delivery->id))->handle();
        $this->fail('Expected delivery exception.');
    } catch (RuntimeException) {
        // Expected: recorded then rethrown for the queue retry.
    }

    expect($delivery->fresh()->status)->toBe('failed')
        ->and($delivery->fresh()->response_status)->toBe(500)
        ->and((new DispatchTenantWebhookJob($tenant->id, (string) $delivery->id))->tries)->toBe(5)
        ->and((new DispatchTenantWebhookJob($tenant->id, (string) $delivery->id))->backoff())->toBe([10, 60, 300, 900]);
});

it('rejects unknown events and isolates tenants', function () {
    [$tenantA] = webhookContext('wh-iso-a');
    makeEndpoint($tenantA->id);

    $tenantB = Tenant::create([
        'id' => Str::uuid()->toString(), 'slug' => 'wh-iso-b',
        'name' => 'Iso B', 'email' => 'wh-iso-b@test.com',
    ]);
    makeEndpoint($tenantB->id);

    expect(fn () => app(DispatchTenantWebhook::class)->execute($tenantA->id, 'nope.event'))
        ->toThrow(InvalidArgumentException::class);

    Queue::fake();

    expect(app(DispatchTenantWebhook::class)->execute($tenantA->id, 'api_key.created'))->toBe(1);

    expect(WebhookDelivery::where('tenant_id', $tenantA->id)->count())->toBe(1)
        ->and(WebhookDelivery::where('tenant_id', $tenantB->id)->count())->toBe(0);
});
