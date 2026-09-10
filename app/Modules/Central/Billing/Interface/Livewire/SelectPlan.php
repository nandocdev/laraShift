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

    public string $displayId = '';

    public function mount(): void
    {
        $this->displayId = 'web_'.substr((string) tenant()->getId(), 0, 8).'_'.now()->format('YmdHis');
    }

    public function checkout(string $planSlug, CreateCheckoutSessionAction $checkouts, PlanManager $plans): void
    {
        $plan = $plans->find($planSlug);

        $session = $checkouts->execute(tenant(), new PlanRef(
            slug: $plan->slug,
            amountCents: $plan->price_monthly,
            currency: $plan->currency,
            gatewayIds: $plan->gatewayIds(),
        ), $this->displayId);

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
