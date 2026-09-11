<div class="flex min-h-[50vh] flex-col items-center justify-center gap-4 py-12 text-center">
    <flux:heading size="xl">{{ __('Payment successful') }}</flux:heading>
    <flux:subheading>{{ __('Your subscription will be activated shortly. You can close this page.') }}</flux:subheading>
    <flux:button variant="primary" :href="route('tenant.billing.manage')" wire:navigate>{{ __('Back to billing') }}</flux:button>
</div>
