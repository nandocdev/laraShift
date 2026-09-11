<div class="flex flex-col gap-6 py-12" x-data="updateCardForm()">
    <div>
        <flux:heading size="xl">{{ __('Update payment method') }}</flux:heading>
        <flux:subheading>{{ __('Pay the outstanding balance with a new card. An approved charge reactivates your subscription.') }}</flux:subheading>
    </div>

    @if ($error)
        <flux:text color="red">{{ $error }}</flux:text>
    @endif

    @if ($subscription)
        <flux:card class="max-w-lg">
            <div class="flex justify-between text-sm">
                <span class="text-zinc-500">{{ __('Status') }}</span>
                <flux:badge size="sm" variant="outline">{{ $subscription->status->value }}</flux:badge>
            </div>
            <div class="mt-2 flex justify-between text-sm">
                <span class="text-zinc-500">{{ __('Failed attempts') }}</span>
                <span>{{ $subscription->failed_attempts }}</span>
            </div>
        </flux:card>

        <flux:card class="max-w-lg">
            <form @submit.prevent="tokenize()" class="flex flex-col gap-4">
                <div>
                    <flux:label>{{ __('ID document number') }}</flux:label>
                    <flux:input wire:model="payerDocument" placeholder="12345678" required />
                </div>
                <div>
                    <label class="text-sm font-medium">{{ __('Card number') }}</label>
                    <div id="dlocal-card-number" class="mt-1 rounded-lg border border-zinc-300 p-3 dark:border-zinc-700"></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">{{ __('Expiry') }}</label>
                        <div id="dlocal-card-expiry" class="mt-1 rounded-lg border border-zinc-300 p-3 dark:border-zinc-700"></div>
                    </div>
                    <div>
                        <label class="text-sm font-medium">{{ __('CVC') }}</label>
                        <div id="dlocal-card-cvc" class="mt-1 rounded-lg border border-zinc-300 p-3 dark:border-zinc-700"></div>
                    </div>
                </div>
                <flux:text class="text-xs" id="dlocal-error"></flux:text>
                <flux:button type="submit" variant="primary" x-bind:disabled="processing">
                    <span x-show="!processing">{{ __('Retry payment') }}</span>
                    <span x-show="processing">{{ __('Processing…') }}</span>
                </flux:button>
            </form>
        </flux:card>
    @else
        <flux:text>{{ __('No subscription to regularize.') }}</flux:text>
    @endif

    <script src="https://js.dlocal.com/v2/"></script>
    <script>
        function updateCardForm() {
            return {
                processing: @entangle('processing'),
                fields: null,
                init() {
                    if (typeof dlocal === 'undefined') {
                        document.getElementById('dlocal-error').textContent =
                            '{{ __('Payment library failed to load. Please retry.') }}';
                        return;
                    }
                    const instance = dlocal('{{ $jsApiKey }}');
                    this.fields = instance.fields({
                        number: {selector: '#dlocal-card-number'},
                        expiration: {selector: '#dlocal-card-expiry'},
                        cvv: {selector: '#dlocal-card-cvc'},
                    });
                },
                async tokenize() {
                    if (!this.fields) return;
                    this.processing = true;
                    try {
                        const {token, error} = await this.fields.createToken();
                        if (error) {
                            document.getElementById('dlocal-error').textContent = error.message;
                            return;
                        }
                        await @this.call('payWithNewCard', token);
                    } finally {
                        this.processing = false;
                    }
                }
            };
        }
    </script>
</div>
