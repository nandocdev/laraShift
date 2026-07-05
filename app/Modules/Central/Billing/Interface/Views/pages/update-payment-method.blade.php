<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Update Payment Method') }}</flux:heading>
        @if($gateway === 'stripe')
            <flux:subheading>{{ __('Provide your new credit card details securely via Stripe.') }}</flux:subheading>
        @else
            <flux:subheading>{{ __('Manage your payment method for :gateway.', ['gateway' => ucfirst($gateway)]) }}</flux:subheading>
        @endif
    </div>

    @if($gateway === 'stripe' && $intent)
        <flux:card>
            <div id="payment-form" class="space-y-6">
                <div>
                    <flux:label>{{ __('Cardholder Name') }}</flux:label>
                    <flux:input id="card-holder-name" type="text" value="{{ tenant()->name }}" />
                </div>

                <!-- Stripe Elements Placeholder -->
                <div class="p-4 border border-zinc-200 dark:border-zinc-700 rounded-lg bg-zinc-50 dark:bg-zinc-800">
                    <div id="card-element"></div>
                </div>

                <div id="card-errors" class="text-red-500 text-sm mt-2" role="alert"></div>

                <div class="flex justify-end gap-2">
                    <flux:button :href="route('tenant.billing.manage')" variant="ghost" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button id="card-button" variant="primary" data-secret="{{ $intent->client_secret }}">
                        {{ __('Update Card') }}
                    </flux:button>
                </div>
            </div>
        </flux:card>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
            document.addEventListener('livewire:navigated', () => {
                const stripe = Stripe('{{ $stripeKey }}');
                const elements = stripe.elements();
                const cardElement = elements.create('card', {
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#32325d',
                        },
                    },
                });

                const cardElementContainer = document.getElementById('card-element');
                if (!cardElementContainer) return;

                cardElement.mount('#card-element');

                const cardHolderName = document.getElementById('card-holder-name');
                const cardButton = document.getElementById('card-button');
                const clientSecret = cardButton.dataset.secret;

                cardButton.addEventListener('click', async (e) => {
                    cardButton.disabled = true;
                    const { setupIntent, error } = await stripe.confirmCardSetup(
                        clientSecret, {
                            payment_method: {
                                card: cardElement,
                                billing_details: { name: cardHolderName.value }
                            }
                        }
                    );

                    if (error) {
                        const errorElement = document.getElementById('card-errors');
                        errorElement.textContent = error.message;
                        cardButton.disabled = false;
                    } else {
                        // Success, send payment method to Livewire
                        Livewire.dispatch('paymentMethodUpdated', { paymentMethod: setupIntent.payment_method });
                    }
                });
            });
        </script>
    @else
        <flux:card class="text-center py-12">
            <div class="flex flex-col items-center gap-4">
                <div class="p-4 bg-zinc-100 dark:bg-zinc-800 rounded-full">
                    <flux:icon.credit-card size="xl" class="text-zinc-400" />
                </div>
                <flux:heading>{{ __('Online Update Unavailable') }}</flux:heading>
                <flux:text class="max-w-sm">
                    {{ __('To update your payment information for :gateway, please contact our support team or manage it during your next checkout.', ['gateway' => ucfirst($gateway)]) }}
                </flux:text>
                <flux:button :href="route('tenant.billing.manage')" variant="primary" class="mt-4">
                    {{ __('Back to Billing') }}
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
