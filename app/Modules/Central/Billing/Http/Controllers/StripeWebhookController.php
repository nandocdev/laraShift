<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Models\PaymentGatewayEvent;
use App\Modules\Central\Provisioning\Models\Tenant;
use App\Modules\Shared\Events\PaymentFailed;
use App\Modules\Shared\Events\PaymentSucceeded;
use App\Modules\Shared\Events\SubscriptionCreated;
use App\Modules\Shared\Events\SubscriptionUpdated;
use App\Modules\Shared\Events\TenantReactivatedAfterPayment;
use App\Modules\Shared\Events\TenantSuspendedByDunning;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierController
{
    /**
     * Handle a Stripe webhook.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $eventId = $payload['id'] ?? null;

        if ($eventId && PaymentGatewayEvent::where('gateway_event_id', $eventId)->exists()) {
            return new Response('Webhook Handled (Duplicate)', 200);
        }

        $event = PaymentGatewayEvent::create([
            'gateway_event_id' => $eventId,
            'gateway' => 'stripe',
            'event_type' => $payload['type'] ?? 'unknown',
            'payload' => $payload,
        ]);

        try {
            $response = parent::handleWebhook($request);
            
            $event->update(['processed_at' => now()]);
            
            return $response;
        } catch (\Exception $e) {
            $event->update(['error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $tenant = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            SubscriptionCreated::dispatch(
                $tenant->id,
                $payload['data']['object']['id'],
                $payload['data']['object']['plan']['id'] ?? 'unknown',
                'stripe'
            );
        }

        return parent::handleCustomerSubscriptionCreated($payload);
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $tenant = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            SubscriptionUpdated::dispatch(
                $tenant->id,
                $payload['data']['object']['id'],
                $payload['previous_attributes']['plan']['id'] ?? null,
                $payload['data']['object']['plan']['id'] ?? 'unknown'
            );
        }

        return parent::handleCustomerSubscriptionUpdated($payload);
    }

    /**
     * Handle invoice payment failed. (US-203 Dunning)
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $tenant = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            $attemptCount = $payload['data']['object']['attempt_count'] ?? 1;
            $amount = (int) $payload['data']['object']['amount_due'];
            $currency = $payload['data']['object']['currency'];
            
            $money = new \Money\Money($amount, new \Money\Currency($currency));
            $formattedAmount = \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($money);

            PaymentFailed::dispatch($tenant->id, $payload['data']['object']['id'], $attemptCount);

            if ($attemptCount < 3) {
                $tenant->notify(new \App\Modules\Central\Billing\Notifications\PaymentFailedNotification($attemptCount, $formattedAmount, $currency));
            } else {
                $tenant->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);
                
                TenantSuspendedByDunning::dispatch($tenant->id, $payload['data']['object']['id']);

                $tenant->notify(new \App\Modules\Central\Billing\Notifications\TenantSuspendedNotification($formattedAmount, $currency));

                activity('billing')
                    ->performedOn($tenant)
                    ->withProperties(['invoice_id' => $payload['data']['object']['id']])
                    ->log('tenant_suspended_by_dunning');
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice payment succeeded.
     */
    protected function handleInvoicePaymentSucceeded(array $payload): Response
    {
        $tenant = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            PaymentSucceeded::dispatch(
                $tenant->id,
                $payload['data']['object']['id'],
                (int) $payload['data']['object']['amount_paid'],
                $payload['data']['object']['currency']
            );

            if ($tenant->status === 'suspended') {
                $tenant->update([
                    'status' => 'active',
                    'suspended_at' => null,
                ]);
                
                TenantReactivatedAfterPayment::dispatch($tenant->id, $payload['data']['object']['id']);

                activity('billing')
                    ->performedOn($tenant)
                    ->log('tenant_reactivated_after_payment');
            }
        }

        return parent::handleInvoicePaymentSucceeded($payload);
    }

    protected function getUserByStripeId($stripeId)
    {
        return Tenant::where('stripe_id', $stripeId)->first();
    }
}
