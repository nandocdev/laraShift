<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    @if($logoUrl)
                    <img src="{{ $logoUrl }}" class="h-8 w-auto">
                    @else
                    <span class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white" style="color: {{ $primaryColor }}">{{ $platformName }}</span>
                    @endif
                </div>

                <div class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <a href="#features" class="hover:text-zinc-900 dark:hover:text-white transition-colors">{{ __('Features') }}</a>
                    <a href="#" class="hover:text-zinc-900 dark:hover:text-white transition-colors">{{ __('Documentation') }}</a>
                </div>

                <div class="flex items-center space-x-4">
                    <flux:button href="/register" variant="primary" size="sm">{{ __('Get Started') }}</flux:button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative pt-20 pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
            <flux:heading size="xl" class="text-5xl md:text-7xl font-extrabold tracking-tight mb-6">
                {{ __('Scale your business with') }} <span style="color: {{ $primaryColor }}">openSaaS</span>
            </flux:heading>
            <flux:subheading class="text-xl md:text-2xl text-zinc-600 dark:text-zinc-400 max-w-3xl mx-auto mb-10 leading-relaxed">
                {{ __('The ultimate production-grade SaaS multi-tenant boilerplate. Built for speed, security, and operational simplicity.') }}
            </flux:subheading>

            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <flux:button variant="primary" href="/register" class="px-8 py-3 text-lg font-bold">{{ __('Launch your SaaS') }}</flux:button>
                <flux:button variant="ghost" href="https://github.com/nandocdev/openSaaS" target="_blank" class="px-8 py-3 text-lg font-bold" icon="folder-git-2">{{ __('View on GitHub') }}</flux:button>
            </div>
        </div>

        <!-- Abstract Background Shape -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full -z-0 opacity-20 dark:opacity-10 pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl" style="background-color: {{ $primaryColor }}"></div>
            <div class="absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full blur-3xl bg-indigo-500"></div>
        </div>
    </header>

    <!-- Features Overview -->
    <section id="features" class="py-24 bg-white dark:bg-zinc-900 border-y border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <flux:heading size="lg" class="mb-2 text-indigo-600">{{ __('Powering Modern SaaS') }}</flux:heading>
                <flux:heading size="xl">{{ __('Enterprise Infrastructure for Everyone') }}</flux:heading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                <div class="space-y-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg" style="background-color: {{ $primaryColor }}">
                        <flux:icon icon="shield-check" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Tenant Isolation') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Built-in PostgreSQL Row Level Security (RLS) ensures that data never leaks between customers.') }}</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg" style="background-color: {{ $primaryColor }}">
                        <flux:icon icon="credit-card" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Team Management') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Roles, invitations and multi-tenant team workspaces out of the box.') }}</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg" style="background-color: {{ $primaryColor }}">
                        <flux:icon icon="server-stack" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Queue Isolation') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Dedicated background job processing per tenant to prevent noisy neighbor performance issues.') }}</flux:text>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <flux:heading size="xl" class="mb-4">{{ __('Launch your workspace in minutes') }}</flux:heading>
            <flux:subheading class="mb-8">{{ __('Create your organization and invite your team. No credit card required.') }}</flux:subheading>
            <flux:button href="/register" variant="primary" class="px-8 py-3 text-lg font-bold">
                {{ __('Get Started') }}
            </flux:button>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-zinc-50 dark:bg-zinc-950 border-t border-zinc-200 dark:border-zinc-800 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="flex flex-col items-center md:items-start">
                <div class="flex items-center gap-2 mb-4">
                    @if($logoUrl)
                    <img src="{{ $logoUrl }}" class="h-6 w-auto">
                    @else
                    <span class="font-bold tracking-tight text-zinc-900 dark:text-white" style="color: {{ $primaryColor }}">{{ $platformName }}</span>
                    @endif
                </div>
                <p class="text-xs text-zinc-500">© 2026 {{ $platformName }}. {{ __('All rights reserved.') }}</p>
            </div>

            <div class="flex gap-8 text-sm font-medium text-zinc-500">
                <a href="#" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Terms') }}</a>
                <a href="#" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Privacy') }}</a>
                <a href="#" class="hover:text-zinc-900 dark:hover:text-white">{{ __('Contact') }}</a>
            </div>
        </div>
    </footer>
</div>
