<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use App\Modules\Central\Billing\Actions\CreateCheckoutSessionAction;
use App\Modules\Central\Billing\Models\Plan;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class SelectPlan extends Component
{
    public function selectPlan(string $planId): void
    {
        try {
            $tenant = tenant();
            $action = app(CreateCheckoutSessionAction::class);
            $plan = \App\Modules\Central\Billing\Models\Plan::findOrFail($planId);
            
            // If they already have this plan and it's active, don't do anything
            if ($tenant->plan_id === $plan->slug) {
                $subscription = $tenant->subscription('default');
                if ($subscription && ($subscription->active() || $subscription->onGracePeriod())) {
                    $this->dispatch('toast', variant: 'warning', heading: __('Plan Selection'), text: __('You are already on this plan.'));
                    return;
                }
            }

            $this->dispatch('toast', text: __('Preparing secure checkout...'));
            $checkoutUrl = $action->execute($tenant, $planId);
            
            $this->redirect($checkoutUrl, navigate: false);
        } catch (\Exception $e) {
            \Log::error("SelectPlan Error: " . $e->getMessage());
            $this->addError('plan', $e->getMessage());
        }
    }

    public function render(): View
    {
        $tenant = tenant();
        $subscription = $tenant->subscription('default');
        $isCurrentPlanActive = $subscription && ($subscription->active() || $subscription->onGracePeriod());

        return view('billing::pages.select-plan', [
            'plans' => Plan::where('is_active', true)->withoutTrashed()->orderBy('price_monthly', 'asc')->get(),
            'currentPlanId' => $tenant->plan_id,
            'isCurrentPlanActive' => $isCurrentPlanActive,
        ]);
    }
}
