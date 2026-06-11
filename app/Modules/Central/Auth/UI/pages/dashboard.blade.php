<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Central Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Real-time overview of your SaaS ecosystem.') }}</flux:subheading>
    </div>

    {{-- Key Metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <flux:card class="flex flex-col gap-2">
            <flux:text size="sm" variant="subtle" class="flex items-center gap-2">
                <flux:icon name="users" variant="micro" />
                {{ __('Total Tenants') }}
            </flux:text>
            <flux:heading size="xl">{{ $tenantCount }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text size="sm" variant="subtle" class="flex items-center gap-2">
                <flux:icon name="credit-card" variant="micro" />
                {{ __('Active Subs') }}
            </flux:text>
            <flux:heading size="xl">{{ $activeSubscriptionsCount }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text size="sm" variant="subtle" class="flex items-center gap-2">
                <flux:icon name="banknotes" variant="micro" />
                {{ __('Revenue (30d)') }}
            </flux:text>
            <flux:heading size="xl">${{ number_format($totalRevenue, 2) }}</flux:heading>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:text size="sm" variant="subtle" class="flex items-center gap-2">
                <flux:icon name="cpu-chip" variant="micro" />
                {{ __('System Status') }}
            </flux:text>
            <div class="flex items-center gap-2">
                <flux:badge color="green" size="lg" inset="top bottom">{{ __('Healthy') }}</flux:badge>
            </div>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Recent Tenants --}}
        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Recent Tenants') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('central.provisioning.index')" wire:navigate>
                    {{ __('View All') }}
                </flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Plan') }}</flux:table.column>
                    <flux:table.column>{{ __('Created') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($recentTenants as $tenant)
                        <flux:table.row :key="$tenant->id">
                            <flux:table.cell class="font-medium">{{ $tenant->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" variant="subtle">{{ $tenant->plan_id ?? 'N/A' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">
                                {{ $tenant->created_at->diffForHumans() }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        {{-- Audit Trail --}}
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Platform Activity') }}</flux:heading>

            <div class="space-y-4">
                @foreach($recentActivities as $activity)
                    <div class="flex items-start gap-3 text-sm">
                        <div class="mt-1">
                            @if($activity->log_name === 'auth')
                                <flux:icon name="key" variant="micro" class="text-blue-500" />
                            @elseif($activity->log_name === 'provisioning')
                                <flux:icon name="server" variant="micro" class="text-green-500" />
                            @else
                                <flux:icon name="information-circle" variant="micro" class="text-zinc-400" />
                            @endif
                        </div>
                        <div class="flex-1">
                            <flux:text class="font-medium">{{ str($activity->description)->replace('_', ' ')->title() }}</flux:text>
                            <flux:text size="xs" variant="subtle">
                                {{ $activity->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </div>

    {{-- Quick Actions --}}
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Quick Actions') }}</flux:heading>
        <div class="flex flex-wrap gap-4">
            <flux:button :href="route('central.provisioning.create')" icon="plus" variant="primary" wire:navigate>
                {{ __('Provision New Tenant') }}
            </flux:button>
            <flux:button icon="envelope" variant="filled">{{ __('Send Broadcast') }}</flux:button>
            <flux:button icon="cog-6-tooth" :href="route('central.settings.branding')" wire:navigate>{{ __('System Settings') }}</flux:button>
        </div>
    </div>
</div>
