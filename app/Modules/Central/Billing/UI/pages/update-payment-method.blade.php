<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Update Payment Method') }}</flux:heading>
        <flux:subheading>{{ __('Provide your new credit card details securely via Stripe.') }}</flux:subheading>
    </div>

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
</div>
