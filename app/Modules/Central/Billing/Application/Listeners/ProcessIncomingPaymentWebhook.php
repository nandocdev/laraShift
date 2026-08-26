<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Listeners;

use App\Modules\Central\Billing\Application\Jobs\ProcessPaymentWebhookJob;
use App\Modules\Platform\Events\PaymentWebhookReceived;
use Illuminate\Support\Facades\Log;

class ProcessIncomingPaymentWebhook
{
    /**
     * Handle the integration event by queuing webhook processing.
     */
    public function handle(PaymentWebhookReceived $event): void
    {
        Log::info('Payment webhook received, dispatching to billing', [
            'context' => $event->context,
            'tenant_id' => $event->tenantId,
        ]);

        ProcessPaymentWebhookJob::dispatch(
            $event->tenantId ?? 'central',
            $event->rawPayload,
            $event->signature,
            $event->webhookSecret,
        );
    }
}
