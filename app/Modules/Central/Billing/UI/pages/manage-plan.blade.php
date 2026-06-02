<div class="flex flex-col gap-6">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.billing.plans')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $isEditing ? __('Edit Plan') : __('New Plan') }}</flux:heading>
            <flux:subheading>{{ __('Define commercial conditions and technical quotas for this tier.') }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:card class="space-y-6">
                <flux:heading size="lg">{{ __('General Information') }}</flux:heading>
                
                <flux:input wire:model="name" :label="__('Plan Name')" placeholder="Pro" required />
                
                <flux:input wire:model="slug" :label="__('Slug / Unique Key')" placeholder="pro" required />
                
                <div class="grid grid-cols-2 gap-4">
                    <flux:input wire:model="price_monthly" type="number" step="0.01" :label="__('Monthly Price (USD)')" required />
                    <flux:input wire:model="price_yearly" type="number" step="0.01" :label="__('Yearly Price (USD)')" required />
                </div>

                <flux:checkbox wire:model="is_active" :label="__('Plan is active and visible for new subscriptions')" />
            </flux:card>

            <flux:card class="space-y-6">
                <flux:heading size="lg">{{ __('Integration & Quotas') }}</flux:heading>
                
                <flux:input wire:model="stripe_id" :label="__('Stripe Price ID (Optional)')" placeholder="price_..." />

                <div class="grid grid-cols-3 gap-4">
                    <flux:input wire:model="quota_branches" type="number" :label="__('Branches')" required />
                    <flux:input wire:model="quota_staff" type="number" :label="__('Staff')" required />
                    <flux:input wire:model="quota_bookings" type="number" :label="__('Bookings')" required />
                </div>

                <flux:textarea wire:model="display_features" :label="__('Marketing Features')" :placeholder="__('Feature 1, Feature 2, ...')" description="Separated by commas" />
            </flux:card>
        </div>

        <div class="flex justify-between items-center">
            @if($isEditing)
                <flux:modal.trigger name="delete-plan">
                    <flux:button variant="danger" icon="trash">{{ __('Delete Plan') }}</flux:button>
                </flux:modal.trigger>
            @else
                <div></div>
            @endif

            <div class="flex gap-2">
                <flux:button :href="route('central.billing.plans')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Plan') }}</flux:button>
            </div>
        </div>
    </form>

    <flux:modal name="delete-plan" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Plan?') }}</flux:heading>
                <flux:subheading>{{ __('This action cannot be undone if no tenants are using it.') }}</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="delete" variant="danger">{{ __('Confirm Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
