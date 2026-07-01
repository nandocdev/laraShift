{{--
================================================================================
DESIGN SYSTEM — SaaS Multitenant
Stack: Laravel Blade + FluxUI + Livewire + Tailwind CSS
Vibe: Minimalism — Inspired by Plausible | Mode: Light

DESIGN VIBE: MINIMALISM
  Strictly prioritize negative space.
  Remove 50% of intended borders.
  Heavy whitespace between elements.
  No gradients. No shadows > 4px.
  Typography hierarchy defines structure — not containers.

TOKENS:
  Background:  #FFFFFF
  Primary:     #111111
  Secondary:   #F5F5F7
  Text:        #111111
  Border:      #E5E5E5  (1px solid)
  Radius:      6px  (rounded-md)
  Shadow:      0px 2px 4px rgba(0,0,0,0.05)  → shadow-[0_2px_4px_rgba(0,0,0,0.05)]
  Font:        Inter — light weights, tracking-tight for headings

TAILWIND MAPPING:
  bg-white          → Background surfaces
  bg-[#F5F5F7]      → Secondary / muted surfaces
  text-[#111111]    → All primary text
  border-[#E5E5E5]  → All borders (use sparingly)
  rounded-md        → 6px radius everywhere
  shadow-[0_2px_4px_rgba(0,0,0,0.05)] → Only where elevation is essential

CRITICAL RULES:
  1. Negative space first — increase padding before adding visual elements
  2. Borders only where structurally necessary — headings/sections need NO border
  3. Shadow only on interactive cards and modals
  4. Typography weight (font-light / font-normal / font-medium / font-semibold)
     replaces color blocks and containers for hierarchy
  5. tracking-tight on all headings
================================================================================
--}}


{{-- ============================================================
     GLOBAL — app.blade.php (base HTML, carga fonts + Flux)
     ============================================================ --}}
{{-- resources/views/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @fluxStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans antialiased text-slate-900">
    {{ $slot }}
    @fluxScripts
    @livewireScripts
</body>
</html>


{{-- ============================================================
     1. x-layout.host
     Shell del backoffice: sidebar fijo + topbar + breadcrumbs
     Uso: <x-layout.host :breadcrumbs="[['label'=>'Billing','url'=>route('host.billing.index')],['label'=>'Planes']]">
     ============================================================ --}}
{{-- resources/views/components/layout/host.blade.php --}}
@props([
    'breadcrumbs' => [],
    'title'       => null,
])

<x-app>
    <div class="flex h-full">

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white">

            {{-- Logo / wordmark --}}
            <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-6">
                <span class="text-sm font-700 tracking-tight text-slate-900">
                    {{ config('app.name') }}
                    <span class="ml-1.5 rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-500 text-slate-500 uppercase tracking-wide">
                        Host
                    </span>
                </span>
            </div>

            {{-- Nav --}}
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">

                {{-- Group: Principal --}}
                <p class="mb-1 px-3 text-[10px] font-600 uppercase tracking-widest text-slate-400">Principal</p>
                <x-host-nav-item route="host.dashboard"     icon="squares-2x2"   label="Dashboard" />
                <x-host-nav-item route="host.tenants.index" icon="building-office" label="Tenants" />
                <x-host-nav-item route="host.provisioning"  icon="server"         label="Provisioning" />

                {{-- Group: Facturación --}}
                <p class="mb-1 mt-4 px-3 text-[10px] font-600 uppercase tracking-widest text-slate-400">Facturación</p>
                <x-host-nav-item route="host.billing.plans.index"         icon="rectangle-stack"  label="Planes" />
                <x-host-nav-item route="host.billing.subscriptions.index" icon="credit-card"       label="Suscripciones" />
                <x-host-nav-item route="host.payments.index"              icon="banknotes"         label="Pagos" />
                <x-host-nav-item route="host.billing.reports"             icon="chart-bar"         label="Reportes" />

                {{-- Group: Plataforma --}}
                <p class="mb-1 mt-4 px-3 text-[10px] font-600 uppercase tracking-widest text-slate-400">Plataforma</p>
                <x-host-nav-item route="host.features.index"   icon="flag"            label="Feature Flags" />
                <x-host-nav-item route="host.support.index"    icon="chat-bubble-left" label="Soporte" />
                <x-host-nav-item route="host.analytics"        icon="presentation-chart-line" label="Analytics" />

                {{-- Group: Operaciones --}}
                <p class="mb-1 mt-4 px-3 text-[10px] font-600 uppercase tracking-widest text-slate-400">Operaciones</p>
                <x-host-nav-item route="host.security.audit"   icon="shield-check"    label="Seguridad" />
                <x-host-nav-item route="host.monitoring"       icon="signal"          label="Monitoring" />
                <x-host-nav-item route="host.settings.index"   icon="cog-6-tooth"     label="Configuración" />
            </nav>

            {{-- Footer del sidebar: usuario activo --}}
            <div class="border-t border-slate-200 p-3">
                <flux:dropdown>
                    <flux:button variant="ghost" class="w-full justify-start gap-3 px-3 py-2 text-sm">
                        <flux:avatar size="sm" name="{{ auth()->user()->name }}" />
                        <div class="min-w-0 text-left">
                            <p class="truncate text-xs font-500 text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[10px] text-slate-400">Super Admin</p>
                        </div>
                        <flux:icon name="chevron-up-down" class="ml-auto size-3.5 text-slate-400" />
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="user" href="{{ route('host.profile') }}">Mi perfil</flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item icon="arrow-right-on-rectangle" variant="danger"
                            x-on:click="$wire.dispatch('logout')">
                            Cerrar sesión
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </aside>

        {{-- Contenido principal --}}
        <div class="flex flex-1 flex-col pl-64">

            {{-- Topbar --}}
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-6">

                {{-- Breadcrumbs --}}
                @if(count($breadcrumbs))
                    <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-sm">
                        @foreach($breadcrumbs as $i => $crumb)
                            @if($i > 0)
                                <flux:icon name="chevron-right" class="size-3 text-slate-300" />
                            @endif
                            @if(isset($crumb['url']) && !$loop->last)
                                <a href="{{ $crumb['url'] }}"
                                   class="text-slate-500 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-2 rounded">
                                    {{ $crumb['label'] }}
                                </a>
                            @else
                                <span class="font-500 text-slate-900">{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <div class="ml-auto flex items-center gap-3">
                    {{-- Notificaciones --}}
                    <flux:button variant="ghost" size="sm" icon="bell" class="relative text-slate-500">
                        <span class="absolute -right-0.5 -top-0.5 flex size-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-black opacity-50"></span>
                            <span class="relative inline-flex size-2 rounded-full bg-black"></span>
                        </span>
                    </flux:button>
                </div>
            </header>

            {{-- Page content --}}
            <main class="flex-1 overflow-y-auto p-6">
                @if($title)
                    <h1 class="mb-6 text-xl font-600 text-slate-900">{{ $title }}</h1>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-app>


{{-- ============================================================
     HELPER: x-host-nav-item
     ============================================================ --}}
{{-- resources/views/components/host-nav-item.blade.php --}}
@props(['route', 'icon', 'label'])

@php $active = request()->routeIs($route . '*'); @endphp

<a href="{{ route($route) }}"
   @class([
       'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-1',
       'bg-slate-100 font-500 text-slate-900'  => $active,
       'font-400 text-slate-600 hover:bg-slate-50 hover:text-slate-900' => !$active,
   ])
   aria-current="{{ $active ? 'page' : 'false' }}">
    <flux:icon :name="$icon"
        @class(['size-4 shrink-0 transition-colors', 'text-slate-900' => $active, 'text-slate-400 group-hover:text-slate-600' => !$active]) />
    {{ $label }}
</a>


{{-- ============================================================
     2. x-layout.tenant
     Shell del tenant con white-label dinámico
     Uso: <x-layout.tenant>...</x-layout.tenant>
     El View Composer inyecta $branding automáticamente
     ============================================================ --}}
{{-- resources/views/components/layout/tenant.blade.php --}}
@props(['breadcrumbs' => [], 'title' => null])

<x-app>
    {{-- CSS variables del tenant inyectadas inline --}}
    <style>
        :root {
            --color-primary:     {{ $branding->primary_color   ?? '#000000' }};
            --color-primary-rgb: {{ $branding->primary_color_rgb ?? '0,0,0' }};
            --color-secondary:   {{ $branding->secondary_color ?? '#334155' }};
        }
        .tenant-primary        { color: var(--color-primary); }
        .tenant-bg-primary     { background-color: var(--color-primary); }
        .tenant-border-primary { border-color: var(--color-primary); }
        .tenant-nav-active     { background-color: color-mix(in srgb, var(--color-primary) 8%, transparent); }
    </style>

    <div class="flex h-full">

        {{-- Sidebar del tenant --}}
        <aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-slate-200 bg-white">

            {{-- Logo white-label --}}
            <div class="flex h-16 shrink-0 items-center border-b border-slate-200 px-5">
                @if($branding->logo_url ?? null)
                    <img src="{{ $branding->logo_url }}" alt="{{ tenant()->name }}"
                         class="h-8 max-w-[140px] object-contain">
                @else
                    <span class="text-sm font-600 text-slate-900">{{ tenant()->name }}</span>
                @endif
            </div>

            {{-- Nav del tenant --}}
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
                <x-tenant-nav-item route="tenant.dashboard"         icon="squares-2x2"    label="Dashboard" />
                <x-tenant-nav-item route="tenant.users.index"       icon="users"          label="Usuarios" />
                <x-tenant-nav-item route="tenant.roles.index"       icon="key"            label="Roles y Permisos"
                    :permission="'manage-roles'" />
                <x-tenant-nav-item route="tenant.security.index"    icon="shield-check"   label="Seguridad"
                    :permission="'manage-security'" />
                <x-tenant-nav-item route="tenant.notifications"     icon="bell"           label="Notificaciones" />
                <x-tenant-nav-item route="tenant.audit.index"       icon="clipboard-list" label="Audit Trail"
                    :permission="'view-audit'" />
                <x-tenant-nav-item route="tenant.usage.index"       icon="chart-bar"      label="Uso y Cuotas" />
                <x-tenant-nav-item route="tenant.integrations.index" icon="puzzle-piece"  label="Integraciones"
                    :permission="'manage-integrations'" />
                <x-tenant-nav-item route="tenant.data.index"        icon="circle-stack"   label="Datos"
                    :permission="'manage-data'" />

                <div class="my-2 border-t border-slate-100"></div>

                <x-tenant-nav-item route="tenant.settings.general"  icon="cog-6-tooth"   label="Configuración"
                    :permission="'manage-settings'" />
            </nav>

            {{-- Footer del sidebar --}}
            <div class="border-t border-slate-200 p-3">
                <flux:dropdown>
                    <flux:button variant="ghost" class="w-full justify-start gap-3 px-3 py-2 text-sm">
                        <flux:avatar size="sm" name="{{ auth()->user()->name }}" />
                        <div class="min-w-0 text-left">
                            <p class="truncate text-xs font-500 text-slate-900">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[10px] text-slate-400">{{ auth()->user()->primaryRole?->name }}</p>
                        </div>
                        <flux:icon name="chevron-up-down" class="ml-auto size-3.5 text-slate-400" />
                    </flux:button>
                    <flux:menu>
                        <flux:menu.item icon="user" href="{{ route('tenant.profile') }}">Mi perfil</flux:menu.item>
                        <flux:menu.item icon="bell" href="{{ route('tenant.profile.notifications') }}">Notificaciones</flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item icon="arrow-right-on-rectangle" variant="danger"
                            x-on:click="$wire.dispatch('logout')">
                            Cerrar sesión
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </aside>

        {{-- Contenido principal --}}
        <div class="flex flex-1 flex-col pl-64">

            {{-- Topbar del tenant --}}
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-slate-200 bg-white px-6">
                @if(count($breadcrumbs))
                    <nav aria-label="Breadcrumb" class="flex items-center gap-1.5 text-sm">
                        @foreach($breadcrumbs as $i => $crumb)
                            @if($i > 0)
                                <flux:icon name="chevron-right" class="size-3 text-slate-300" />
                            @endif
                            @if(isset($crumb['url']) && !$loop->last)
                                <a href="{{ $crumb['url'] }}"
                                   class="text-slate-500 hover:text-slate-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded">
                                    {{ $crumb['label'] }}
                                </a>
                            @else
                                <span class="font-500 text-slate-900">{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <div class="ml-auto flex items-center gap-2">
                    {{-- Indicador de tenant --}}
                    <span class="hidden text-xs text-slate-400 sm:block">{{ tenant()->name }}</span>
                    <div class="h-4 w-px bg-slate-200"></div>
                    {{-- Notificaciones --}}
                    <flux:button variant="ghost" size="sm" icon="bell" class="relative text-slate-500" href="{{ route('tenant.notifications') }}" />
                </div>
            </header>

            <main class="flex-1 overflow-y-auto p-6">
                @if($title)
                    <h1 class="mb-6 text-xl font-600 text-slate-900">{{ $title }}</h1>
                @endif
                {{ $slot }}
            </main>
        </div>
    </div>
</x-app>


{{-- ============================================================
     HELPER: x-tenant-nav-item
     ============================================================ --}}
{{-- resources/views/components/tenant-nav-item.blade.php --}}
@props(['route', 'icon', 'label', 'permission' => null])

@php
    if ($permission && !auth()->user()?->can($permission)) return;
    $active = request()->routeIs($route . '*');
@endphp

<a href="{{ route($route) }}"
   @class([
       'group flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-1',
       'tenant-nav-active tenant-primary font-500' => $active,
       'font-400 text-slate-600 hover:bg-slate-50 hover:text-slate-900' => !$active,
   ])
   aria-current="{{ $active ? 'page' : 'false' }}">
    <flux:icon :name="$icon"
        @class(['size-4 shrink-0', 'tenant-primary' => $active, 'text-slate-400 group-hover:text-slate-600' => !$active]) />
    {{ $label }}
</a>


{{-- ============================================================
     3. x-layout.public
     Shell público: navbar de marketing + footer legal
     ============================================================ --}}
{{-- resources/views/components/layout/public.blade.php --}}
@props(['transparent' => false])

<x-app>
    <div class="flex min-h-full flex-col">

        {{-- Navbar --}}
        <header @class([
            'sticky top-0 z-50 border-b',
            'border-transparent bg-transparent' => $transparent,
            'border-slate-200 bg-white'         => !$transparent,
        ])>
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-8 px-6">

                {{-- Logo --}}
                <a href="{{ route('home') }}"
                   class="text-sm font-700 tracking-tight text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded">
                    {{ config('app.name') }}
                </a>

                {{-- Links de navegación --}}
                <nav class="hidden items-center gap-6 md:flex">
                    <a href="{{ route('pricing') }}"
                       class="text-sm text-slate-600 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded">
                        Precios
                    </a>
                    <a href="{{ route('contact') }}"
                       class="text-sm text-slate-600 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded">
                        Contacto
                    </a>
                </nav>

                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ route('host.login') }}"
                       class="text-sm text-slate-600 transition-colors hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded px-2 py-1">
                        Iniciar sesión
                    </a>
                    <flux:button href="{{ route('register') }}" size="sm" variant="primary">
                        Comenzar gratis
                    </flux:button>
                </div>
            </div>
        </header>

        {{-- Contenido --}}
        <main class="flex-1">
            {{ $slot }}
        </main>

        {{-- Footer legal --}}
        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-6 py-8">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <span class="text-xs text-slate-400">
                        &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
                    </span>
                    <nav class="flex items-center gap-6">
                        @foreach(\App\Models\LegalDocument::published()->get() as $doc)
                            <a href="{{ route('legal.show', $doc->slug) }}"
                               class="text-xs text-slate-400 hover:text-slate-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded">
                                {{ $doc->title }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</x-app>


{{-- ============================================================
     4. x-table
     Tabla genérica: búsqueda + ordenamiento + paginación + acciones
     Uso:
       <x-table :headers="$headers" :rows="$plans" searchable>
           <x-slot:actions>
               <flux:button size="sm">Exportar</flux:button>
           </x-slot:actions>
           <x-slot:row="{ row }">
               <td>{{ $row->name }}</td>
               <td><x-badge :status="$row->status" /></td>
               <td><x-table.actions :row="$row" /></td>
           </x-slot:row>
       </x-table>
     ============================================================ --}}
{{-- resources/views/components/table.blade.php --}}
@props([
    'headers'    => [],   // [['label' => 'Nombre', 'key' => 'name', 'sortable' => true]]
    'rows'       => null, // LengthAwarePaginator o Collection
    'searchable' => false,
    'searchPlaceholder' => 'Buscar...',
    'emptyMessage' => 'No hay registros para mostrar.',
])

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">

    {{-- Toolbar --}}
    @if($searchable || isset($actions))
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3">
            @if($searchable)
                <div class="relative flex-1 max-w-xs">
                    <flux:icon name="magnifying-glass"
                        class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400 pointer-events-none" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ $searchPlaceholder }}"
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-400
                               focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-black focus:ring-offset-0
                               transition-colors"
                        aria-label="{{ $searchPlaceholder }}"
                    />
                </div>
            @endif
            @if(isset($actions))
                <div class="flex items-center gap-2 ml-auto">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif

    {{-- Tabla --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm" role="grid">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    @foreach($headers as $header)
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-600 uppercase tracking-wide text-slate-500 whitespace-nowrap">
                            @if($header['sortable'] ?? false)
                                <button
                                    wire:click="sortBy('{{ $header['key'] }}')"
                                    class="inline-flex items-center gap-1.5 hover:text-slate-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black rounded"
                                    aria-label="Ordenar por {{ $header['label'] }}">
                                    {{ $header['label'] }}
                                    <span class="text-slate-300" aria-hidden="true">
                                        @if(($sortBy ?? '') === $header['key'])
                                            @if(($sortDir ?? 'asc') === 'asc')
                                                ↑
                                            @else
                                                ↓
                                            @endif
                                        @else
                                            ↕
                                        @endif
                                    </span>
                                </button>
                            @else
                                {{ $header['label'] }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100" role="rowgroup">
                @forelse($rows ?? [] as $row)
                    <tr class="group transition-colors hover:bg-slate-50" role="row">
                        {{ $row }}
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($headers) }}" class="px-4 py-12 text-center">
                            <x-empty-state :message="$emptyMessage" compact />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->hasPages())
        <div class="flex items-center justify-between border-t border-slate-200 px-4 py-3 text-xs text-slate-500">
            <span>
                Mostrando {{ $rows->firstItem() }}–{{ $rows->lastItem() }} de {{ $rows->total() }} resultados
            </span>
            <div class="flex items-center gap-1">
                @if($rows->onFirstPage())
                    <span class="cursor-not-allowed rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-300">‹</span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}"
                       class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black"
                       wire:navigate aria-label="Página anterior">‹</a>
                @endif

                @foreach($rows->getUrlRange(max(1,$rows->currentPage()-2), min($rows->lastPage(),$rows->currentPage()+2)) as $page => $url)
                    @if($page === $rows->currentPage())
                        <span class="rounded-lg border border-slate-900 bg-slate-900 px-2.5 py-1.5 font-500 text-white"
                              aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}"
                           class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors
                                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black"
                           wire:navigate>{{ $page }}</a>
                    @endif
                @endforeach

                @if($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}"
                       class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors
                              focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black"
                       wire:navigate aria-label="Página siguiente">›</a>
                @else
                    <span class="cursor-not-allowed rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-300">›</span>
                @endif
            </div>
        </div>
    @endif
</div>


{{-- ============================================================
     5. x-modal
     Modal con slot de contenido y confirmación destructiva opcional
     Uso:
       <x-modal name="delete-plan" title="Eliminar plan" destructive
           confirm-label="Sí, eliminar" confirm-action="deletePlan">
           <p>¿Estás seguro de que deseas eliminar este plan?</p>
       </x-modal>
       Trigger: <flux:button x-on:click="$flux.modal('delete-plan').show()">Eliminar</flux:button>
     ============================================================ --}}
{{-- resources/views/components/modal.blade.php --}}
@props([
    'name',
    'title',
    'description'   => null,
    'destructive'   => false,
    'confirmLabel'  => 'Confirmar',
    'cancelLabel'   => 'Cancelar',
    'confirmAction' => null,
    'maxWidth'      => 'md',  // sm | md | lg
])

<flux:modal :name="$name" class="w-full {{ match($maxWidth) { 'sm'=>'max-w-sm', 'lg'=>'max-w-lg', default=>'max-w-md' } }}">
    <div class="rounded-lg bg-white p-6 shadow-lg ring-1 ring-slate-200">

        {{-- Header --}}
        <div class="mb-4 flex items-start justify-between gap-4">
            <div>
                @if($destructive)
                    <div class="mb-3 flex size-10 items-center justify-center rounded-lg bg-red-50">
                        <flux:icon name="exclamation-triangle" class="size-5 text-red-600" />
                    </div>
                @endif
                <h2 id="modal-{{ $name }}-title"
                    class="text-base font-600 text-slate-900">
                    {{ $title }}
                </h2>
                @if($description)
                    <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                @endif
            </div>
            <flux:button variant="ghost" size="sm" icon="x-mark"
                x-on:click="$flux.modal('{{ $name }}').close()"
                class="shrink-0 text-slate-400 hover:text-slate-600"
                aria-label="Cerrar" />
        </div>

        {{-- Contenido --}}
        <div class="text-sm text-slate-600">
            {{ $slot }}
        </div>

        {{-- Acciones --}}
        <div class="mt-6 flex items-center justify-end gap-3">
            <flux:button variant="ghost" size="sm"
                x-on:click="$flux.modal('{{ $name }}').close()">
                {{ $cancelLabel }}
            </flux:button>

            @if($confirmAction)
                <flux:button
                    :variant="$destructive ? 'danger' : 'primary'"
                    size="sm"
                    wire:click="{{ $confirmAction }}"
                    x-on:click="$flux.modal('{{ $name }}').close()">
                    {{ $confirmLabel }}
                </flux:button>
            @endif

            @if(isset($footer))
                {{ $footer }}
            @endif
        </div>
    </div>
</flux:modal>


{{-- ============================================================
     6. x-alert
     Banners de éxito, error, advertencia e información
     Uso:
       <x-alert type="success" title="Plan creado" dismissible>
           El plan fue creado correctamente.
       </x-alert>
     ============================================================ --}}
{{-- resources/views/components/alert.blade.php --}}
@props([
    'type'      => 'info',   // success | error | warning | info
    'title'     => null,
    'dismissible' => false,
])

@php
$config = match($type) {
    'success' => [
        'wrapper' => 'bg-emerald-50 border-emerald-200 text-emerald-900',
        'icon'    => 'check-circle',
        'icon_c'  => 'text-emerald-600',
        'title_c' => 'text-emerald-900',
        'body_c'  => 'text-emerald-800',
    ],
    'error' => [
        'wrapper' => 'bg-red-50 border-red-200 text-red-900',
        'icon'    => 'x-circle',
        'icon_c'  => 'text-red-600',
        'title_c' => 'text-red-900',
        'body_c'  => 'text-red-800',
    ],
    'warning' => [
        'wrapper' => 'bg-amber-50 border-amber-200 text-amber-900',
        'icon'    => 'exclamation-triangle',
        'icon_c'  => 'text-amber-600',
        'title_c' => 'text-amber-900',
        'body_c'  => 'text-amber-800',
    ],
    default => [
        'wrapper' => 'bg-blue-50 border-blue-200 text-blue-900',
        'icon'    => 'information-circle',
        'icon_c'  => 'text-blue-600',
        'title_c' => 'text-blue-900',
        'body_c'  => 'text-blue-800',
    ],
};
@endphp

<div role="alert"
     aria-live="{{ $type === 'error' ? 'assertive' : 'polite' }}"
     x-data="{ show: true }"
     x-show="show"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @class(['flex items-start gap-3 rounded-lg border p-4 text-sm', $config['wrapper']])>

    <flux:icon :name="$config['icon']"
        class="mt-0.5 size-4 shrink-0 {{ $config['icon_c'] }}" aria-hidden="true" />

    <div class="flex-1 min-w-0">
        @if($title)
            <p class="font-600 {{ $config['title_c'] }}">{{ $title }}</p>
        @endif
        @if($slot->isNotEmpty())
            <p @class(['mt-0.5' => $title, $config['body_c']])>{{ $slot }}</p>
        @endif
    </div>

    @if($dismissible)
        <button x-on:click="show = false"
                class="shrink-0 rounded {{ $config['icon_c'] }} opacity-60 hover:opacity-100 transition-opacity focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-black focus-visible:ring-offset-1"
                aria-label="Cerrar alerta">
            <flux:icon name="x-mark" class="size-4" aria-hidden="true" />
        </button>
    @endif
</div>


{{-- ============================================================
     7. x-badge
     Estado visual del Tenant Lifecycle + estados genéricos
     Uso: <x-badge status="active" /> | <x-badge status="trial" />
          <x-badge :status="$tenant->status" />
     ============================================================ --}}
{{-- resources/views/components/badge.blade.php --}}
@props([
    'status',
    'dot' => true,
])

@php
$map = [
    // Tenant Lifecycle
    'pending_provisioning' => ['label' => 'Provisionando', 'classes' => 'bg-slate-100 text-slate-600',   'dot' => 'bg-slate-400'],
    'trial'                => ['label' => 'Trial',         'classes' => 'bg-blue-50 text-blue-700',     'dot' => 'bg-blue-500'],
    'active'               => ['label' => 'Activo',        'classes' => 'bg-emerald-50 text-emerald-700','dot' => 'bg-emerald-500'],
    'past_due'             => ['label' => 'Pago pendiente','classes' => 'bg-amber-50 text-amber-700',   'dot' => 'bg-amber-500'],
    'suspended'            => ['label' => 'Suspendido',    'classes' => 'bg-red-50 text-red-700',       'dot' => 'bg-red-500'],
    'archived'             => ['label' => 'Archivado',     'classes' => 'bg-slate-100 text-slate-600',  'dot' => 'bg-slate-400'],
    'deleted'              => ['label' => 'Eliminado',     'classes' => 'bg-slate-900 text-white',      'dot' => 'bg-white'],
    // Genéricos
    'enabled'              => ['label' => 'Habilitado',    'classes' => 'bg-emerald-50 text-emerald-700','dot' => 'bg-emerald-500'],
    'disabled'             => ['label' => 'Deshabilitado', 'classes' => 'bg-slate-100 text-slate-500',  'dot' => 'bg-slate-400'],
    'pending'              => ['label' => 'Pendiente',     'classes' => 'bg-amber-50 text-amber-700',   'dot' => 'bg-amber-400'],
    'failed'               => ['label' => 'Fallido',       'classes' => 'bg-red-50 text-red-700',       'dot' => 'bg-red-500'],
    'processing'           => ['label' => 'Procesando',    'classes' => 'bg-blue-50 text-blue-700',     'dot' => 'bg-blue-400'],
    'verified'             => ['label' => 'Verificado',    'classes' => 'bg-emerald-50 text-emerald-700','dot' => 'bg-emerald-500'],
    'paid'                 => ['label' => 'Pagada',        'classes' => 'bg-emerald-50 text-emerald-700','dot' => 'bg-emerald-500'],
    'unpaid'               => ['label' => 'Impaga',        'classes' => 'bg-red-50 text-red-700',       'dot' => 'bg-red-500'],
    'partial'              => ['label' => 'Parcial',       'classes' => 'bg-amber-50 text-amber-700',   'dot' => 'bg-amber-400'],
];

$badge = $map[$status] ?? ['label' => ucfirst($status), 'classes' => 'bg-slate-100 text-slate-600', 'dot' => 'bg-slate-400'];
@endphp

<span @class(['inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-500 whitespace-nowrap', $badge['classes']]
)>
    @if($dot)
        <span class="size-1.5 rounded-full {{ $badge['dot'] }}" aria-hidden="true"></span>
    @endif
    {{ $badge['label'] }}
</span>


{{-- ============================================================
     8. x-empty-state
     Pantalla vacía con CTA contextual
     Uso:
       <x-empty-state
           icon="rectangle-stack"
           title="No hay planes"
           description="Crea el primer plan para empezar a vender."
           action-label="Crear plan"
           action-route="host.billing.plans.create" />
     ============================================================ --}}
{{-- resources/views/components/empty-state.blade.php --}}
@props([
    'icon'        => 'inbox',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionRoute' => null,
    'actionWire'  => null,
    'compact'     => false,
])

<div @class(['flex flex-col items-center text-center', 'py-8' => $compact, 'py-16' => !$compact])>

    <div @class([
        'flex items-center justify-center rounded-xl bg-slate-100',
        'mb-3 size-10' => $compact,
        'mb-4 size-14' => !$compact,
    ])>
        <flux:icon :name="$icon" @class(['text-slate-400', 'size-5' => $compact, 'size-7' => !$compact]) />
    </div>

    <h3 @class(['font-600 text-slate-900', 'text-sm' => $compact, 'text-base' => !$compact])>
        {{ $title }}
    </h3>

    @if($description)
        <p @class(['mt-1 text-slate-500', 'text-xs' => $compact, 'text-sm' => !$compact, 'max-w-sm' => !$compact])>
            {{ $description }}
        </p>
    @endif

    @if($actionLabel)
        <div class="mt-4">
            @if($actionRoute)
                <flux:button href="{{ route($actionRoute) }}" size="sm" variant="primary" icon="plus">
                    {{ $actionLabel }}
                </flux:button>
            @elseif($actionWire)
                <flux:button wire:click="{{ $actionWire }}" size="sm" variant="primary" icon="plus">
                    {{ $actionLabel }}
                </flux:button>
            @endif
        </div>
    @endif

    @if(isset($slot) && $slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>


{{-- ============================================================
     9. x-skeleton
     Placeholder de carga por sección
     Uso:
       <x-skeleton type="table" :rows="5" />
       <x-skeleton type="card" />
       <x-skeleton type="stat" :count="3" />
       <x-skeleton type="form" :fields="4" />
     ============================================================ --}}
{{-- resources/views/components/skeleton.blade.php --}}
@props([
    'type'   => 'table',  // table | card | stat | form | text
    'rows'   => 5,
    'count'  => 3,
    'fields' => 3,
])

@php
// Clase base de la animación
$pulse = 'animate-pulse rounded-lg bg-slate-200';
@endphp

{{-- TABLE skeleton --}}
@if($type === 'table')
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-busy="true" aria-label="Cargando tabla">
        {{-- Toolbar falso --}}
        <div class="flex items-center gap-4 border-b border-slate-200 px-4 py-3">
            <div class="{{ $pulse }} h-8 w-48"></div>
            <div class="{{ $pulse }} ml-auto h-8 w-24"></div>
        </div>
        {{-- Header falso --}}
        <div class="flex items-center gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3">
            @for($i = 0; $i < 4; $i++)
                <div class="{{ $pulse }} h-3 {{ match($i) { 0 => 'w-32', 1 => 'w-24', 2 => 'w-20', default => 'w-16 ml-auto' } }}"></div>
            @endfor
        </div>
        {{-- Filas falsas --}}
        @for($r = 0; $r < $rows; $r++)
            <div class="flex items-center gap-4 border-b border-slate-100 px-4 py-3.5 last:border-0">
                <div class="{{ $pulse }} size-7 rounded-full shrink-0"></div>
                <div class="{{ $pulse }} h-3.5 w-36"></div>
                <div class="{{ $pulse }} h-3.5 w-24"></div>
                <div class="{{ $pulse }} h-5 w-16 rounded-md"></div>
                <div class="{{ $pulse }} ml-auto h-3.5 w-20"></div>
            </div>
        @endfor
    </div>

{{-- CARD skeleton --}}
@elseif($type === 'card')
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-busy="true" aria-label="Cargando">
        <div class="{{ $pulse }} mb-4 h-4 w-1/3"></div>
        <div class="space-y-2">
            <div class="{{ $pulse }} h-3 w-full"></div>
            <div class="{{ $pulse }} h-3 w-5/6"></div>
            <div class="{{ $pulse }} h-3 w-4/6"></div>
        </div>
        <div class="{{ $pulse }} mt-4 h-8 w-24"></div>
    </div>

{{-- STAT skeleton --}}
@elseif($type === 'stat')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-{{ $count }}" aria-busy="true" aria-label="Cargando métricas">
        @for($i = 0; $i < $count; $i++)
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="{{ $pulse }} mb-3 h-3 w-24"></div>
                <div class="{{ $pulse }} mb-1 h-7 w-32"></div>
                <div class="{{ $pulse }} h-3 w-20"></div>
            </div>
        @endfor
    </div>

{{-- FORM skeleton --}}
@elseif($type === 'form')
    <div class="space-y-5" aria-busy="true" aria-label="Cargando formulario">
        @for($i = 0; $i < $fields; $i++)
            <div>
                <div class="{{ $pulse }} mb-1.5 h-3 w-24"></div>
                <div class="{{ $pulse }} h-9 w-full rounded-lg"></div>
            </div>
        @endfor
        <div class="{{ $pulse }} ml-auto h-9 w-28 rounded-lg"></div>
    </div>

{{-- TEXT skeleton --}}
@elseif($type === 'text')
    <div class="space-y-2" aria-busy="true" aria-label="Cargando contenido">
        <div class="{{ $pulse }} h-4 w-3/4"></div>
        <div class="{{ $pulse }} h-3 w-full"></div>
        <div class="{{ $pulse }} h-3 w-5/6"></div>
        <div class="{{ $pulse }} h-3 w-2/3"></div>
    </div>
@endif