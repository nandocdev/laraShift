<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Central Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Overview of your SaaS platform.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card>
            <div class="flex flex-col gap-2">
                <flux:text size="sm" color="neutral">{{ __('Active Tenants') }}</flux:text>
                <flux:heading size="xl">{{ $tenantCount }}</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex flex-col gap-2">
                <flux:text size="sm" color="neutral">{{ __('Total Revenue') }}</flux:text>
                <flux:heading size="xl">$0.00</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="flex flex-col gap-2">
                <flux:text size="sm" color="neutral">{{ __('System Health') }}</flux:text>
                <flux:badge color="green" size="lg" inset="10">{{ __('Stable') }}</flux:badge>
            </div>
        </flux:card>
    </div>

    <div class="mt-8">
        <flux:heading size="lg" class="mb-4">{{ __('Quick Actions') }}</flux:heading>
        <div class="flex gap-4">
            <flux:button :href="route('central.provisioning.create')" icon="plus" wire:navigate>
                {{ __('Provision New Tenant') }}
            </flux:button>
            <flux:button icon="envelope">{{ __('Broadcast Message') }}</flux:button>
        </div>
    </div>
</div>
