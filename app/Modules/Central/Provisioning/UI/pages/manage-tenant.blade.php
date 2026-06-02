<div class="flex flex-col gap-6 max-w-2xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.provisioning.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Edit Tenant') }}: {{ $tenant->name }}</flux:heading>
            <flux:subheading>{{ __('Manage account details, subscription plan and operational status.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:input 
                wire:model="name" 
                :label="__('Account Name')" 
                required 
            />

            <flux:input 
                wire:model="email" 
                :label="__('Owner Email')" 
                type="email"
                required 
            />

            <div class="grid grid-cols-2 gap-6">
                <flux:select wire:model="plan_id" :label="__('Subscription Plan')">
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="status" :label="__('Operational Status')">
                    <option value="provisioning">{{ __('Provisioning') }}</option>
                    <option value="active">{{ __('Active') }}</option>
                    <option value="suspended">{{ __('Suspended') }}</option>
                    <option value="archived">{{ __('Archived') }}</option>
                    <option value="failed">{{ __('Failed') }}</option>
                </flux:select>
            </div>

            <div class="space-y-4">
                <flux:checkbox 
                    wire:model="maintenance_mode" 
                    :label="__('Maintenance Mode')" 
                    description="{{ __('Access will be blocked with a 503 error.') }}"
                />

                <flux:checkbox 
                    wire:model="read_only" 
                    :label="__('Read Only Mode')" 
                    description="{{ __('Post/Put/Delete actions will be blocked for tenant users.') }}"
                />
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <flux:button :href="route('central.provisioning.index')" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <div class="mt-12">
        <livewire:tenant-support-bitacora :tenant="$tenant" />
    </div>
</div>
