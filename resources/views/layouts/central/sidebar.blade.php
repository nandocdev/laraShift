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
                <x-host-nav-item route="central.dashboard" icon="home" :label="__('Dashboard')" />
                <x-host-nav-item route="central.provisioning.*" icon="server-stack" :label="__('Tenants')" />
                <x-host-nav-item route="central.health" icon="heart" :label="__('Health Monitor')"
                    href="{{ route('central.health') }}" target="_blank" />
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Support')" class="grid">
                <x-host-nav-item route="central.support.broadcasts" icon="megaphone" :label="__('Broadcast Center')" />
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Billing')" class="grid">
                <x-host-nav-item route="central.billing.subscriptions" icon="credit-card" :label="__('Subscriptions')" />
                <x-host-nav-item route="central.billing.plans" icon="presentation-chart-line" :label="__('Plans')" />
                <x-host-nav-item route="central.billing.invoices.global" icon="receipt-percent" :label="__('Global Invoices')" />
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Settings')" class="grid">
                <x-host-nav-item route="central.settings.*" icon="paint-brush" :label="__('Platform Branding')" />
                <x-host-nav-item route="central.features.*" icon="command-line" :label="__('Feature Catalog')" />
            </flux:sidebar.group>
        </flux:sidebar.nav>
        <flux:spacer />

        <flux:sidebar.nav>
            <flux:sidebar.item icon="folder-git-2" href="https://github.com/nandocdev/LaraShift" target="_blank">
                {{ __('Project') }}
            </flux:sidebar.item>

            <flux:sidebar.item icon="book-open-text" href="{{ url('/docs') }}" target="_blank">
                {{ __('Documentation') }}
            </flux:sidebar.item>
        </flux:sidebar.nav>

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
