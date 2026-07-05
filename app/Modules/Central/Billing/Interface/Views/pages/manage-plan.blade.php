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
            <div class="space-y-6">
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
                    <flux:heading size="lg">{{ __('Marketing & Quotas') }}</flux:heading>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <flux:input wire:model="quota_branches" type="number" :label="__('Branches')" required />
                        <flux:input wire:model="quota_staff" type="number" :label="__('Staff')" required />
                        <flux:input wire:model="quota_bookings" type="number" :label="__('Bookings')" required />
                    </div>

                    <flux:textarea wire:model="display_features" :label="__('Marketing Features (Text)')" :placeholder="__('Feature 1, Feature 2, ...')" description="Separated by commas. For landing page display." />
                </flux:card>
            </div>

            <div class="space-y-6">
                <flux:card class="space-y-6">
                    <flux:heading size="lg">{{ __('Functional Catalog') }}</flux:heading>
                    <flux:subheading>{{ __('Select features from the global catalog included in this plan base.') }}</flux:subheading>

                    <div class="space-y-2 max-h-[300px] overflow-y-auto p-2 border border-zinc-100 dark:border-zinc-800 rounded-lg">
                        @foreach($availableFeatures->groupBy('module') as $module => $features)
                            <div class="mb-4">
                                <div class="text-xs font-bold uppercase text-zinc-400 mb-2">{{ $module ?: __('General') }}</div>
                                @foreach($features as $f)
                                    <label class="flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded cursor-pointer transition-colors">
                                        <input type="checkbox" wire:model="selectedFeatures" value="{{ $f->id }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-600">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-medium">{{ $f->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $f->key }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </flux:card>

                <flux:card class="space-y-6">
                    <flux:heading size="lg">{{ __('Integration') }}</flux:heading>
                    <flux:input wire:model="stripe_id" :label="__('Stripe Price ID (Optional)')" placeholder="price_..." />
                </flux:card>
            </div>
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
                <flux:heading size="lg">{{ __('Retire Plan?') }}</flux:heading>
                <flux:subheading>{{ __('The plan will be hidden for new subscriptions. Existing tenants using this plan will remain unaffected and historical records will be preserved.') }}</flux:subheading>
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
