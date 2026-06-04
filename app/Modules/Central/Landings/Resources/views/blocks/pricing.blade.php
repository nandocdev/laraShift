@props([
    'config' => [],
    'styles' => [],
    'variant' => 'cards',
])

@php
    $padding = match($styles['padding'] ?? 'lg') {
        'md' => 'py-12',
        'lg' => 'py-20',
        'xl' => 'py-32',
        default => 'py-20'
    };
    
    $bgClass = match($styles['background'] ?? 'white') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}" x-data="{ billing: 'monthly' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($config['section_title'] ?? null)
            <div class="text-center mb-12">
                <h2 class="text-3xl font-extrabold sm:text-4xl">
                    {{ $config['section_title'] }}
                </h2>
                @if($config['section_subtitle'] ?? null)
                    <p class="mt-4 text-lg opacity-80 max-w-2xl mx-auto">
                        {{ $config['section_subtitle'] }}
                    </p>
                @endif

                @if($config['show_toggle'] ?? false)
                    <div class="mt-8 flex justify-center items-center gap-4">
                        <span class="text-sm font-medium" :class="billing === 'monthly' ? 'text-primary' : 'opacity-60'">{{ __('Monthly') }}</span>
                        <button 
                            type="button"
                            x-on:click="billing = billing === 'monthly' ? 'annual' : 'monthly'"
                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-zinc-200 dark:bg-zinc-700 focus:outline-none"
                        >
                            <span 
                                :class="billing === 'annual' ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            ></span>
                        </button>
                        <span class="text-sm font-medium" :class="billing === 'annual' ? 'text-primary' : 'opacity-60'">
                            {{ __('Annual') }}
                            @if($config['annual_discount_text'] ?? null)
                                <span class="ml-1 text-xs text-emerald-500 font-bold uppercase tracking-wider">({{ $config['annual_discount_text'] }})</span>
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-3">
            @foreach($config['plans'] ?? [] as $plan)
                <div class="relative flex flex-col p-8 rounded-3xl shadow-sm border transition-all duration-300 {{ ($plan['is_featured'] ?? false) ? 'bg-white dark:bg-zinc-800 border-primary ring-2 ring-primary scale-105 z-10' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700' }}">
                    @if($plan['badge'] ?? null)
                        <div class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-primary text-white text-xs font-bold uppercase tracking-widest rounded-full">
                            {{ $plan['badge'] }}
                        </div>
                    @endif

                    <div class="mb-8">
                        <h3 class="text-xl font-bold mb-2">{{ $plan['name'] ?? '' }}</h3>
                        <p class="text-sm opacity-60 h-10 overflow-hidden">{{ $plan['description'] ?? '' }}</p>
                    </div>

                    <div class="mb-8">
                        <span class="text-5xl font-extrabold tracking-tight">
                            <span x-show="billing === 'monthly'">{{ $plan['currency'] ?? '$' }}{{ $plan['price_monthly'] ?? '0' }}</span>
                            <span x-show="billing === 'annual'">{{ $plan['currency'] ?? '$' }}{{ $plan['price_annual'] ?? '0' }}</span>
                        </span>
                        <span class="text-base font-medium opacity-60">/{{ __('mo') }}</span>
                    </div>

                    <ul class="mb-8 space-y-4 flex-1">
                        @foreach($plan['features'] ?? [] as $feature)
                            <li class="flex items-start text-sm">
                                <flux:icon.check-circle class="mr-3 h-5 w-5 {{ ($feature['included'] ?? true) ? 'text-emerald-500' : 'text-zinc-300 opacity-50' }}" />
                                <span class="{{ ($feature['included'] ?? true) ? '' : 'opacity-50' }}">{{ $feature['text'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a 
                        href="{{ $plan['cta_url'] ?? '#' }}" 
                        class="w-full flex items-center justify-center px-6 py-3 border border-transparent text-base font-bold rounded-xl transition {{ ($plan['is_featured'] ?? false) ? 'bg-primary text-white hover:opacity-90' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                    >
                        {{ $plan['cta_text'] ?? __('Get Started') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
