<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Health Monitor') }}</flux:heading>
            <flux:subheading>{{ __('Qué está fallando, desde cuándo y a quién afecta.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" size="sm" icon="arrow-path" wire:click="refresh" wire:loading.attr="disabled" class="min-h-12">{{ __('Refresh') }}</flux:button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <flux:card class="p-6">
            <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-4">
                {{ __('Platform') }}
            </flux:heading>
            <div class="space-y-3.5">
                @foreach($this->platform['services'] as $service)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $service['name'] }}</span>
                        <div class="flex items-center gap-2">
                            @if($service['ok'])
                                <span class="size-2 rounded-full bg-emerald-500"></span>
                                <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Healthy') }}</span>
                            @else
                                <span class="size-2 rounded-full bg-rose-500"></span>
                                <span class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ __('Degraded') }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-2.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Queue size') }}</span>
                    <span class="font-mono font-semibold">{{ number_format($this->platform['queue_size']) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Failed jobs') }}</span>
                    <span class="font-mono font-semibold">{{ number_format($this->platform['failed_jobs']) }}</span>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-4">
                {{ __('Tenants') }}
            </flux:heading>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Healthy') }}</div>
                    <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($this->tenantHealth['healthy']) }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Warning') }}</div>
                    <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">{{ number_format($this->tenantHealth['warning']) }}</div>
                </div>
                <div>
                    <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500">{{ __('Critical') }}</div>
                    <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400">{{ number_format($this->tenantHealth['critical']) }}</div>
                </div>
            </div>
            <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-right">
                <flux:link :href="route('central.provisioning.index')" wire:navigate class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                    {{ __('View Affected Tenants') }} &rarr;
                </flux:link>
            </div>
        </flux:card>
    </div>

    <flux:card class="p-6">
        <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-5">
            {{ __('Incidents') }}
        </flux:heading>
        <div class="space-y-4">
            @forelse($this->incidents as $incident)
                <div class="flex items-start justify-between gap-4 text-sm">
                    <div class="flex items-start gap-3">
                        @if($incident['severity'] === 'critical')
                            <span class="size-2 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></span>
                        @else
                            <span class="size-2 rounded-full bg-amber-500 mt-1.5 flex-shrink-0"></span>
                        @endif
                        <div class="flex flex-col">
                            <span class="font-medium">{{ $incident['title'] }}</span>
                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $incident['detail'] }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($incident['link'])
                            <flux:button variant="ghost" size="sm" :href="$incident['link']" wire:navigate class="min-h-12">{{ __('View') }}</flux:button>
                        @endif
                        <flux:button variant="ghost" size="sm" wire:click="acknowledge('{{ $incident['id'] }}')" class="min-h-12">{{ __('Acknowledge') }}</flux:button>
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No active incidents. Everything is operational.') }}</p>
            @endforelse
        </div>
    </flux:card>
</div>
