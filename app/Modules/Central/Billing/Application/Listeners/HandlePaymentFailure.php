<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Application\Listeners;

use App\Modules\Central\Billing\Domain\Enums\SubscriptionStatus;
use App\Modules\Central\Billing\Domain\Events\PaymentDeclined;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Provisioning\Actions\SuspendTenantForNonPayment;
use Illuminate\Support\Facades\Log;

class HandlePaymentFailure
{
    private const MIT_MAX_ATTEMPTS = 3;

    public function __construct(private SuspendTenantForNonPayment $suspend) {}

    /**
     * Async gateway decline (webhook) for a subscription-linked payment.
     * MIT track only: link-based renewals never touch failed_attempts.
     */
    public function handle(PaymentDeclined $event): void
    {
        $payment = $event->payment;

        if (! $payment->subscription_id) {
            return;
        }

        $subscription = Subscription::find($payment->subscription_id);

        if (! $subscription || ! $subscription->pm_card_id) {
            return; // Not an MIT subscription: link track handles its own deadlines.
        }

        $attempts = $subscription->failed_attempts + 1;

        $subscription->update([
            'failed_attempts' => $attempts,
            'status' => SubscriptionStatus::PastDue,
        ]);

        Log::warning('billing.webhook_decline_counted', [
            'subscription_id' => $subscription->id,
            'attempt' => $attempts,
        ]);

        if ($attempts >= self::MIT_MAX_ATTEMPTS) {
            $this->suspend->execute((string) $subscription->tenant_id, 'mit_attempts_exhausted');
        }
    }
}
