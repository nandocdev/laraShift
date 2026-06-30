<div class="flex flex-col gap-8 py-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Platform Analytics') }}</flux:heading>
            <flux:subheading>{{ __('Real-time health metrics and historical trends.') }}</flux:subheading>
        </div>
        <flux:button icon="arrow-down-tray" wire:click="export" :loading="$exporting">
            {{ __('Export CSV') }}
        </flux:button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card class="flex flex-col gap-2">
            <flux:badge size="sm" variant="outline" class="w-fit">{{ __('Monthly Recurring Revenue') }}</flux:badge>
            <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                ${{ number_format($currentMrr, 2) }}
            </span>
            <flux:text class="text-xs text-zinc-400">{{ __('MRR') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:badge size="sm" variant="outline" class="w-fit">{{ __('Churn Rate (30d)') }}</flux:badge>
            <span class="text-2xl font-bold {{ $churn30d > 5 ? 'text-red-500' : 'text-zinc-900 dark:text-white' }}">
                {{ number_format($churn30d, 2) }}%
            </span>
            <flux:text class="text-xs text-zinc-400">{{ __('Last 30 days') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:badge size="sm" variant="outline" class="w-fit">{{ __('Total Tenants') }}</flux:badge>
            <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalTenants) }}</span>
            <div class="flex gap-2 text-xs text-zinc-400">
                <span class="text-emerald-500">{{ number_format($activeTenants) }} active</span>
                <span class="text-amber-500">{{ number_format($suspendedTenants) }} suspended</span>
                <span class="text-zinc-400">{{ number_format($archivedTenants) }} archived</span>
            </div>
        </flux:card>

        <flux:card class="flex flex-col gap-2">
            <flux:badge size="sm" variant="outline" class="w-fit">{{ __('Provisioning') }}</flux:badge>
            <span class="text-2xl font-bold {{ $failedProvisioning > 0 ? 'text-red-500' : 'text-emerald-500' }}">
                {{ number_format($failedProvisioning) }}
            </span>
            <flux:text class="text-xs text-zinc-400">{{ __('Failed provisions') }}</flux:text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <flux:card class="space-y-4">
            <flux:heading size="sm">{{ __('MRR by Plan') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Plan') }}</flux:table.column>
                    <flux:table.column>{{ __('Tenants') }}</flux:table.column>
                    <flux:table.column>{{ __('MRR') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse($mrrByPlan as $plan)
                        <flux:table.row>
                            <flux:table.cell class="font-medium">{{ $plan->plan }}</flux:table.cell>
                            <flux:table.cell>{{ $plan->count }}</flux:table.cell>
                            <flux:table.cell class="font-mono">${{ number_format($plan->mrr, 2) }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="3" class="text-center text-zinc-400">{{ __('No data.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="sm">{{ __('Monthly Breakdown (12 months)') }}</flux:heading>
            <div class="space-y-3 max-h-[400px] overflow-y-auto">
                @foreach($monthlyBreakdown as $month)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-mono text-xs text-zinc-500 w-16">{{ $month->month }}</span>
                        <span class="text-emerald-600 font-medium w-24 text-right">${{ number_format($month->mrr, 0) }}</span>
                        <span class="text-blue-500 text-xs w-16 text-right">+{{ $month->newTenants }}</span>
                        <span class="text-red-400 text-xs w-16 text-right">{{ $month->churned > 0 ? '-'.$month->churned : '0' }}</span>
                    </div>
                @endforeach
            </div>
            <div class="flex gap-4 text-xs text-zinc-400 pt-2 border-t">
                <span class="text-emerald-600">■ MRR</span>
                <span class="text-blue-500">■ New</span>
                <span class="text-red-400">■ Churned</span>
            </div>
        </flux:card>
    </div>
</div>
