<?php

declare(strict_types=1);

namespace App\Modules\Central\Billing\Interface\Livewire;

use App\Modules\Central\Billing\Application\Actions\InitiateCheckout;
use App\Modules\Central\Billing\Application\DTO\PaymentData;
use App\Modules\Central\Billing\Domain\Enums\PaymentStatus;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

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
 * dLocal uses a DIRECT flow with Smart Fields (the card is tokenized client
 * side and charged server-side, no hosted page). Other gateways fall back to
 * the hosted checkout redirect.
 *
 * On success: dispatches browser event 'payment-approved' with payment data.
 * On error:   exposes $error string for the view.
 */
final class CheckoutComponent extends Component
{
    // -------------------------------------------------------------------------
    // Props
    // -------------------------------------------------------------------------

    #[Locked]
    public float $amount = 0.0;

    #[Locked]
    public float $taxAmount = 0.0;

    #[Locked]
    public float $discount = 0.0;

    #[Locked]
    public string $displayId = '';

    #[Locked]
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

    /** dLocal Smart Fields token, set by the frontend before charging. */
    public ?string $token = null;

    /** Cardholder name collected for the Smart Fields token. */
    public string $cardHolderName = '';

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    public function mount(): void
    {
        // Email defaults to authenticated user
        if (empty($this->email) && auth()->check()) {
            $this->email = auth()->user()->email;
        }
    }

    // -------------------------------------------------------------------------
    // Actions
    // -------------------------------------------------------------------------

    public function initiateCheckout(InitiateCheckout $action): void
    {
        $this->loading = true;
        $this->error = null;

        try {
            $gateway = tenant('billing_gateway') ?? config('payments.default', 'clave');
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
                    token: $this->token,
                    payerName: $this->cardHolderName,
                ),
                tenantId: tenancy()->tenant->id,
                apiKey: (string) $apiKey,
            );

            // DIRECT flow: payment processed synchronously, no redirect URL
            if ($session->result !== null) {
                $this->handleDirectResult($session->result->status);

                return;
            }

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

    /**
     * Called by the JS adapter via Livewire.dispatch when the iframe posts
     * a payment result back to the parent window.
     */
    public function handlePaymentResult(string $status, string $displayId): void
    {
        if ($status === 'approved') {
            $this->completed = true;
            $this->dispatch('payment-approved', displayId: $displayId);
        } else {
            $this->error = __('payments.payment_declined');
            $this->dispatch('payment-declined', displayId: $displayId);
        }
    }

    public function gateway(): string
    {
        return (string) (tenant('billing_gateway') ?? config('payments.default', 'clave'));
    }

    public function render(): View
    {
        $gateway = $this->gateway();

        return view('payments::livewire.checkout-component', [
            'gateway' => $gateway,
            'directEnabled' => $gateway === 'dlocal',
            'dlocalLogin' => (string) config('dlocal.login'),
            'dlocalJsUrl' => config('dlocal.environment') === 'production'
                ? 'https://js.dlocal.com/'
                : 'https://js-sandbox.dlocal.com/',
        ]);
    }

    private function handleDirectResult(PaymentStatus $status): void
    {
        if ($status === PaymentStatus::Approved) {
            $this->completed = true;
            $this->dispatch('payment-approved', displayId: $this->displayId);

            return;
        }

        $this->error = __('payments.payment_declined');
        $this->dispatch('payment-declined', displayId: $this->displayId);
    }
}
