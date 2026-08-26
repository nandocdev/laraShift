<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Jobs;

use App\Modules\Platform\Events\PaymentWebhookReceived;
use App\Modules\Platform\Integrations\Dlocal\Models\PaymentReference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResolveDlocalWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $externalReference,
        public readonly array $rawPayload,
        public readonly string $signature = '',
        public readonly string $webhookSecret = '',
    ) {}

    public function handle(): void
    {
        $reference = PaymentReference::where('external_reference', $this->externalReference)->first();

        if (! $reference) {
            Log::warning('ResolveDlocalWebhookJob: No payment reference found', [
                'external_reference' => $this->externalReference,
            ]);

            return;
        }

        match ($reference->context) {
            'central' => $this->dispatchResolved($reference, null),
            'tenant' => $this->dispatchTenant($reference),
            default => Log::error('ResolveDlocalWebhookJob: Unknown context', [
                'context' => $reference->context,
                'external_reference' => $this->externalReference,
            ]),
        };
    }

    private function dispatchTenant(PaymentReference $reference): void
    {
        if (! $reference->tenant_id) {
            Log::error('ResolveDlocalWebhookJob: Tenant context missing tenant_id', [
                'external_reference' => $this->externalReference,
            ]);

            return;
        }

        Log::info('ResolveDlocalWebhookJob: Dispatching to Tenant', [
            'tenant_id' => $reference->tenant_id,
            'external_reference' => $this->externalReference,
        ]);

        $this->dispatchResolved($reference, $reference->tenant_id);
    }

    private function dispatchResolved(PaymentReference $reference, ?string $tenantId): void
    {
        PaymentWebhookReceived::dispatch(
            $reference->context,
            $tenantId,
            json_encode($this->rawPayload),
            $this->signature,
            $this->webhookSecret,
        );
    }
}
