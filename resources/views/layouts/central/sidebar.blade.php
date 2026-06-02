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
                <flux:sidebar.item icon="heart" :href="route('central.health')"
                    :current="request()->routeIs('central.health')" target="_blank">
                    {{ __('Health Monitor') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Support')" class="grid">
                <flux:sidebar.item icon="megaphone" :href="route('central.support.broadcasts')"
                    :current="request()->routeIs('central.support.broadcasts')" wire:navigate>
                    {{ __('Broadcast Center') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Billing')" class="grid">
                <flux:sidebar.item icon="credit-card" :href="route('central.billing.subscriptions')"
                    :current="request()->routeIs('central.billing.subscriptions')" wire:navigate>
                    {{ __('Subscriptions') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="presentation-chart-line" :href="route('central.billing.plans')"
                    :current="request()->routeIs('central.billing.plans')" wire:navigate>
                    {{ __('Plans') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="receipt-percent" href="#"
                    :current="false" wire:navigate>
                    {{ __('Global Invoices') }}
                </flux:sidebar.item>
            </flux:sidebar.group>

            <flux:sidebar.group :heading="__('Settings')" class="grid">
                <flux:sidebar.item icon="paint-brush" :href="route('central.settings.branding')"
                    :current="request()->routeIs('central.settings.*')" wire:navigate>
                    {{ __('Platform Branding') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="command-line" :href="route('central.features.index')"
                    :current="request()->routeIs('central.features.*')" wire:navigate>
                    {{ __('Feature Catalog') }}
                </flux:sidebar.item>
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
                        <div class="p-0 text-sm font-normal">
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
