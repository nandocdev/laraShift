<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('central.dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Administration')" class="grid">
                <flux:sidebar.item icon="home" :href="route('central.dashboard')"
                    :current="request()->routeIs('central.dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="server-stack" :href="route('central.provisioning.index')"
                    :current="request()->routeIs('central.provisioning.*')" wire:navigate>
                    {{ __('Tenants') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="heart" :href="route('central.health.monitor')"
                    :current="request()->routeIs('central.health.monitor')" wire:navigate>
                    {{ __('Health Monitor') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="clipboard-document-list" :href="route('central.audit.log')"
                    :current="request()->routeIs('central.audit.log')" wire:navigate>
                    {{ __('Audit Log') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Billing')" class="grid">
                <flux:sidebar.item icon="credit-card" :href="route('central.billing.subscriptions')"
                    :current="request()->routeIs('central.billing.*')" wire:navigate>
                    {{ __('Subscriptions') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Catalog')" class="grid">
                <flux:sidebar.item icon="squares-2x2" :href="route('central.catalog.plans')"
                    :current="request()->routeIs('central.catalog.*')" wire:navigate>
                    {{ __('Plans') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Support')" class="grid">
                <flux:sidebar.item icon="megaphone" :href="route('central.support.broadcasts')"
                    :current="request()->routeIs('central.support.broadcasts')" wire:navigate>
                    {{ __('Broadcast Center') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            @if(auth('central')->user()?->is_global_admin)
                <flux:sidebar.group :heading="__('Security')" class="grid">
                    <flux:sidebar.item icon="users" :href="route('central.auth.users')"
                        :current="request()->routeIs('central.auth.users')" wire:navigate>
                        {{ __('Admin Users') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            @endif

            <flux:sidebar.group :heading="__('Settings')" class="grid">
                <flux:sidebar.item icon="paint-brush" :href="route('central.settings.branding')"
                    :current="request()->routeIs('central.settings.*')" wire:navigate>
                    {{ __('Platform Branding') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>
        <flux:spacer />

        <div
            x-data="{
                status: 'loading',
                checks: { database: { status: 'loading' }, redis: { status: 'loading' }, queue: { status: 'loading', size: null, failed_jobs: null } },
                dot(status) {
                    return {
                        pass: 'bg-emerald-500',
                        warn: 'bg-amber-500',
                        fail: 'bg-rose-500',
                    }[status] ?? 'bg-zinc-400 animate-pulse';
                },
                label() {
                    return { healthy: '{{ __('System healthy') }}', degraded: '{{ __('System degraded') }}' }[this.status] ?? '{{ __('Checking status…') }}';
                },
                async load() {
                    try {
                        const res = await fetch('{{ route('central.health') }}', {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        });
                        if (!res.ok) throw new Error(res.status);
                        const data = await res.json();
                        this.status = data.status ?? 'degraded';
                        this.checks = data.checks ?? this.checks;
                    } catch (e) {
                        this.status = 'degraded';
                    }
                }
            }"
            x-init="load(); setInterval(() => load(), 60000)"
            class="border-t border-zinc-200 px-3 py-3 dark:border-zinc-700/60"
        >
            <a href="{{ route('central.health') }}" target="_blank" class="flex items-center gap-2 text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                <span class="size-2 shrink-0 rounded-full" :class="dot(status)"></span>
                <span x-text="label()">{{ __('Checking status…') }}</span>
            </a>
            <div class="mt-2 space-y-1 text-[11px] leading-tight text-zinc-500 dark:text-zinc-400">
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full" :class="dot(checks.database?.status)"></span>
                        {{ __('Database') }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full" :class="dot(checks.redis?.status)"></span>
                        {{ __('Redis') }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-1.5">
                        <span class="size-1.5 rounded-full" :class="dot(checks.queue?.status)"></span>
                        {{ __('Queue') }}
                    </span>
                    <span x-show="checks.queue?.size !== undefined && checks.queue?.size !== null" x-text="checks.queue.size + ' / ' + (checks.queue.failed_jobs ?? 0)" class="tabular-nums"></span>
                </div>
            </div>
        </div>

        @auth('central')
        <x-central-user-menu class="hidden lg:block" />
        @endauth
    </flux:sidebar>

    <!-- Mobile User Menu -->
    @auth('central')
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth('central')->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth('central')->user()->name"
                                :initials="auth('central')->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth('central')->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth('central')->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('central.auth.2fa')" icon="shield-check" wire:navigate>
                        {{ __('Security & 2FA') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('central.logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>
    @endauth

    {{ $slot }}

    @persist('toast')
    <flux:toast.group>
        <flux:toast />
    </flux:toast.group>
    @endpersist

    @fluxScripts
</body>

</html>
