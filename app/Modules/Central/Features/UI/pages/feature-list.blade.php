<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Global Feature Catalog') }}</flux:heading>
            <flux:subheading>{{ __('Define functionalities that can be assigned to plans or overridden per tenant.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.features.create')" variant="primary" icon="plus" wire:navigate>
            {{ __('New Feature') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <flux:table :paginate="$features">
            <flux:table.columns>
                <flux:table.column>{{ __('Key') }}</flux:table.column>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Module') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($features as $feature)
                    <flux:table.row :key="$feature->id">
                        <flux:table.cell class="font-mono text-xs">
                            {{ $feature->key }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $feature->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ strtoupper($feature->module ?: 'General') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$feature->is_active ? 'success' : 'neutral'">
                                {{ $feature->is_active ? __('ACTIVE') : __('INACTIVE') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('central.features.edit', $feature->id)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="trash" disabled>{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
