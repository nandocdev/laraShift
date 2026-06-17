<div x-data="{
    apiKey: '{{ config('payments.dlocal.login') }}',
    locale: '{{ app()->getLocale() }}',
    dlocalInstance: null,
    fields: null,
    loading: false,
    completed: false,
    error: null,
    cardholderName: '',
    saveCard: false,
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

    async submitPayment() {
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

            const paymentResult = await this.$wire.processSmartFieldsPayment(result.token, this.saveCard);
            
            if (paymentResult.success) {
                this.completed = true;
                this.$wire.handlePaymentResult('approved', paymentResult.displayId);
            } else {
                this.error = paymentResult.message || 'Payment declined';
            }
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

    <div x-show="!completed" class="space-y-4">
        <flux:heading size="lg">{{ __('Card Information') }}</flux:heading>
        
        <div class="space-y-4 bg-zinc-50 dark:bg-zinc-900 p-6 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <!-- Card Number Container -->
            <div class="space-y-1">
                <flux:label>{{ __('Card Number') }}</flux:label>
                <div wire:ignore id="dlocal-card-number" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                <p x-show="fieldErrors.card_number" x-text="fieldErrors.card_number" class="text-xs text-red-500 mt-1"></p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <!-- Expiry Date Container -->
                <div class="space-y-1">
                    <flux:label>{{ __('Expiry Date (MM/YY)') }}</flux:label>
                    <div wire:ignore id="dlocal-card-expiry" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                    <p x-show="fieldErrors.card_expiry" x-text="fieldErrors.card_expiry" class="text-xs text-red-500 mt-1"></p>
                </div>

                <!-- CVV Container -->
                <div class="space-y-1">
                    <flux:label>{{ __('CVV') }}</flux:label>
                    <div wire:ignore id="dlocal-card-cvv" class="h-10 px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-700 rounded-md"></div>
                    <p x-show="fieldErrors.card_cvv" x-text="fieldErrors.card_cvv" class="text-xs text-red-500 mt-1"></p>
                </div>
            </div>

            <!-- Cardholder Name -->
            <div class="space-y-1">
                <flux:label>{{ __('Cardholder Name') }}</flux:label>
                <flux:input x-model="cardholderName" placeholder="{{ __('As shown on card') }}" />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:checkbox x-model="saveCard" label="{{ __('Save card for future payments') }}" />
        </div>

        <flux:button x-on:click="submitPayment" variant="primary" class="w-full py-3" x-bind:disabled="loading || !isFormValid">
            <span x-show="!loading">{{ __('Pay') }} {{ number_format($amount, 2) }} USD</span>
            <span x-show="loading" style="display: none;" class="flex items-center gap-2">
                <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                {{ __('Processing...') }}
            </span>
        </flux:button>

        <div class="flex justify-center items-center gap-2 text-zinc-400">
            <flux:icon.lock-closed size="xs" />
            <span class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by dLocal') }}</span>
        </div>
    </div>

    <div x-show="completed" style="display: none;" class="text-center py-8">
        <div class="mb-4 inline-flex items-center justify-center w-12 h-12 bg-green-100 text-green-600 rounded-full">
            <flux:icon icon="check" size="sm" />
        </div>
        <flux:heading size="lg">{{ __('Payment Successful') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Your transaction has been processed.') }}</flux:text>
    </div>

    <script src="https://js.dlocal.com/v1/"></script>
</div>
