<?php

declare(strict_types=1);

namespace App\Modules\Platform\Integrations\Dlocal\Jobs;

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
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
            'central' => $this->dispatchToCentral($reference),
            'tenant' => $this->dispatchToTenant($reference),
            default => Log::error('ResolveDlocalWebhookJob: Unknown context', [
                'context' => $reference->context,
                'external_reference' => $this->externalReference,
            ]),
        };
    }

    private function dispatchToCentral(PaymentReference $reference): void
    {
        Log::info('ResolveDlocalWebhookJob: Dispatching to Central billing', [
            'external_reference' => $this->externalReference,
        ]);

        ProcessPaymentWebhookJob::dispatch(
            $reference->tenant_id ?? 'central',
            json_encode($this->rawPayload),
            '',
            '',
        );
    }

    private function dispatchToTenant(PaymentReference $reference): void
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

        ProcessPaymentWebhookJob::dispatch(
            $reference->tenant_id,
            json_encode($this->rawPayload),
            '',
            '',
        );
    }
}
