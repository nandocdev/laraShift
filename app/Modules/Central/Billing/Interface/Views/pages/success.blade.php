<x-layouts::app :title="__('Subscription Successful')">
    <div class="max-w-2xl mx-auto py-20 text-center">
        <div class="mb-6 inline-flex items-center justify-center w-20 h-20 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-full">
            <flux:icon icon="check" size="xl" variant="solid" />
        </div>
        
        <flux:heading size="xl" class="mb-4">{{ __('Thank you for your subscription!') }}</flux:heading>
        <flux:text class="text-lg mb-8">
            {{ __('Your account has been upgraded successfully. You now have access to all the features of your new plan.') }}
        </flux:text>

        <div class="flex justify-center gap-4">
            <flux:button :href="route('dashboard')" variant="primary" wire:navigate>
                {{ __('Go to Dashboard') }}
            </flux:button>
            <flux:button :href="route('tenant.billing.manage')" variant="ghost" wire:navigate>
                {{ __('View Billing Details') }}
            </flux:button>
        </div>
    </div>
</x-layouts::app>
