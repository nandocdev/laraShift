<div x-data="{ 
        loading: false, 
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
</div>