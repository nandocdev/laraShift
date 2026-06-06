<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')] // Using app layout for tenant context
class UpdatePaymentMethod extends Component
{
    public string $paymentMethod;

    #[On('paymentMethodUpdated')]
    public function updatePaymentMethod(string $paymentMethod): void
    {
        try {
            tenant()->updateDefaultPaymentMethod($paymentMethod);
            
            tenant()->update([
                'pm_type' => tenant()->card_brand,
                'pm_last_four' => tenant()->card_last_four,
            ]);

            session()->flash('status', __('Payment method updated successfully.'));
            $this->redirect(route('tenant.billing.manage'), navigate: true);
        } catch (\Exception $e) {
            $this->addError('payment_method', $e->getMessage());
        }
    }

    public function render(): View
    {
        $tenant = tenant();
        $gateway = $tenant->billing_gateway ?? config('cashier.driver', 'stripe');
        
        $intent = null;
        $stripeKey = config('cashier.key');

        if ($gateway === 'stripe' && $stripeKey && config('cashier.secret')) {
            try {
                $intent = $tenant->createSetupIntent();
            } catch (\Exception $e) {
                \Log::error("Stripe SetupIntent Error: " . $e->getMessage());
            }
        }

        return view('billing::pages.update-payment-method', [
            'intent' => $intent,
            'stripeKey' => $stripeKey,
            'gateway' => $gateway,
        ]);
    }
}
