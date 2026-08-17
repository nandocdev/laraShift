<div class="flex flex-col gap-8 pb-12">
    {{-- Header --}}
    <div class="flex flex-col gap-1">
        <flux:heading size="xl" level="1" class="font-bold tracking-tight text-zinc-900 dark:text-zinc-50">
            {{ __('Dashboard') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400">
            {{ __('Visión general de toda la plataforma') }}
        </flux:subheading>
    </div>

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

                {{-- Chart Area --}}
                <div class="relative w-full h-52 flex flex-col justify-between select-none">
                    {{-- Y-Axis Grid & Guidelines --}}
                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none text-[11px] font-mono text-zinc-400 dark:text-zinc-500">
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-right">5k</span>
                            <div class="flex-1 border-b border-dashed border-zinc-200 dark:border-zinc-800"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-right">4k</span>
                            <div class="flex-1 border-b border-dashed border-zinc-200 dark:border-zinc-800"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-right">3k</span>
                            <div class="flex-1 border-b border-dashed border-zinc-200 dark:border-zinc-800"></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-6 text-right">2k</span>
                            <div class="flex-1 border-b border-dashed border-zinc-200 dark:border-zinc-800"></div>
                        </div>
                    </div>

                    {{-- SVG Curve Graph --}}
                    <div class="relative w-full h-40 pl-9 pr-3 pt-1">
                        <svg viewBox="0 0 600 140" preserveAspectRatio="none" class="w-full h-full overflow-visible">
                            <defs>
                                <linearGradient id="centralActivityGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.2" />
                                    <stop offset="100%" stop-color="#4f46e5" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            {{-- Gradient Fill --}}
                            <path
                                d="M 0,120 C 40,115 60,95 90,85 C 140,70 160,50 190,38 C 240,20 260,10 290,6 C 340,3 360,12 390,16 C 440,25 460,50 490,75 C 540,100 570,115 600,125 L 600,140 L 0,140 Z"
                                fill="url(#centralActivityGrad)"
                            />
                            {{-- Line Path --}}
                            <path
                                d="M 0,120 C 40,115 60,95 90,85 C 140,70 160,50 190,38 C 240,20 260,10 290,6 C 340,3 360,12 390,16 C 440,25 460,50 490,75 C 540,100 570,115 600,125"
                                fill="none"
                                stroke="#4f46e5"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="dark:stroke-indigo-400"
                            />
                            {{-- Data markers --}}
                            <circle cx="0" cy="120" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                            <circle cx="90" cy="85" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                            <circle cx="190" cy="38" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                            <circle cx="290" cy="6" r="4.5" class="fill-indigo-600 dark:fill-indigo-400 stroke-white dark:stroke-zinc-900 stroke-2" />
                            <circle cx="390" cy="16" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                            <circle cx="490" cy="75" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                            <circle cx="600" cy="125" r="3.5" class="fill-white dark:fill-zinc-900 stroke-indigo-600 dark:stroke-indigo-400 stroke-2" />
                        </svg>
                    </div>

                    {{-- X-Axis Labels --}}
                    <div class="flex justify-between pl-9 pr-3 pt-2 border-t border-zinc-200 dark:border-zinc-800 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        <span>L</span>
                        <span>M</span>
                        <span>M</span>
                        <span>J</span>
                        <span>V</span>
                        <span>S</span>
                        <span>D</span>
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
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Uptime') }}</span>
                    <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->systemHealth['metrics']['uptime'] }}</span>
                </div>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-zinc-500 dark:text-zinc-400">{{ __('Latencia media') }}</span>
                    <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $this->systemHealth['metrics']['avg_latency'] }}</span>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Middle Section 2: Recent Activity & Alerts --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left: ACTIVIDAD RECIENTE --}}
        <flux:card class="p-6 flex flex-col justify-between">
            <div>
                <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 dark:text-zinc-400 mb-5">
                    {{ __('ACTIVIDAD RECIENTE') }}
                </flux:heading>

                <div class="space-y-4">
                    @foreach($this->recentActivities as $activity)
                        <div class="flex items-start gap-3 text-sm">
                            <span class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-500 mt-1.5 flex-shrink-0"></span>
                            <div class="flex flex-col">
                                <span class="font-semibold text-zinc-900 dark:text-zinc-100 leading-snug">{{ $activity['title'] }}</span>
                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $activity['detail'] }}</span>
                            </div>
                        </div>
                    @endforeach
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
                    @foreach($this->alerts as $alert)
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
                    @endforeach
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
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Usuarios') }}</flux:table.column>
                <flux:table.column>{{ __('Estado') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Acción') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->organizations as $org)
                    <flux:table.row :key="$org['id']">
                        <flux:table.cell class="font-medium">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $org['name'] }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $org['domain'] }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline" class="font-medium">{{ $org['plan'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $org['users_count'] }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($org['status'] === 'active')
                                <div class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                    <span>{{ $org['status_label'] }}</span>
                                </div>
                            @else
                                <div class="flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    <span>{{ $org['status_label'] }}</span>
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                                <flux:menu>
                                    @if(is_numeric($org['id']) || \Illuminate\Support\Str::isUuid((string) $org['id']))
                                        <flux:menu.item icon="pencil" :href="route('central.provisioning.edit', $org['id'])" wire:navigate>
                                            {{ __('Editar') }}
                                        </flux:menu.item>
                                        <flux:menu.item icon="command-line" :href="route('central.tenants.features.overrides', $org['id'])" wire:navigate>
                                            {{ __('Características') }}
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
                @endforeach
            </flux:table.rows>
        </flux:table>

        <div class="mt-5 pt-4 border-t border-zinc-200 dark:border-zinc-800 text-right">
            <flux:link :href="route('central.provisioning.index')" wire:navigate class="text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline inline-flex items-center gap-1">
                {{ __('Ver organizaciones') }} &rarr;
            </flux:link>
        </div>
    </flux:card>
</div>

