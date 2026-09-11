<div class="flex flex-col gap-8 pb-12">
    {{-- Header --}}
    <div class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
            <flux:heading size="xl" level="1" class="font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
                {{ __('Dashboard') }}
            </flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                {{ __('Visión general de toda la plataforma') }}
            </flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button variant="ghost" size="sm" :href="route('central.provisioning.index')" wire:navigate>
                {{ __('View Tenants') }}
            </flux:button>
            <flux:button variant="ghost" size="sm" :href="route('central.health')" target="_blank">
                {{ __('View Health') }}
            </flux:button>
            <flux:button variant="ghost" size="sm" :href="route('central.billing.subscriptions')" wire:navigate>
                {{ __('View Billing Issues') }}
            </flux:button>
        </div>
    </div>

    {{-- Tenant breakdown (spec: Tenants | Active | Suspended | Quarantined) --}}
    <flux:card class="p-5">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-center">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Tenants') }}</div>
                <div class="text-2xl font-bold font-mono text-zinc-900 dark:text-zinc-50">{{ number_format($this->stats['breakdown']['total']) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</div>
                <div class="text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ number_format($this->stats['breakdown']['active']) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Suspended') }}</div>
                <div class="text-2xl font-bold font-mono text-amber-600 dark:text-amber-400">{{ number_format($this->stats['breakdown']['suspended']) }}</div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Quarantined') }}</div>
                <div class="text-2xl font-bold font-mono text-rose-600 dark:text-rose-400">{{ number_format($this->stats['breakdown']['quarantined']) }}</div>
            </div>
        </div>
    </flux:card>

    {{-- Top Metric Cards (4 Grid) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- ORGANIZACIONES --}}
        <flux:card class="flex flex-col justify-between p-5 space-y-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('ORGANIZACIONES') }}
            </span>
            <div class="space-y-1">
                <div class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 font-mono">
                    {{ is_numeric($this->stats['organizations']['total']) ? number_format($this->stats['organizations']['total']) : $this->stats['organizations']['total'] }}
                </div>
                <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    {{ $this->stats['organizations']['growth'] }}
                </div>
            </div>
        </flux:card>

        {{-- USUARIOS --}}
        <flux:card class="flex flex-col justify-between p-5 space-y-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('USUARIOS') }}
            </span>
            <div class="space-y-1">
                <div class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 font-mono">
                    {{ is_numeric($this->stats['users']['total']) ? number_format($this->stats['users']['total']) : $this->stats['users']['total'] }}
                </div>
                <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                    {{ $this->stats['users']['growth'] }}
                </div>
            </div>
        </flux:card>

        {{-- ACTIVAS --}}
        <flux:card class="flex flex-col justify-between p-5 space-y-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('ACTIVAS') }}
            </span>
            <div class="space-y-1">
                <div class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 font-mono">
                    {{ is_numeric($this->stats['active']['total']) ? number_format($this->stats['active']['total']) : $this->stats['active']['total'] }}
                </div>
                <div class="text-xs font-medium text-zinc-500 dark:text-zinc-400">
                    {{ $this->stats['active']['percentage'] }}
                </div>
            </div>
        </flux:card>

        {{-- ALERTAS --}}
        <flux:card class="flex flex-col justify-between p-5 space-y-3">
            <span class="text-[11px] font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                {{ __('ALERTAS') }}
            </span>
            <div class="space-y-1">
                <div class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 font-mono">
                    {{ $this->stats['alerts']['total'] }}
                </div>
                <div class="text-xs font-medium text-amber-600 dark:text-amber-400 flex items-center gap-1">
                    <span>⚠ {{ $this->stats['alerts']['critical_label'] }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Middle Section 1: Platform Activity & System Health --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left (2 cols): ACTIVIDAD DE LA PLATAFORMA --}}
        <flux:card class="lg:col-span-2 flex flex-col justify-between p-6">
            <div>
                <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-6">
                    {{ __('ACTIVIDAD DE LA PLATAFORMA') }}
                </flux:heading>

                {{-- Chart Area: barras reales últimos 7 días --}}
                <div class="relative w-full flex flex-col gap-2 select-none">
                    @php($chartMax = max($this->activityChart['max'], 1))
                    <div class="flex items-end gap-2 h-40">
                        @foreach($this->activityChart['days'] as $day)
                            <div class="flex-1 flex flex-col items-center justify-end gap-1 h-full">
                                <span class="text-[11px] font-mono text-zinc-500 dark:text-zinc-400">{{ $day['value'] }}</span>
                                <div class="w-full rounded-t bg-indigo-500/80 dark:bg-indigo-400/80" style="height: {{ $chartMax > 0 ? max(round(($day['value'] / $chartMax) * 100), $day['value'] > 0 ? 4 : 0) : 0 }}%"></div>
                                <span class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">{{ $day['key'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-100 dark:border-zinc-800 text-xs text-zinc-500 dark:text-zinc-400 text-center">
                {{ $this->activityChart['subtitle'] }}
            </div>
        </flux:card>

        {{-- Right (1 col): SALUD DEL SISTEMA --}}
        <flux:card class="flex flex-col justify-between p-6">
            <div>
                <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-4">
                    {{ __('SALUD DEL SISTEMA') }}
                </flux:heading>

                <div class="space-y-3.5">
                    @foreach($this->systemHealth['services'] as $service)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-zinc-700 dark:text-zinc-300 font-medium">{{ $service['name'] }}</span>
                            <div class="flex items-center gap-2">
                                @if($service['status'] === 'operational')
                                    <span class="size-2 rounded-full bg-emerald-500"></span>
                                    <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ $service['status_label'] }}</span>
                                @else
                                    <span class="size-2 rounded-full bg-amber-500"></span>
                                    <span class="text-xs font-medium text-amber-600 dark:text-amber-400">{{ $service['status_label'] }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 space-y-2.5">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Queue size') }}</span>
                    <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($this->systemHealth['metrics']['queue_size']) }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Past due') }}</span>
                    <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ number_format($this->systemHealth['metrics']['past_due']) }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Middle Section 2: Recent Activity & Alerts --}}
    <div id="alertas" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: ACTIVIDAD RECIENTE --}}
        <flux:card class="p-6 flex flex-col justify-between">
            <div>
                <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-5">
                    {{ __('ACTIVIDAD RECIENTE') }}
                </flux:heading>

                <div class="space-y-4">
                    @forelse($this->recentActivities as $activity)
                        <div class="flex items-start gap-3 text-sm">
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 mt-1.5 flex-shrink-0"></span>
                            <div class="flex flex-col">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100 leading-snug">{{ $activity['title'] }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $activity['detail'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Sin actividad reciente.') }}</p>
                    @endforelse
                </div>
            </div>
        </flux:card>

        {{-- Right: ALERTAS --}}
        <flux:card class="p-6 flex flex-col justify-between">
            <div>
                <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-5">
                    {{ __('ALERTAS') }}
                </flux:heading>

                <div class="space-y-4">
                    @forelse($this->alerts as $alert)
                        <div class="flex items-start gap-3 text-sm">
                            @if($alert['type'] === 'critical')
                                <span class="size-2 rounded-full bg-red-500 mt-1.5 flex-shrink-0"></span>
                            @else
                                <span class="size-2 rounded-full bg-amber-500 mt-1.5 flex-shrink-0"></span>
                            @endif
                            <div class="flex flex-col">
                                <span class="font-medium text-zinc-900 dark:text-zinc-100 leading-snug">{{ $alert['title'] }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $alert['time'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Sin alertas. Todo operativo.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-right">
                <flux:link :href="route('central.support.broadcasts')" wire:navigate class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                    {{ __('Ver todas') }} &rarr;
                </flux:link>
            </div>
        </flux:card>
    </div>

    {{-- Bottom Section: ORGANIZACIONES (Table) --}}
    <flux:card class="p-6">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400">
                {{ __('ORGANIZACIONES') }}
            </flux:heading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Empresa') }}</flux:table.column>
                <flux:table.column>{{ __('Usuarios') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Acción') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->organizations as $org)
                    <flux:table.row :key="$org['id']">
                        <flux:table.cell class="font-medium">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $org['name'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $org['domain'] }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $org['users_count'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                                <span class="size-1.5 rounded-full bg-zinc-400"></span>
                                <span>{{ $org['status_label'] }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    @if(is_numeric($org['id']) || \Illuminate\Support\Str::isUuid((string) $org['id']))
                                        <flux:menu.item icon="pencil" :href="route('central.provisioning.edit', $org['id'])" wire:navigate>
                                            {{ __('Editar') }}
                                        </flux:menu.item>
                                    @else
                                        <flux:menu.item icon="eye" :href="route('central.provisioning.index')" wire:navigate>
                                            {{ __('Ver detalles') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-sm text-zinc-500">
                            {{ __('Sin organizaciones todavía.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-5 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-right">
            <flux:link :href="route('central.provisioning.index')" wire:navigate class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                {{ __('Ver organizaciones') }} &rarr;
            </flux:link>
        </div>
    </flux:card>
</div>

