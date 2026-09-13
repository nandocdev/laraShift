<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Subscriptions') }}</flux:heading>
            <flux:subheading>{{ __('All tenant subscriptions across gateways.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="create-subscription">
            <flux:button variant="primary" icon="plus">{{ __('Create Subscription') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="overflow-hidden">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Tenant name or slug…') }}" />
            <flux:select wire:model.live="statusFilter" :label="__('Status')">
                <option value="">{{ __('All') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="gatewayFilter" :label="__('Gateway')">
                <option value="">{{ __('All') }}</option>
                @foreach ($this->gateways as $gateway)
                    <option value="{{ $gateway }}">{{ $gateway }}</option>
                @endforeach
            </flux:select>
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <flux:table :paginate="$subscriptions">
            <flux:table.columns>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Renewal') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row wire:loading.flex wire:target="search,statusFilter,gatewayFilter,nextPage,previousPage,gotoPage">
                    <flux:table.cell colspan="6">
                        <div class="flex flex-col gap-2" aria-hidden="true">
                            <flux:skeleton class="h-10 w-full" />
                            <flux:skeleton class="h-10 w-full" />
                            <flux:skeleton class="h-10 w-full" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
                @forelse($subscriptions as $subscription)
                    <flux:table.row :key="$subscription->id">
                        <flux:table.cell>{{ $tenants[$subscription->tenant_id]?->name ?? $subscription->tenant_id }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->plan_id }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->gateway }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ $subscription->status->value }}</flux:badge>
                            @if($subscription->cancel_at_period_end)
                                <flux:badge size="sm" variant="warning">{{ __('Ends soon') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $subscription->current_period_end?->toDateString() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Subscription actions') }}" />
                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('central.billing.subscriptions.show', $subscription->id)" wire:navigate>{{ __('View') }}</flux:menu.item>
                                    <flux:menu.item icon="arrow-path" wire:click="reactivate('{{ $subscription->id }}')">{{ __('Reactivate') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="x-mark" wire:click="cancel('{{ $subscription->id }}')" wire:confirm="{{ __('Cancel at period end?') }}">{{ __('Cancel') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-sm text-zinc-500">{{ __('No subscriptions match the current filters.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $subscriptions->links() }}</div>
    </flux:card>

    <flux:modal name="create-subscription" class="min-w-[25rem]">
        <form wire:submit="create" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create Subscription') }}</flux:heading>
                <flux:subheading>{{ __('Activates the tenant on the selected plan.') }}</flux:subheading>
            </div>

            <flux:input wire:model="newTenantSlug" :label="__('Tenant slug')" placeholder="acme" required />
            <flux:error name="newTenantSlug" />

            <flux:select wire:model="newPlanSlug" :label="__('Plan')" placeholder="{{ __('Choose a plan…') }}">
                @foreach ($this->plans as $plan)
                    <option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</option>
                @endforeach
            </flux:select>
            <flux:error name="newPlanSlug" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
