<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Tests;

use App\Modules\Central\Payments\Enums\PaymentStatus;
use App\Modules\Central\Payments\Exceptions\WebhookVerificationException;
use App\Modules\Central\Payments\Models\Payment;
use App\Modules\Central\Payments\Services\PaymentVerifier;
use App\Modules\Shared\Events\PaymentCompleted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TenantTestCase;

final class PaymentVerifierTest extends TenantTestCase
{
    use RefreshDatabase;

    private PaymentVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = app(PaymentVerifier::class);
    }

    // ── Webhook verification ──────────────────────────────────────────────────

    public function test_throws_on_invalid_signature(): void
    {
        $this->expectException(WebhookVerificationException::class);

        $this->verifier->handleWebhook(
            rawPayload: '{"displayId":"INV-001"}',
            signature: 'bad-sig',
            webhookSecret: 'real-secret',
            tenantId: $this->tenantId,
        );
    }

    // ── Idempotency ───────────────────────────────────────────────────────────

    public function test_duplicate_webhook_does_not_create_second_record(): void
    {
        \Event::fake();

        $payload = $this->approvedPayload('INV-001', 'TX-001');
        $rawPayload = json_encode($payload);
        $secret = 'test-secret';
        $signature = hash_hmac('sha256', $rawPayload, $secret);

        Payment::factory()->create([
            'tenant_id' => $this->tenantId,
            'display_id' => 'INV-001',
            'status' => PaymentStatus::Pending->value,
        ]);

        // Process same webhook twice
        $this->verifier->handleWebhook($rawPayload, $signature, $secret, $this->tenantId);
        $this->verifier->handleWebhook($rawPayload, $signature, $secret, $this->tenantId);

        $this->assertDatabaseCount('payment_webhooks', 1);
    }

    // ── Cross-tenant isolation ────────────────────────────────────────────────

    public function test_webhook_does_not_reconcile_payment_from_another_tenant(): void
    {
        $payload = $this->approvedPayload('INV-001', 'TX-001');
        $rawPayload = json_encode($payload);
        $secret = 'test-secret';
        $signature = hash_hmac('sha256', $rawPayload, $secret);

        // Payment belongs to a DIFFERENT tenant
        Payment::factory()->create([
            'tenant_id' => 'other-tenant-id',
            'display_id' => 'INV-001',
            'status' => PaymentStatus::Pending->value,
        ]);

        $this->verifier->handleWebhook($rawPayload, $signature, $secret, $this->tenantId);

        // The other tenant's payment must remain untouched
        $this->assertDatabaseHas('payments', [
            'tenant_id' => 'other-tenant-id',
            'status' => PaymentStatus::Pending->value,
        ]);
    }

    // ── Terminal status guard ─────────────────────────────────────────────────

    public function test_approved_payment_is_not_overwritten_by_declined_webhook(): void
    {
        \Event::fake([PaymentCompleted::class]);

        Payment::factory()->create([
            'tenant_id' => $this->tenantId,
            'display_id' => 'INV-001',
            'status' => PaymentStatus::Approved->value, // already terminal
        ]);

        $payload = json_encode($this->declinedPayload('INV-001', 'TX-002'));
        $secret = 'test-secret';
        $signature = hash_hmac('sha256', $payload, $secret);

        $this->verifier->handleWebhook($payload, $signature, $secret, $this->tenantId);

        $this->assertDatabaseHas('payments', [
            'tenant_id' => $this->tenantId,
            'display_id' => 'INV-001',
            'status' => PaymentStatus::Approved->value,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function approvedPayload(string $displayId, string $txId): array
    {
        return [
            'displayId' => $displayId,
            'txId' => $txId,
            'approved' => true,
            'amount' => 99.99,
            'gatewayCode' => 'CLAVE',
        ];
    }

    private function declinedPayload(string $displayId, string $txId): array
    {
        return [
            'displayId' => $displayId,
            'txId' => $txId,
            'declined' => true,
            'amount' => 99.99,
            'gatewayCode' => 'CLAVE',
        ];
    }
}
