<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Catalog\Application\Services\PlanManager;
use App\Modules\Platform\Contracts\Billing\BillingCapability;
use App\Modules\Platform\Contracts\Billing\BillingManager;
use App\Modules\Platform\Contracts\Billing\PaymentMethodType;
use App\Modules\Platform\Contracts\Billing\PlanRef;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SelectPlan extends Component
{
    use AuthorizesRequests;

    public ?string $error = null;

    public function checkout(string $planSlug, CreateCheckoutSessionAction $checkouts, PlanManager $plans): void
    {
        $this->error = null;

        try {
            $plan = $plans->find($planSlug);

            // One display_id per attempt: reusing a mount-time id across
            // plans would bind the new plan to the old payment row (amount
            // mismatch). Same plan + same second still collapses to one row
            // via the action idempotency, covering double-clicks.
            $displayId = 'web_'.substr((string) tenant()->getId(), 0, 8).'_'.$plan->slug.'_'.now()->format('YmdHis');

            $session = $checkouts->execute(tenant(), new PlanRef(
                slug: $plan->slug,
                amountCents: $plan->price_monthly,
                currency: $plan->currency,
                gatewayIds: $plan->gatewayIds(),
            ), $displayId);
        } catch (\Throwable $e) {
            report($e);
            $this->error = __('Could not start the checkout with the payment gateway. Please try again.');

            return;
        }

        $this->redirect($session->url, navigate: false);
    }

    public function render(PlanManager $plans, BillingManager $billing): View
    {
        $gateway = $billing->providerFor(tenant());

        return view('billing::livewire.select-plan', [
            'plans' => $plans->active(),
            'gatewayName' => $gateway->identifier(),
            'autoRenew' => $gateway->supports(BillingCapability::Subscriptions, PaymentMethodType::Card),
            'directCard' => $gateway->supports(BillingCapability::DirectPayment, PaymentMethodType::Card),
        ]);
    }
}
