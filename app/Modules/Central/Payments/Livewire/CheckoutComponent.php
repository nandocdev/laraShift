<?php

declare(strict_types=1);

namespace App\Modules\Central\Payments\Livewire;

use Livewire\Component;
use App\Modules\Central\Payments\Actions\InitiateCheckoutAction;
use App\Modules\Central\Payments\Actions\ProcessDirectPaymentAction;
use App\Modules\Central\Payments\DTOs\PaymentData;
use App\Modules\Central\Payments\Exceptions\ClaveGatewayException;
use App\Modules\Central\Payments\Services\CheckoutSession;

/**
 * Checkout widget component.
 *
 * Usage:
 *   <livewire:payments.checkout
 *     :amount="149.99"
 *     :description="'Pro Plan - Annual'"
 *     :display-id="$invoice->id"
 *     :email="auth()->user()->email"
 *   />
 *
 * On success: dispatches browser event 'payment-approved' with payment data.
 * On error:   exposes $error string for the view.
 */
final class CheckoutComponent extends Component {
    // -------------------------------------------------------------------------
    // Props
    // -------------------------------------------------------------------------

    #[\Livewire\Attributes\Locked]
    public float $amount = 0.0;

    #[\Livewire\Attributes\Locked]
    public float $taxAmount = 0.0;

    #[\Livewire\Attributes\Locked]
    public float $discount = 0.0;

    #[\Livewire\Attributes\Locked]
    public string $displayId = '';

    #[\Livewire\Attributes\Locked]
    public array $customFieldValues = [];

    public string $description = '';
    public string $email = '';
    public string $lang = 'es';

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    public ?string $checkoutUrl = null;
    public ?string $error = null;
    public bool $loading = false;
    public bool $completed = false;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void {
        // Email defaults to authenticated user
        if (empty($this->email) && auth()->check()) {
            $this->email = auth()->user()->email;
        }
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    public function initiateCheckout(InitiateCheckoutAction $action): void {
        $this->loading = true;
        $this->error = null;

        try {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'dlocal');
            $apiKey = config("payments.{$gateway}.api_key") ?? config("payments.{$gateway}.login"); // dLocal uses login as ID

            $session = $action->execute(
                data: new PaymentData(
                    amount: $this->amount,
                    description: $this->description,
                    displayId: $this->displayId,
                    email: $this->email,
                    tenantId: (string) tenancy()->tenant->id,
                    taxAmount: $this->taxAmount,
                    discount: $this->discount,
                    lang: $this->lang,
                    customFieldValues: $this->customFieldValues,
                ),
                tenantId: tenancy()->tenant->id,
                apiKey: (string) $apiKey,
            );

            $this->checkoutUrl = $session->checkoutUrl;
            $this->dispatch('checkout-ready', url: $this->checkoutUrl);
            $this->dispatch('toast', text: __('Redirecting to secure gateway...'));
        } catch (\Exception $e) {
            $this->error = __('payments.checkout_error');
            $this->dispatch('toast', variant: 'danger', heading: __('Checkout Failed'), text: $e->getMessage());

            logger()->error('Checkout initiation failed', [
                'tenant_id' => tenancy()->tenant->id,
                'display_id' => $this->displayId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->loading = false;
        }
    }

    public function processSmartFieldsPayment(string $token, bool $saveCard, ProcessDirectPaymentAction $action): array {
        $this->loading = true;

        try {
            $data = new PaymentData(
                amount: $this->amount,
                description: $this->description,
                displayId: $this->displayId,
                email: $this->email,
                tenantId: (string) tenancy()->tenant->id,
                taxAmount: $this->taxAmount,
                discount: $this->discount,
                lang: $this->lang,
                customFieldValues: $this->customFieldValues,
            );

            return $action->execute($data, $token, $saveCard);
        } catch (\Exception $e) {
            logger()->error('Smart Fields payment failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        } finally {
            $this->loading = false;
        }
    }

    /**
     * Called by the JS adapter via Livewire.dispatch when the iframe posts
     * a payment result back to the parent window.
     */
    public function handlePaymentResult(string $status, string $displayId): void {
        if ($status === 'approved') {
            $this->completed = true;
            $this->dispatch('payment-approved', displayId: $displayId);
        } else {
            $this->error = __('payments.payment_declined');
            $this->dispatch('payment-declined', displayId: $displayId);
        }
    }

    public function render(): \Illuminate\View\View {
        $gateway = tenant('billing_gateway') ?? config('payments.default', 'dlocal');

        if ($gateway === 'dlocal' && !isset($this->customFieldValues['use_redirect'])) {
            return view('payments::livewire.dlocal-smart-fields');
        }

        return view('payments::livewire.checkout-component');
    }
}
