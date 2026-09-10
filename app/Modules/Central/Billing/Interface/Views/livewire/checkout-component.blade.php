<div x-data="{
        loading: @entangle('loading'),
        checkoutUrl: @entangle('checkoutUrl'),
        completed: @entangle('completed'),
        error: @entangle('error')
    }">
    <div x-show="error" class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800"
        role="alert">
        <span x-text="error"></span>
    </div>

    <div x-show="completed" class="text-center py-8">
        <div class="mb-4 inline-flex items-center justify-center w-12 h-12 bg-green-100 text-green-600 rounded-full">
            <flux:icon icon="check" size="sm" />
        </div>
        <flux:heading size="lg">{{ __('Payment Successful') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Your transaction has been processed.') }}</flux:text>
    </div>

    @if ($directEnabled)
        {{-- ── dLocal DIRECT flow: Smart Fields (tokenize + server-side charge) ── --}}
        <div x-show="!checkoutUrl && !completed" x-data="dlocalCard()" class="px-6 py-6 sm:px-8">
            <form id="payment-form" @submit.prevent="submit" novalidate>
                <div class="space-y-5">
                    <flux:field>
                        <flux:label>{{ __('Credit or Debit Card') }}</flux:label>
                        <div id="card-field-wrap"
                            class="mt-1 rounded-lg border border-zinc-300 bg-white px-3 py-3 transition focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/30">
                            <div id="card-field"></div>
                        </div>
                        <flux:description>{{ __('Visa, Mastercard and Amex test cards accepted in sandbox.') }}</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label for="card-holder">{{ __('Cardholder Name') }}</flux:label>
                        <input id="card-holder" type="text" x-model="cardHolder" placeholder="John Doe" required
                            autocomplete="cc-name"
                            class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-xs transition placeholder:text-zinc-400 focus:border-primary focus:ring-2 focus:ring-primary/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" />
                    </flux:field>

                    <div id="card-errors" class="min-h-5 text-sm text-red-600 dark:text-red-400" role="alert"></div>

                    <div>
                        <button type="submit" :disabled="processing || loading"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-8 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:ring-2 focus:ring-primary/40 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60">
                            <span x-show="!processing && !loading">{{ __('Pay Now') }}</span>
                            <span x-show="processing || loading">{{ __('Processing...') }}</span>
                        </button>
                    </div>
                </div>
            </form>

            <div class="mt-6 flex items-center justify-center gap-2 text-zinc-400">
                <flux:icon.lock-closed size="xs" />
                <span class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by dLocal') }}</span>
            </div>
        </div>

        <script>
            window.dlocalCard = function() {
                return {
                    cardHolder: '',
                    processing: false,
                    dlocal: null,
                    fields: null,
                    card: null,
                    init() {
                        const script = document.createElement('script');
                        script.src = '{{ $dlocalJsUrl }}';
                        script.onload = () => {
                            this.dlocal = window.dlocal('{{ $dlocalJsKey }}');
                            this.fields = this.dlocal.fields({ locale: 'es', country: '{{ $dlocalCountry }}' });
                            this.card = this.fields.create('card', {
                                style: {
                                    base: {
                                        fontSize: '16px',
                                        color: '#27272a',
                                        fontFamily: 'inherit',
                                        '::placeholder': { color: '#a1a1aa' },
                                    },
                                    invalid: { color: '#dc2626' },
                                },
                            });
                            this.card.mount(document.getElementById('card-field'));
                            this.card.addEventListener('change', (event) => {
                                const errors = document.getElementById('card-errors');
                                errors.textContent = event.error ? event.error.message : '';
                                document.getElementById('card-field-wrap')?.classList.toggle('border-red-500', !!event.error);
                            });
                        };
                        document.head.appendChild(script);
                    },
                    async submit() {
                        if (!this.card) return;
                        this.processing = true;
                        try {
                            const result = await this.dlocal.createToken(this.card, {
                                name: this.cardHolder,
                            });
                            if (result.token) {
                                @this.set('token', result.token);
                                @this.set('cardHolderName', this.cardHolder);
                                @this.call('initiateCheckout');
                            } else {
                                throw result;
                            }
                        } catch (error) {
                            const errors = document.getElementById('card-errors');
                            errors.textContent = "Error: " + (error?.error?.message || JSON.stringify(error));
                            console.error("Tokenization Error:", error);
                            this.processing = false;
                        }
                    },
                };
            };
        </script>
    @else
        {{-- ── Hosted redirect flow (Clave / PagueloFacil) ── --}}
        <div x-show="checkoutUrl && !completed" class="text-center py-16">
            <div class="flex flex-col items-center gap-4">
                <div class="w-12 h-12 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
                <flux:heading size="lg">{{ __('Redirecting to Secure Payment...') }}</flux:heading>
                <flux:text class="max-w-xs mx-auto">
                    {{ __('Please wait while we transfer you to PagueloFacil to complete your transaction.') }}
                </flux:text>

                <div class="mt-8">
                    <flux:button x-on:click="window.location.href = checkoutUrl" variant="primary">
                        {{ __('Click here if you are not redirected') }}
                    </flux:button>
                </div>

                <script>
                    document.addEventListener('livewire:initialized', () => {
                        @this.on('checkout-ready', (event) => {
                            setTimeout(() => {
                                window.location.href = event.url;
                            }, 1000);
                        });
                    });
                </script>
            </div>
        </div>

        <div x-show="!checkoutUrl && !completed" class="flex flex-col items-center py-12">
            <flux:button wire:click="initiateCheckout" variant="primary" class="px-8 py-3" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Pay Now') }}</span>
                <span wire:loading>{{ __('Initializing...') }}</span>
            </flux:button>

            <div class="mt-6 flex items-center gap-2 text-zinc-400">
                <flux:icon.lock-closed size="xs" />
                <span
                    class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by PagueLo Fácil / Clave') }}</span>
            </div>
        </div>
    @endif
</div>
