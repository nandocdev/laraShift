<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Tenants') }}</flux:heading>
            <flux:subheading>{{ __('Manage platform customers and their isolation.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.provisioning.create')" variant="primary" icon="plus" wire:navigate>
            {{ __('New Tenant') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <flux:table :paginate="$tenants">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Created At') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($tenants as $tenant)
                    <flux:table.row :key="$tenant->id">
                        <flux:table.cell class="font-medium">
                            {{ $tenant->name }}
                            <div class="text-xs text-neutral-500">{{ $tenant->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="'http://' . $tenant->domains->first()?->domain" target="_blank">
                                {{ $tenant->domains->first()?->domain ?? 'No domain' }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:badge size="sm" :variant="$tenant->status === 'active' ? 'success' : ($tenant->status === 'maintenance' ? 'warning' : 'neutral')">
                                    {{ strtoupper($tenant->status) }}
                                </flux:badge>
                                @if($tenant->read_only)
                                    <flux:badge size="sm" variant="neutral">{{ __('READ-ONLY') }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ strtoupper($tenant->plan_id) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $tenant->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="command-line" :href="route('central.tenants.features.overrides', $tenant->id)" wire:navigate>{{ __('Manage Features') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil" :href="route('central.provisioning.edit', $tenant->id)" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    
                                    <flux:modal.trigger name="impersonate-tenant">
                                        <flux:menu.item icon="shield-check" wire:click="selectTenant('{{ $tenant->id }}')">{{ __('Impersonate') }}</flux:menu.item>
                                    </flux:modal.trigger>

                                    <flux:menu.separator />
                                    <flux:modal.trigger name="delete-tenant">
                                        <flux:menu.item variant="danger" icon="trash" wire:click="selectTenant('{{ $tenant->id }}')">{{ __('Delete') }}</flux:menu.item>
                                    </flux:modal.trigger>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="impersonate-tenant" class="min-w-[25rem]">
        <form wire:submit="impersonate" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Impersonate Tenant') }}</flux:heading>
                <flux:subheading>{{ __('You are about to access the account of :name.', ['name' => $selectedTenant?->name]) }}</flux:subheading>
            </div>

            <flux:textarea 
                wire:model="impersonationReason" 
                :label="__('Reason for Access')" 
                placeholder="{{ __('e.g. Investigating reported bug in invoicing module...') }}" 
                description="{{ __('Min. 20 characters. This action is audited.') }}"
                required 
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Start Session') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-tenant" class="min-w-[25rem]">
        <form wire:submit="delete" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Hard Delete Tenant') }}</flux:heading>
                <flux:subheading>{{ __('Warning: This will permanently purge all data, database schema and files for :name.', ['name' => $selectedTenant?->name]) }}</flux:subheading>
            </div>

            <flux:input 
                wire:model="confirmSlug" 
                :label="__('Type the tenant slug to confirm')" 
                :placeholder="$selectedTenant?->slug"
                required 
            />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Confirm Purge') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
