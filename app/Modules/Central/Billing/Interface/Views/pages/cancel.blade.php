<div class="flex min-h-[50vh] flex-col items-center justify-center gap-4 py-12 text-center">
    <flux:heading size="xl">{{ __('Payment cancelled') }}</flux:heading>
    <flux:subheading>{{ __('No charge was made. You can retry whenever you are ready.') }}</flux:subheading>
    <flux:button variant="primary" :href="route('tenant.billing.plans')" wire:navigate>{{ __('Back to plans') }}</flux:button>
</div>
