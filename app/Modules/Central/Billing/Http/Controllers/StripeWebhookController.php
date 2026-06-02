<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Http\Controllers;

use App\Modules\Central\Billing\Models\PaymentGatewayEvent;
use App\Modules\Central\Provisioning\Models\Tenant;
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

    /**
     * Handle invoice payment failed. (US-203 Dunning)
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $tenant = $this->getUserByStripeId($payload['data']['object']['customer']);

        if ($tenant) {
            $attemptCount = $payload['data']['object']['attempt_count'] ?? 1;
            $amount = number_format($payload['data']['object']['amount_due'] / 100, 2);
            $currency = $payload['data']['object']['currency'];
            
            if ($attemptCount < 3) {
                $tenant->notify(new \App\Modules\Central\Billing\Notifications\PaymentFailedNotification($attemptCount, $amount, $currency));
            } else {
                $tenant->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                ]);
                
                $tenant->notify(new \App\Modules\Central\Billing\Notifications\TenantSuspendedNotification($amount, $currency));

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

        if ($tenant && $tenant->status === 'suspended') {
            $tenant->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);
            
            activity('billing')
                ->performedOn($tenant)
                ->log('tenant_reactivated_after_payment');
        }

        return parent::handleInvoicePaymentSucceeded($payload);
    }

    protected function getUserByStripeId($stripeId)
    {
        return Tenant::where('stripe_id', $stripeId)->first();
    }
}
