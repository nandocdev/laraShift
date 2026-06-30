<?php

declare(strict_types=1);

use App\Modules\Central\Auth\Models\CentralUser;
use App\Modules\Central\Payments\Livewire\GatewaySettings;
use App\Modules\Central\Payments\Livewire\WebhookLog;
use App\Modules\Central\Payments\Models\PaymentWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = CentralUser::create([
        'id' => Str::uuid()->toString(),
        'name' => 'Payments Admin',
        'email' => 'payments-admin@test.com',
        'password' => Hash::make('password'),
    ]);
});

// Helper to create webhook records bypassing timestamps issue
function createWebhook(array $overrides = []): void
{
    DB::table('payment_webhooks')->insert(array_merge([
        'id' => Str::uuid()->toString(),
        'tenant_id' => Str::uuid()->toString(),
        'gateway_reference' => 'ref_'.Str::random(10),
        'display_id' => 'disp_'.Str::random(8),
        'status' => 'approved',
        'amount' => 99.99,
        'gateway_code' => 'CLAVE',
        'authorization_code' => null,
        'error_code' => null,
        'error_message' => null,
        'raw_payload' => '{}',
        'created_at' => now(),
    ], $overrides));
}

// ─── GatewaySettings ─────────────────────────────────────────────

it('renders gateway settings page', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(GatewaySettings::class)
        ->assertStatus(200)
        ->assertSee(__('Payment Gateway Settings'));
});

it('shows current gateway configuration status', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(GatewaySettings::class)
        ->assertStatus(200)
        ->assertSee(__('Configuration Status'))
        ->assertSee(__('Environment'));
});

it('switches between gateways', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(GatewaySettings::class)
        ->set('gateway', 'dlocal')
        ->assertSet('gateway', 'dlocal');
});

it('redirects unauthenticated users', function () {
    $this->get(route('central.payments.gateway'))
        ->assertRedirect(route('central.login'));
});

// ─── WebhookLog ──────────────────────────────────────────────────

it('renders webhook log page', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(WebhookLog::class)
        ->assertStatus(200)
        ->assertSee(__('Webhook Log'));
});

it('shows empty state when no webhooks exist', function () {
    $this->actingAs($this->admin, 'central');

    Livewire::test(WebhookLog::class)
        ->assertStatus(200)
        ->assertSee(__('No webhooks found matching the filters.'));
});

it('displays existing webhook records', function () {
    createWebhook(['gateway_code' => 'CLAVE', 'amount' => 99.99]);

    $this->actingAs($this->admin, 'central');

    Livewire::test(WebhookLog::class)
        ->assertStatus(200)
        ->assertSee('CLAVE')
        ->assertSee('99.99');
});

it('filters by gateway', function () {
    createWebhook(['gateway_code' => 'CLAVE', 'amount' => 50.00, 'gateway_reference' => 'ref-clave-s']);
    createWebhook(['gateway_code' => 'DLOCAL', 'amount' => 25.00, 'gateway_reference' => 'ref-dlocal-s']);

    $this->actingAs($this->admin, 'central');

    Livewire::test(WebhookLog::class)
        ->set('filterGateway', 'CLAVE')
        ->assertSee('ref-clave-s')
        ->assertDontSee('ref-dlocal-s');
});

it('filters by status', function () {
    createWebhook(['status' => 'approved', 'gateway_reference' => 'ref-approve']);
    createWebhook(['status' => 'declined', 'gateway_reference' => 'ref-decline']);

    $this->actingAs($this->admin, 'central');

    Livewire::test(WebhookLog::class)
        ->set('filterStatus', 'approved')
        ->assertSee('ref-approve')
        ->assertDontSee('ref-decline');
});

it('shows payload detail modal', function () {
    $id = Str::uuid()->toString();
    createWebhook([
        'id' => $id,
        'raw_payload' => json_encode(['transaction' => 'test', 'amount' => 75]),
    ]);

    $this->actingAs($this->admin, 'central');

    $webhook = PaymentWebhook::withoutGlobalScopes()->find($id);

    Livewire::test(WebhookLog::class)
        ->call('showPayload', $id)
        ->assertSet('expandedPayload', $webhook->raw_payload);
});

it('redirects unauthenticated users for webhook log', function () {
    $this->get(route('central.payments.webhooks'))
        ->assertRedirect(route('central.login'));
});
