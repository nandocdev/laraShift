<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Financial Reports') }}</flux:heading>
            <flux:subheading>{{ __('MRR, churn, and revenue metrics.') }}</flux:subheading>
        </div>
        <flux:select wire:model.live="period" class="w-44">
            <option value="this_month">{{ __('This Month') }}</option>
            <option value="last_month">{{ __('Last Month') }}</option>
            <option value="last_3_months">{{ __('Last 3 Months') }}</option>
            <option value="last_12_months">{{ __('Last 12 Months') }}</option>
        </flux:select>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" variant="subtle">{{ __('MRR') }}</flux:text>
            <flux:heading size="xl">${{ number_format($currentMrr, 2) }}</flux:heading>
            <flux:text size="xs" variant="subtle">{{ __('Monthly Recurring Revenue') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" variant="subtle">{{ __('ARR') }}</flux:text>
            <flux:heading size="xl">${{ number_format($arr, 2) }}</flux:heading>
            <flux:text size="xs" variant="subtle">{{ __('Annual Run Rate') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" variant="subtle">{{ __('Churn (30d)') }}</flux:text>
            <flux:heading size="xl">{{ number_format($churn30d, 1) }}%</flux:heading>
            <flux:text size="xs" variant="subtle">{{ __('30-day churn rate') }}</flux:text>
        </flux:card>

        <flux:card class="flex flex-col gap-1">
            <flux:text size="sm" variant="subtle">{{ __('Active Tenants') }}</flux:text>
            <flux:heading size="xl">{{ $totalTenants }}</flux:heading>
            <flux:text size="xs" variant="subtle">{{ __('New: :new | Churned: :churned', ['new' => $newTenants, 'churned' => $churnedTenants]) }}</flux:text>
        </flux:card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Monthly MRR Chart --}}
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Monthly MRR Trend') }}</flux:heading>
            <flux:separator />
            <div class="space-y-2">
                @forelse($monthlyBreakdown as $month)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-16 text-zinc-500 font-mono text-xs">{{ $month['month'] }}</span>
                        <div class="flex-1 h-5 bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            @php
                                $maxMrr = max(array_column($monthlyBreakdown, 'mrr'));
                                $pct = $maxMrr > 0 ? ($month['mrr'] / $maxMrr) * 100 : 0;
                            @endphp
                            <div class="h-full bg-zinc-900 dark:bg-zinc-100 rounded-full transition-all duration-500"
                                style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-24 text-right font-mono text-xs font-medium">${{ number_format($month['mrr'], 0) }}</span>
                        <div class="flex gap-2 text-xs text-zinc-400 w-20 justify-end">
                            <span title="{{ __('New') }}">+{{ $month['new_tenants'] }}</span>
                            <span title="{{ __('Churned') }}" class="text-red-400">-{{ $month['churned'] }}</span>
                        </div>
                    </div>
                @empty
                    <flux:text variant="subtle" class="py-4 text-center">{{ __('No monthly data available.') }}</flux:text>
                @endforelse
            </div>
        </flux:card>

        {{-- MRR by Plan --}}
        <flux:card class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('MRR by Plan') }}</flux:heading>
            <flux:separator />
            <div class="space-y-3">
                @forelse($mrrByPlan as $plan)
                    <div class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <flux:text class="font-medium">{{ $plan['plan'] }}</flux:text>
                            <flux:text size="xs" variant="subtle">({{ $plan['count'] }} {{ __('tenants') }})</flux:text>
                        </div>
                        <flux:text class="font-mono font-medium">${{ number_format($plan['mrr'], 2) }}</flux:text>
                    </div>
                @empty
                    <flux:text variant="subtle" class="py-4 text-center">{{ __('No plan data available.') }}</flux:text>
                @endforelse
            </div>
            <flux:separator />
            <div class="flex items-center justify-between text-sm">
                <flux:text class="font-semibold">{{ __('Total MRR') }}</flux:text>
                <flux:text class="font-mono font-semibold">${{ number_format($currentMrr, 2) }}</flux:text>
            </div>
        </flux:card>
    </div>

    {{-- Tenant Status Distribution --}}
    <flux:card class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Tenant Status Distribution') }}</flux:heading>
        <flux:separator />
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach(['active', 'trial', 'past_due', 'suspended', 'archived'] as $status)
                @php
                    $count = $statusCounts[$status] ?? 0;
                    $colors = ['active' => 'emerald', 'trial' => 'blue', 'past_due' => 'amber', 'suspended' => 'red', 'archived' => 'zinc'];
                @endphp
                <flux:card class="flex flex-col gap-1 items-center py-3">
                    <flux:badge size="sm" :color="$colors[$status] ?? 'zinc'">{{ str_replace('_', ' ', ucfirst($status)) }}</flux:badge>
                    <flux:heading size="lg">{{ $count }}</flux:heading>
                </flux:card>
            @endforeach
        </div>
    </flux:card>
</div>
