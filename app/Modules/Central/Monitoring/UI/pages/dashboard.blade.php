<div class="flex flex-col gap-6 py-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Monitoring & Alerting') }}</flux:heading>
            <flux:subheading>{{ __('Platform health, critical alerts, and recent activity.') }}</flux:subheading>
        </div>
        <flux:button wire:click="checkAlerts" icon="arrow-path">{{ __('Check Alerts') }}</flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    @if (! empty($alerts))
        <div class="space-y-2">
            @foreach ($alerts as $alert)
                <flux:card class="border-l-4 {{ $alert['severity'] === 'critical' ? 'border-l-red-500' : 'border-l-amber-500' }}">
                    <div class="flex items-center justify-between">
                        <div>
                            <flux:badge size="sm" :color="$alert['severity'] === 'critical' ? 'red' : 'amber'">
                                {{ strtoupper($alert['severity']) }}
                            </flux:badge>
                            <span class="ml-2 text-sm font-medium">{{ $alert['message'] }}</span>
                        </div>
                        <flux:text class="text-xs text-zinc-400">{{ $alert['count'] }} affected</flux:text>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <flux:card>
            <flux:heading size="sm">{{ __('Health Checks (24h)') }}</flux:heading>
            <flux:text class="text-2xl font-bold">{{ number_format($totalChecks) }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Failed Checks') }}</flux:heading>
            <flux:text class="text-2xl font-bold {{ $failedChecks > 0 ? 'text-red-500' : 'text-emerald-500' }}">{{ $failedChecks }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Active Tenants') }}</flux:heading>
            <flux:text class="text-2xl font-bold">{{ \App\Modules\Central\Provisioning\Models\Tenant::where('status', 'active')->count() }}</flux:text>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('System Health') }}</flux:heading>
            <flux:text class="text-2xl font-bold {{ $failedChecks > 0 ? 'text-amber-500' : 'text-emerald-500' }}">
                {{ $failedChecks > 0 ? 'Degraded' : 'Healthy' }}
            </flux:text>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="sm">{{ __('Health Check Summary (24h)') }}</flux:heading>
        </div>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Check Type') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Count') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($healthSummary as $row)
                    <flux:table.row>
                        <flux:table.cell class="font-mono text-xs">{{ $row->check_type }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$row->status === 'pass' ? 'emerald' : 'red'">{{ $row->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $row->count }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-400">{{ __('No health checks recorded yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('Recent Activity') }}</flux:heading>
        <div class="max-h-64 overflow-y-auto space-y-1">
            @forelse($recentActivity as $event)
                <div class="flex items-center justify-between text-xs py-1 border-b last:border-0">
                    <span class="text-zinc-400 w-24">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                    <flux:badge size="xs" variant="outline" class="w-20">{{ $event->log_name }}</flux:badge>
                    <span class="flex-1 px-2">{{ $event->description }}</span>
                    <span class="text-zinc-400 w-24 text-right">{{ $event->causer?->name ?? 'System' }}</span>
                </div>
            @empty
                <flux:text class="text-zinc-400">{{ __('No activity recorded.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>
