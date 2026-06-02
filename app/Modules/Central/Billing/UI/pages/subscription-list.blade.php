<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Subscriptions') }}</flux:heading>
            <flux:subheading>{{ __('Monitor active subscriptions and billing status across the platform.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <flux:table :paginate="$tenants">
            <flux:table.columns>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Next Billing') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($tenants as $tenant)
                    @php $subscription = $tenant->subscription('default'); @endphp
                    <flux:table.row :key="$tenant->id">
                        <flux:table.cell class="font-medium">
                            {{ $tenant->name }}
                            <div class="text-xs text-neutral-500">{{ $tenant->email }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ strtoupper($tenant->plan_id) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($subscription?->active())
                                <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                            @elseif($subscription?->onGracePeriod())
                                <flux:badge size="sm" variant="warning">{{ __('GRACE PERIOD') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="danger">{{ __('INACTIVE') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $subscription?->nextPayment() ? $subscription->nextPayment()->date()->format('Y-m-d') : 'N/A' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="credit-card">{{ __('View Invoices') }}</flux:menu.item>
                                    <flux:menu.item icon="arrow-path">{{ __('Sync Status') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="x-circle">{{ __('Cancel Subscription') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
