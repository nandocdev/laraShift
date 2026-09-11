<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Subscriptions') }}</flux:heading>
        <flux:subheading>{{ __('All tenant subscriptions across gateways.') }}</flux:subheading>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Period end') }}</flux:table.column>
                <flux:table.column>{{ __('Failed') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($subscriptions as $subscription)
                    <flux:table.row :key="$subscription->id">
                        <flux:table.cell>{{ $tenants[$subscription->tenant_id]?->name ?? $subscription->tenant_id }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->gateway }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" variant="outline">{{ $subscription->status->value }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $subscription->current_period_end?->toDateString() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->failed_attempts }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('No subscriptions.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    </flux:card>
</div>
