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
    @elseif($gateway === 'dlocal')
        <flux:card>
            <div x-data="{
                apiKey: '{{ config('payments.dlocal.smart_fields') }}',
                locale: '{{ app()->getLocale() }}',
                dlocalInstance: null,
                fields: null,
                loading: false,
                error: null,
                cardholderName: '{{ tenant()->name }}',
                fieldErrors: {
                    card_number: '',
                    card_expiry: '',
                    card_cvv: ''
                },
                isFormValid: false,

                init() {
                    const checkDependencies = () => {
                        if (typeof dlocal === 'undefined') {
                            setTimeout(checkDependencies, 50);
                            return;
                        }
                        this.setupFields();
                    };
                    checkDependencies();
                },

                setupFields() {
                    console.log('dLocal: Setup fields initialized.');
                    try {
                        this.dlocalInstance = dlocal(this.apiKey);
                        this.fields = this.dlocalInstance.fields({
                            locale: this.locale,
                            fonts: [{ cssSrc: 'https://fonts.googleapis.com/css?family=Inter' }]
                        });

                        const style = {
                            base: {
                                fontSize: '14px',
                                lineHeight: '24px',
                                color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#ffffff' : '#000000',
                                '::placeholder': { color: '#a1a1aa' }
                            },
                            invalid: { color: '#ef4444' }
                        };

                        const cardNumber = this.fields.create('cardNumber', { style });
                        const cardExpiry = this.fields.create('cardExpiry', { style });
                        const cardCvv = this.fields.create('cardCvv', { style });

                        setTimeout(() => {
                            cardNumber.mount('#dlocal-card-number');
                            cardExpiry.mount('#dlocal-card-expiry');
                            cardCvv.mount('#dlocal-card-cvv');
                            console.log('dLocal: Fields mounted');
                        }, 100);

                        const validate = () => {
                            this.isFormValid = this.cardholderName.length > 2;
                        };

                        [cardNumber, cardExpiry, cardCvv].forEach(f => {
                            f.on('change', (e) => {
                                this.fieldErrors[e.fieldType] = e.error ? e.error.message : '';
                                validate();
                            });
                        });
                    } catch (err) {
                        console.error('dLocal Initialization Error:', err);
                        this.error = 'Failed to load payment fields. Please refresh.';
                    }
                },

                async submitToken() {
                    this.loading = true;
                    this.error = null;

                    try {
                        const result = await this.dlocalInstance.createToken(this.fields, {
                            name: this.cardholderName
                        });

                        if (result.error) {
                            this.error = result.error.message;
                            this.loading = false;
                            return;
                        }

                        Livewire.dispatch('paymentMethodUpdated', { 
                            paymentMethod: result.token,
                            lastFour: '****', 
                            brand: 'dLocal Card'
                        });
                    } catch (e) {
                        this.error = 'An unexpected error occurred';
                    } finally {
                        this.loading = false;
                    }
                }
            }" class="space-y-6">
                
                <div x-show="error" style="display: none;" class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                    <span x-text="error"></span>
                </div>

                <div class="space-y-4">
                    <div class="space-y-4 bg-zinc-50 dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <div class="space-y-1">
                            <flux:label>{{ __('Card Number') }}</flux:label>
                            <div wire:ignore id="dlocal-card-number" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                            <p x-show="fieldErrors.card_number" x-text="fieldErrors.card_number" class="text-xs text-red-500 mt-1"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <flux:label>{{ __('Expiry Date (MM/YY)') }}</flux:label>
                                <div wire:ignore id="dlocal-card-expiry" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                                <p x-show="fieldErrors.card_expiry" x-text="fieldErrors.card_expiry" class="text-xs text-red-500 mt-1"></p>
                            </div>

                            <div class="space-y-1">
                                <flux:label>{{ __('CVV') }}</flux:label>
                                <div wire:ignore id="dlocal-card-cvv" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                                <p x-show="fieldErrors.card_cvv" x-text="fieldErrors.card_cvv" class="text-xs text-red-500 mt-1"></p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <flux:label>{{ __('Cardholder Name') }}</flux:label>
                            <flux:input x-model="cardholderName" placeholder="{{ __('As shown on card') }}" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <flux:button :href="route('tenant.billing.manage')" variant="ghost" wire:navigate>
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button x-on:click="submitToken" variant="primary" x-bind:disabled="loading || !isFormValid">
                            <span x-show="!loading">{{ __('Update Card') }}</span>
                            <span x-show="loading" style="display: none;" class="flex items-center gap-2">
                                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                {{ __('Processing...') }}
                            </span>
                        </flux:button>
                    </div>

                    <div class="flex justify-center items-center gap-2 text-zinc-400 pt-4">
                        <flux:icon.lock-closed size="xs" />
                        <span class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by dLocal') }}</span>
                    </div>
                </div>

                <script src="{{ config('payments.dlocal.environment') === 'production' ? 'https://js.dlocal.com/v1/' : 'https://js-sandbox.dlocal.com/v1/' }}"></script>
            </div>
        </flux:card>
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
