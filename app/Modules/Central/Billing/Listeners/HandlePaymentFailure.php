<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Listeners;

use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\PaymentFailed;
use App\Modules\Shared\Events\TenantSuspendedByDunning;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailure
{
    /**
     * Handle the payment failure event.
     * Centralizes Dunning logic for all providers.
     */
    public function handle(PaymentFailed $event): void
    {
        $tenant = Tenant::find($event->tenantId);

        if (! $tenant) {
            Log::error('Dunning: Tenant not found', ['tenant_id' => $event->tenantId]);

            return;
        }

        // Logic moved from StripeWebhookController for consistency
        $attemptCount = $event->attemptCount;

        // We'd ideally have amount/currency in the event or fetch it from invoice
        // For now, we rely on the event or log as generic

        if ($attemptCount < 3) {
            Log::info("Dunning: Payment attempt {$attemptCount} failed for tenant {$tenant->slug}");
            // Notification logic...
        } else {
            Log::alert("Dunning: Maximum attempts reached. Suspending tenant {$tenant->slug}");

            $tenant->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

            TenantSuspendedByDunning::dispatch($tenant->id, $event->invoiceId);

            activity('billing')
                ->performedOn($tenant)
                ->withProperties(['invoice_id' => $event->invoiceId, 'attempts' => $attemptCount])
                ->log('tenant_suspended_by_dunning');
        }
    }
}
