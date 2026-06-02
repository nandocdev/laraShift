<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Subscription Plans') }}</flux:heading>
            <flux:subheading>{{ __('Manage the commercial matrix and platform tiers.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.billing.plans.create')" variant="primary" icon="plus" wire:navigate>{{ __('New Plan') }}</flux:button>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Monthly') }}</flux:table.column>
                <flux:table.column>{{ __('Yearly') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell class="font-medium">
                            {{ $plan->name }}
                            <div class="text-xs text-neutral-500">{{ $plan->slug }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($plan->price_monthly / 100, 2) }} USD
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($plan->price_yearly / 100, 2) }} USD
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$plan->is_active ? 'success' : 'neutral'">
                                {{ $plan->is_active ? __('ACTIVE') : __('INACTIVE') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item :href="route('central.billing.plans.edit', $plan->id)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="eye">{{ __('View Features') }}</flux:menu.item>
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
