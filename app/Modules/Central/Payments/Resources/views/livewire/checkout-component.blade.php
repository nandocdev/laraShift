<div 
    x-data="{ 
        loading: false, 
        checkoutUrl: @entangle('checkoutUrl'),
        completed: @entangle('completed'),
        error: @entangle('error')
    }"
    x-init="
        $watch('checkoutUrl', value => {
            if (value) {
                // Wait for the container to be in the DOM
                $nextTick(() => {
                    import('{{ Vite::asset('resources/js/payments/clave-adapter.js') }}').then(module => {
                        const ClaveAdapter = module.default;
                        ClaveAdapter.mount({
                            checkoutUrl: value,
                            containerId: 'clave-checkout-container',
                            onClose: () => { checkoutUrl = null; }
                        });
                    });
                });
            }
        })
    "
>
    <div x-show="error" class="p-4 mb-4 text-sm text-red-700 bg-red-100 rounded-lg dark:bg-red-200 dark:text-red-800" role="alert">
        <span x-text="error"></span>
    </div>

    <div x-show="completed" class="text-center py-8">
        <div class="mb-4 inline-flex items-center justify-center w-12 h-12 bg-green-100 text-green-600 rounded-full">
            <flux:icon icon="check" size="sm" />
        </div>
        <flux:heading size="lg">{{ __('Payment Successful') }}</flux:heading>
        <flux:text class="mt-2">{{ __('Your transaction has been processed.') }}</flux:text>
    </div>

    <div x-show="checkoutUrl && !completed">
        <div id="clave-checkout-container" class="w-full min-h-[500px] border border-zinc-200 rounded-xl overflow-hidden bg-white">
            <!-- The iframe will be mounted here by JS -->
        </div>
    </div>

    <div x-show="!checkoutUrl && !completed" class="flex flex-col items-center py-12">
        <flux:button 
            wire:click="initiateCheckout" 
            variant="primary" 
            class="px-8 py-3"
            wire:loading.attr="disabled"
        >
            <span wire:loading.remove>{{ __('Pay Now') }}</span>
            <span wire:loading>{{ __('Initializing...') }}</span>
        </flux:button>
        
        <div class="mt-6 flex items-center gap-2 text-zinc-400">
            <flux:icon.lock-closed size="xs" />
            <span class="text-[10px] uppercase tracking-widest font-bold">{{ __('Secured by PagueLo Fácil / Clave') }}</span>
        </div>
    </div>
</div>
