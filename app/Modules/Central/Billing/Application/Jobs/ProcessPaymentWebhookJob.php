<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Jobs;

use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentApproved;
use App\Modules\Central\Billing\Domain\Events\PaymentDeclined;
use App\Modules\Central\Billing\Domain\Models\Payment;
use App\Modules\Central\Billing\Domain\Models\PaymentGatewayEvent;
use App\Modules\Central\Billing\Domain\Models\PaymentWebhook;
use App\Modules\Central\Billing\Infrastructure\Gateways\ClaveGateway;
use App\Modules\Central\Billing\Infrastructure\Gateways\DlocalGateway;
use App\Modules\Platform\Contracts\Billing\BillingEventData;
use App\Modules\Platform\Contracts\Billing\WebhookProvider;
use App\Modules\Platform\Contracts\TenantAware;
use App\Modules\Platform\Events\PaymentWebhookReceived;
use App\Modules\Platform\Tenancy\Infrastructure\Jobs\Concerns\RehydratesTenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhookJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, RehydratesTenantContext, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $gateway,
        public string $rawPayload,
        public string $signature,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function handle(ClaveGateway $clave, DlocalGateway $dlocal): void
    {
        $gateway = match ($this->gateway) {
            'clave' => $clave,
            'dlocal' => $dlocal,
            default => null,
        };

        if (! $gateway instanceof WebhookProvider) {
            Log::warning('billing.webhook_unknown_gateway', ['gateway' => $this->gateway]);

            return;
        }

        // Defense in depth: verify again inside the job, under lock, so a
        // concurrently delivered duplicate cannot double-process.
        $lock = Cache::lock("billing:webhook:{$this->gateway}:".sha1($this->rawPayload), 30);

        if (! $lock->get()) {
            return;
        }

        try {
            $gateway->verifyOrFail($this->rawPayload, $this->signature);

            $payload = json_decode($this->rawPayload, true) ?? [];
            $event = $gateway->normalize($payload);

            $gatewayEvent = PaymentGatewayEvent::firstOrCreate(
                ['gateway' => $this->gateway, 'gateway_event_id' => $event->gatewayEventId ?: sha1($this->rawPayload)],
                ['event_type' => $event->type, 'payload' => $payload]
            );

            if (! $gatewayEvent->wasRecentlyCreated && $gatewayEvent->processed_at) {
                return; // Duplicate delivery: ack without reprocessing.
            }

            $this->assertTenantOwnership($event->displayId);

            $payment = $this->upsertPayment($event);

            PaymentWebhook::updateOrCreate(
                ['gateway' => $this->gateway, 'gateway_reference' => $event->gatewayEventId],
                [
                    'tenant_id' => $this->tenantId,
                    'display_id' => $event->displayId,
                    'status' => $event->type,
                    'amount_cents' => $event->amountCents,
                    'payload' => $payload,
                    'processed_at' => now(),
                ]
            );

            PaymentWebhookReceived::dispatch($this->gateway, $event->gatewayEventId, $payload);

            match ($event->type) {
                'payment.succeeded' => PaymentApproved::dispatch($payment, $event),
                'payment.failed' => PaymentDeclined::dispatch($payment, $event),
                default => null,
            };

            $gatewayEvent->update(['processed_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Fase 0, regla 1: the display_id row decides the owner. A payload
     * claiming another tenant's payment is rejected loudly, never processed.
     *
     * @throws \RuntimeException
     */
    private function assertTenantOwnership(?string $displayId): void
    {
        if (! $displayId) {
            return;
        }

        $owner = Payment::withoutGlobalScopes()->where('display_id', $displayId)->value('tenant_id');

        if ($owner && (string) $owner !== (string) $this->tenantId) {
            Log::warning('billing.webhook_tenant_mismatch', [
                'display_id' => $displayId,
                'payload_tenant' => $this->tenantId,
                'payment_tenant' => $owner,
            ]);

            throw new \RuntimeException('Webhook tenant does not own the payment.');
        }
    }

    private function upsertPayment(BillingEventData $event): Payment
    {
        $status = $event->type === 'payment.succeeded' ? PaymentStatus::Approved : PaymentStatus::Declined;

        $payment = Payment::withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId)
            ->where('display_id', $event->displayId)
            ->first();

        if ($payment) {
            // No regression from terminal states.
            if (! in_array($payment->status, [PaymentStatus::Approved, PaymentStatus::Refunded], true)) {
                $payment->update([
                    'status' => $status,
                    'gateway_reference' => $event->gatewayEventId ?: $payment->gateway_reference,
                ]);
            }

            return $payment;
        }

        return Payment::create([
            'tenant_id' => $this->tenantId,
            'slug' => 'pay_'.$event->displayId,
            'display_id' => $event->displayId,
            'amount_cents' => $event->amountCents ?? 0,
            'currency' => $event->currency ?? 'USD',
            'status' => $status,
            'gateway' => $this->gateway,
            'gateway_reference' => $event->gatewayEventId,
        ]);
    }
}
