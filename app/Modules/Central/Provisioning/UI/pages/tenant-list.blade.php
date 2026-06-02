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

    <flux:card>
        <flux:table :paginate="$tenants">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Domain') }}</flux:table.column>
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
                            <flux:badge size="sm">{{ strtoupper($tenant->plan_id) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $tenant->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="shield-check">{{ __('Impersonate') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
