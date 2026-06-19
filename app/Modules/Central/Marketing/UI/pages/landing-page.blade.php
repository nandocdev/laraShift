<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    <!-- Navbar -->
    <nav class="sticky top-0 z-50 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" class="h-8 w-auto">
                    @else
                        <span class="text-xl font-bold tracking-tight text-primary">{{ $platformName }}</span>
                    @endif
                </div>
                
                <div class="hidden md:flex items-center space-x-8 text-sm font-medium text-zinc-600 dark:text-zinc-400">
                    <a href="#features" class="hover:text-zinc-900 dark:hover:text-white transition-colors">{{ __('Features') }}</a>
                    <a href="#pricing" class="hover:text-zinc-900 dark:hover:text-white transition-colors">{{ __('Pricing') }}</a>
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
            <flux:heading size="xl" class="text-5xl md:text-7xl font-extrabold tracking-tighter mb-6">
                {{ __('Scale your business with') }} <span class="text-primary">LaraShift</span>
            </flux:heading>
            <flux:subheading class="text-xl md:text-2xl text-zinc-600 dark:text-zinc-400 max-w-3xl mx-auto mb-10 leading-relaxed">
                {{ __('The ultimate production-grade SaaS multi-tenant boilerplate. Built for speed, security, and operational simplicity.') }}
            </flux:subheading>
            
            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <flux:button variant="primary" href="/register" class="px-8 py-3 text-lg font-bold">{{ __('Launch your SaaS') }}</flux:button>
                <flux:button variant="ghost" href="https://github.com/nandocdev/LaraShift" target="_blank" class="px-8 py-3 text-lg font-bold" icon="folder-git-2">{{ __('View on GitHub') }}</flux:button>
            </div>
        </div>
        
        <!-- Abstract Background Shape -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full -z-0 opacity-20 dark:opacity-10 pointer-events-none">
            <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full blur-3xl bg-primary animate-pulse"></div>
            <div class="absolute bottom-1/4 right-1/4 w-64 h-64 rounded-full blur-3xl bg-indigo-500 animate-pulse" style="animation-delay: 2s;"></div>
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
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg bg-primary">
                        <flux:icon icon="shield-check" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Tenant Isolation') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Built-in PostgreSQL Row Level Security (RLS) ensures that data never leaks between customers.') }}</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg bg-primary">
                        <flux:icon icon="credit-card" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Multi-gateway Billing') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Native support for Stripe (Global) and dLocal (LATAM) with dynamic plan matrix management.') }}</flux:text>
                </div>

                <div class="space-y-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white shadow-lg bg-primary">
                        <flux:icon icon="server-stack" variant="solid" />
                    </div>
                    <flux:heading size="lg">{{ __('Queue Isolation') }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __('Dedicated background job processing per tenant to prevent noisy neighbor performance issues.') }}</flux:text>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <flux:heading size="xl" class="mb-4">{{ __('Simple, Transparent Pricing') }}</flux:heading>
                <flux:subheading>{{ __('Choose the tier that fits your business stage. No hidden fees.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($plans as $plan)
                    <flux:card class="relative flex flex-col p-8 transition-all duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-primary/10 dark:ring-1 dark:ring-white/10 {{ $plan->slug === 'pro' ? 'ring-2 ring-primary border-primary' : '' }}">
                        @if($plan->slug === 'pro')
                            <div class="absolute -top-4 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full text-xs font-bold text-white uppercase tracking-widest shadow-sm bg-primary">
                                {{ __('Most Popular') }}
                            </div>
                        @endif

                        <div class="mb-8">
                            <flux:heading size="lg" class="text-2xl mb-1">{{ $plan->name }}</flux:heading>
                            <div class="flex items-baseline gap-1 mt-4">
                                <span class="text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">{{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($plan->price_monthly) }}</span>
                                <span class="text-zinc-500 text-sm">/{{ __('month') }}</span>
                            </div>
                        </div>

                        <ul class="flex-1 space-y-4 mb-8">
                            @foreach($plan->features['display_features'] ?? [] as $feature)
                                <li class="flex items-center gap-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    <flux:icon icon="check" class="text-emerald-500 shrink-0" size="sm" />
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                            
                            @if(isset($plan->features['quotas']))
                                <li class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                                    <div class="text-[10px] font-bold uppercase text-zinc-400 tracking-wider">{{ __('Technical Quotas') }}</div>
                                    <div class="flex justify-between text-xs">
                                        <span>{{ __('Branches') }}</span>
                                        <span class="font-bold">{{ $plan->features['quotas']['branches'] < 0 ? 'Unlimited' : $plan->features['quotas']['branches'] }}</span>
                                    </div>
                                    <div class="flex justify-between text-xs">
                                        <span>{{ __('Staff Members') }}</span>
                                        <span class="font-bold">{{ $plan->features['quotas']['staff'] < 0 ? 'Unlimited' : $plan->features['quotas']['staff'] }}</span>
                                    </div>
                                </li>
                            @endif
                        </ul>

                        <flux:button 
                            href="/register?plan={{ $plan->slug }}"
                            variant="{{ $plan->slug === 'pro' ? 'primary' : 'ghost' }}" 
                            class="w-full py-3"
                        >
                            {{ $plan->price_monthly->isPositive() ? __('Get Started') : __('Start for Free') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </div>
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
                        <span class="font-bold tracking-tight text-primary">{{ $platformName }}</span>
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
