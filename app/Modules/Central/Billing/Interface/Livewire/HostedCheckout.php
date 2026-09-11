<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\ChargeDirectAction;
use App\Modules\Central\Billing\Application\Actions\SubscribeTenantAction;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\DirectPaymentData;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class HostedCheckout extends Component
{
    #[Locked]
    public string $planSlug = '';

    #[Locked]
    public string $displayId = '';

    public string $payerDocument = '';

    public ?string $error = null;

    public bool $processing = false;

    public function mount(string $plan, PlanManager $plans, BillingManager $billing): void
    {
        $planModel = $plans->find($plan);
        $gateway = $billing->providerFor(tenant());

        // Card-only flow: anything else goes back to plan selection.
        if (! $gateway->supports(BillingCapability::DirectPayment, PaymentMethodType::Card)) {
            $this->redirect(route('tenant.billing.plans'), navigate: true);

            return;
        }

        $this->planSlug = $planModel->slug;
        $this->displayId = 'web_'.substr((string) tenant()->getId(), 0, 8).'_'.now()->format('YmdHis');
    }

    public function charge(string $token, ChargeDirectAction $direct, SubscribeTenantAction $subscribe, PlanManager $plans): void
    {
        if (trim($this->payerDocument) === '') {
            $this->error = __('An ID document number is required for card payments.');
            $this->addError('payerDocument', __('An ID document number is required.'));

            return;
        }

        if (strlen($token) < 8) {
            $this->error = __('Invalid card token. Please retry.');
            $this->addError('token', __('Invalid card token.'));

            return;
        }

        $this->processing = true;
        $this->error = null;

        try {
            $tenant = tenant();
            $plan = $plans->find($this->planSlug);

            $ref = $direct->execute(new DirectPaymentData(
                tenantId: (string) $tenant->getId(),
                gateway: $tenant->getBillingGateway(),
                orderId: $this->displayId,
                amountCents: $plan->price_monthly,
                currency: $plan->currency,
                method: PaymentMethodType::Card,
                paymentToken: $token,
                payerDocument: trim($this->payerDocument),
            ));

            if ($ref->status !== 'approved') {
                $this->error = __('The charge was declined. Try another card.');

                return;
            }

            $subscribe->execute($tenant, $plan->slug, $ref->cardId);

            $this->redirect(route('tenant.billing.success'), navigate: true);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('Payment failed. Please try again.');
        } finally {
            $this->processing = false;
        }
    }

    public function render(PlanManager $plans): View
    {
        try {
            $plan = $plans->find($this->planSlug);
        } catch (ModelNotFoundException) {
            $plan = null;
        }

        return view('billing::livewire.hosted-checkout', [
            'plan' => $plan,
            'jsApiKey' => config('dlocal.js_api_key'),
            'country' => config('dlocal.country_default', 'PA'),
            // Per dLocal setup guide: production loads js.dlocal.com,
            // testing loads js-sandbox.dlocal.com. A sandbox key against
            // the production host is rejected with 403 on init-session.
            'jsUrl' => config('dlocal.environment') === 'production'
                ? 'https://js.dlocal.com/'
                : 'https://js-sandbox.dlocal.com/',
        ]);
    }
}
