<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')] // Using app layout for tenant context
class UpdatePaymentMethod extends Component {
    public string $paymentMethod;

    #[On('paymentMethodUpdated')]
    public function updatePaymentMethod(string $paymentMethod, ?string $lastFour = null, ?string $brand = null): void {
        try {
            $tenant = tenant();
            $gateway = $tenant->billing_gateway ?? config('payments.default', 'dlocal');

            if ($gateway === 'dlocal') {
                $tenant->update([
                    'stripe_id' => $paymentMethod, // We use stripe_id column generically for the customer vault ID / token
                    'pm_type' => $brand ?? 'card',
                    'pm_last_four' => $lastFour ?? '****',
                ]);
            } else {
                // Fallback to Cashier for Stripe
                $tenant->updateDefaultPaymentMethod($paymentMethod);
                $tenant->update([
                    'pm_type' => $tenant->card_brand,
                    'pm_last_four' => $tenant->card_last_four,
                ]);
            }

            session()->flash('status', __('Payment method updated successfully.'));
            $this->redirect(route('tenant.billing.manage'), navigate: true);
        } catch (\Exception $e) {
            \Log::error("Failed to update payment method: " . $e->getMessage());
            $this->addError('payment_method', __('Failed to update payment method. Please try again.'));
        }
    }

    public function render(): View {
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
