<x-layouts::app :title="__('Subscription Cancelled')">
    <div class="max-w-2xl mx-auto py-20 text-center">
        <div class="mb-6 inline-flex items-center justify-center w-20 h-20 bg-zinc-100 dark:bg-zinc-800 text-zinc-500 rounded-full">
            <flux:icon icon="x-mark" size="xl" variant="solid" />
        </div>
        
        <flux:heading size="xl" class="mb-4">{{ __('Subscription Process Cancelled') }}</flux:heading>
        <flux:text class="text-lg mb-8">
            {{ __('No changes were made to your account. You can return to the billing section to try again whenever you are ready.') }}
        </flux:text>

        <div class="flex justify-center gap-4">
            <flux:button :href="route('tenant.billing.plans')" variant="primary" wire:navigate>
                {{ __('Try Again') }}
            </flux:button>
            <flux:button :href="route('tenant.billing.manage')" variant="ghost" wire:navigate>
                {{ __('Back to Billing') }}
            </flux:button>
        </div>
    </div>
</x-layouts::app>
