<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\ChargeDirectAction;
use App\Modules\Central\Billing\Domain\Models\Subscription;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class UpdatePaymentMethod extends Component
{
    #[Locked]
    public string $displayId = '';

    public string $payerDocument = '';

    public ?string $error = null;

    public bool $processing = false;

    public function mount(): void
    {
        $this->displayId = 'upd_'.substr((string) tenant()->getId(), 0, 8).'_'.now()->format('YmdHis');
    }

    /**
     * Retry the outstanding subscription payment with a new card.
     * An approved charge reactivates via FulfillSubscription.
     */
    public function payWithNewCard(string $token, ChargeDirectAction $direct): void
    {
        if (strlen($token) < 8) {
            $this->error = __('Invalid card token. Please retry.');
            $this->addError('token', __('Invalid card token.'));

            return;
        }

        if (trim($this->payerDocument) === '') {
            $this->error = __('An ID document number is required for card payments.');
            $this->addError('payerDocument', __('An ID document number is required.'));

            return;
        }

        $subscription = $this->subscription();

        if (! $subscription) {
            $this->error = __('No subscription to regularize.');

            return;
        }

        [$amountCents, $currency] = $this->outstanding($subscription);

        // Never approve a regularization without a resolvable amount:
        // a 0 charge would reactivate the subscription for free.
        if ($amountCents <= 0) {
            $this->error = __('Could not determine the outstanding amount. Contact support.');

            return;
        }

        $this->processing = true;
        $this->error = null;

        try {
            $tenant = tenant();

            $ref = $direct->execute(new DirectPaymentData(
                tenantId: (string) $tenant->getId(),
                gateway: $tenant->getBillingGateway(),
                orderId: $this->displayId,
                amountCents: $amountCents,
                currency: $currency,
                method: PaymentMethodType::Card,
                paymentToken: $token,
                payerDocument: trim($this->payerDocument),
                subscriptionId: $subscription->id,
            ));

            if ($ref->status !== 'approved') {
                $this->error = __('The charge was declined. Try another card.');

                return;
            }

            $this->redirect(route('tenant.billing.success'), navigate: true);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('Payment failed. Please try again.');
        } finally {
            $this->processing = false;
        }
    }

    private function subscription(): ?Subscription
    {
        return Subscription::where('tenant_id', tenant()->getId())->latest()->first();
    }

    /**
     * @return array{0:int,1:string} [amountCents, currency]
     */
    private function outstanding(Subscription $subscription): array
    {
        if ($subscription->plan_id) {
            try {
                $plan = app(PlanManager::class)->findById($subscription->plan_id);

                return [$plan->price_monthly, $plan->currency];
            } catch (\Throwable) {
                // Fall through.
            }
        }

        return [0, 'USD'];
    }

    public function render(BillingManager $billing): View
    {
        $gateway = $billing->providerFor(tenant());

        if (! $gateway->supports(BillingCapability::DirectPayment, PaymentMethodType::Card)) {
            abort(404);
        }

        return view('billing::livewire.update-payment-method', [
            'subscription' => $this->subscription(),
            'jsApiKey' => config('dlocal.js_api_key'),
        ]);
    }
}
