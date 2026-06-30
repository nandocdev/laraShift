<div class="flex flex-col gap-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.features.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $isEditing ? __('Edit Feature') : __('New Feature') }}</flux:heading>
            <flux:subheading>{{ __('Define a functional key and its metadata for the system.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input 
                wire:model="key" 
                :label="__('Technical Key')" 
                placeholder="crm.pipeline" 
                description="Use module.action format. Unique system-wide."
                required 
            />

            <flux:input 
                wire:model="name" 
                :label="__('Display Name')" 
                placeholder="Advanced Pipeline Management" 
                required 
            />

            <flux:input 
                wire:model="module" 
                :label="__('Module / Category')" 
                placeholder="CRM" 
            />

            <flux:textarea 
                wire:model="description" 
                :label="__('Description')" 
                placeholder="Allows users to manage multiple sales pipelines..." 
            />

            <flux:checkbox 
                wire:model="is_active" 
                :label="__('Feature is active and can be used by tenants')" 
            />

            <flux:separator text="{{ __('Targeting Rules') }}" />

            <flux:text class="text-sm text-zinc-500">{{ __('Optionally restrict this feature to specific tenant attributes. Leave empty to make it available to all tenants with this plan.') }}</flux:text>

            <div class="space-y-4">
                <flux:input
                    wire:model="regionInput"
                    :label="__('Regions')"
                    placeholder="e.g. LATAM, US, EU"
                    @keydown.enter.prevent="$wire.addRegion"
                >
                    <x-slot name="append">
                        <flux:button variant="ghost" wire:click="addRegion">{{ __('Add') }}</flux:button>
                    </x-slot>
                </flux:input>

                @if (! empty($targeting['regions']))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($targeting['regions'] as $region)
                            <flux:badge size="sm" inset="left">
                                {{ $region }}
                                <button wire:click="removeRegion('{{ $region }}')" class="ml-1 hover:text-red-500">&times;</button>
                            </flux:badge>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-3 gap-4">
                    <flux:input wire:model="targeting.staff_min" type="number" min="0" :label="__('Min Staff')" placeholder="1" />
                    <flux:input wire:model="targeting.staff_max" type="number" min="0" :label="__('Max Staff')" placeholder="50" />
                    <flux:input wire:model="targeting.min_tenancy_days" type="number" min="0" :label="__('Min Tenancy (days)')" placeholder="30" />
                </div>
            </div>

            <div class="flex justify-between items-center mt-4">
                @if($isEditing)
                    <flux:modal.trigger name="delete-feature">
                        <flux:button variant="danger" icon="trash">{{ __('Delete') }}</flux:button>
                    </flux:modal.trigger>
                @else
                    <div></div>
                @endif

                <div class="flex gap-2">
                    <flux:button :href="route('central.features.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Save Feature') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:card>

    <flux:modal name="delete-feature" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete Feature?') }}</flux:heading>
                <flux:subheading>{{ __('Warning: This might break existing plan assignments.') }}</flux:subheading>
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
