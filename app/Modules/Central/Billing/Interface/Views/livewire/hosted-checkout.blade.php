<div class="flex flex-col gap-6 py-12" x-data="smartFieldsCheckout()">
    <div>
        <flux:heading size="xl">{{ __('Pay with card') }}</flux:heading>
        <flux:subheading>
            @if ($plan)
            {{ $plan->name }} · {{ number_format($plan->price_monthly / 100, 2) }} {{ $plan->currency }}
            @endif
        </flux:subheading>
    </div>

    @if ($error)
    <flux:text color="red">{{ $error }}</flux:text>
    @endif

    <flux:card class="max-w-lg">
        <form @submit.prevent="tokenize()" class="flex flex-col gap-4">
            <div>
                <flux:label>{{ __('Cardholder name') }}</flux:label>
                <flux:input x-ref="cardHolder" placeholder="John Doe" required />
            </div>
            <div>
                <flux:label>{{ __('ID document number') }}</flux:label>
                <flux:input wire:model="payerDocument" placeholder="12345678" required />
            </div>
            <div>
                <flux:label>{{ __('Credit or debit card') }}</flux:label>
                <div wire:ignore id="card-field" class="mt-1 rounded-lg border border-zinc-300 p-3 h-11 dark:border-zinc-700 bg-white dark:bg-zinc-800"></div>
            </div>
            <flux:text class="text-xs" id="dlocal-error"></flux:text>
            <flux:button type="submit" variant="primary" x-bind:disabled="processing">
                <span x-show="!processing">{{ __('Pay now') }}</span>
                <span x-show="processing">{{ __('Processing…') }}</span>
            </flux:button>
        </form>
    </flux:card>

    <script>
        window.smartFieldsCheckout = function () {
            return {
                processing: false,
                instance: null,
                card: null,
                scriptUrl: @js($jsUrl),
                loadScript() {
                    if (typeof dlocal !== 'undefined') {
                        return Promise.resolve();
                    }
                    if (window.__dlocalJsUrl !== this.scriptUrl) {
                        window.__dlocalJsUrl = this.scriptUrl;
                        window.__dlocalJsPromise = null;
                    }
                    if (!window.__dlocalJsPromise) {
                        window.__dlocalJsPromise = new Promise((resolve, reject) => {
                            const s = document.createElement('script');
                            s.src = this.scriptUrl;
                            s.async = true;
                            s.onload = resolve;
                            s.onerror = () => reject(new Error('dlocal.js failed to load'));
                            document.head.appendChild(s);
                        }).catch((e) => {
                            window.__dlocalJsPromise = null;
                            throw e;
                        });
                    }
                    return window.__dlocalJsPromise;
                },
                async init() {
                    if (this.card) {
                        return;
                    }
                    try {
                        await this.loadScript();
                    } catch (_) {
                        document.getElementById('dlocal-error').textContent = @js(__('Payment library failed to load. Please retry.'));
                        return;
                    }
                    if (typeof dlocal === 'undefined') {
                        document.getElementById('dlocal-error').textContent = @js(__('Payment library failed to load. Please retry.'));
                        return;
                    }
                    this.instance = dlocal(@js($jsApiKey));
                    const fields = this.instance.fields({
                        locale: 'en',
                        country: @js($country),
                    });

                    this.card = fields.create('card', {
                        style: {
                            base: {
                                fontSize: '16px',
                                color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#18181b',
                                fontFamily: 'ui-sans-serif, system-ui, sans-serif'
                            }
                        }
                    });
                    this.card.mount(document.getElementById('card-field'));

                    this.card.addEventListener('change', function(event) {
                        const displayError = document.getElementById('dlocal-error');
                        if (event.error) {
                            displayError.textContent = event.error.message;
                        } else {
                            displayError.textContent = '';
                        }
                    });
                },
                async tokenize() {
                    if (!this.card) return;
                    this.processing = true;
                    try {
                        const cardHolderName = this.$refs.cardHolder?.value ?? '';
                        const result = await this.instance.createToken(this.card, {
                            name: cardHolderName
                        });

                        if (result.error) {
                            console.error('dlocal token error', result.error);
                            document.getElementById('dlocal-error').textContent = result.error.message;
                            return;
                        }
                        await this.$wire.call('charge', result.token);
                    } catch (e) {
                        console.error('dlocal tokenize failed', e);
                        const detail = e?.message ? ` (${e.message})` : '';
                        document.getElementById('dlocal-error').textContent = @js(__('An error occurred processing the card.')) + detail;
                    } finally {
                        this.processing = false;
                    }
                }
            };
        }
    </script>
</div>
