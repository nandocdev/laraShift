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
        'surface' => 'bg-surface text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-white'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}" x-data="{ billing: 'monthly' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(($config['headline'] ?? null) || ($config['subtitle'] ?? null))
            <div class="text-center mb-16">
                @if($config['headline'] ?? null)
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight">
                        {{ $config['headline'] }}
                    </h2>
                @endif
                @if($config['subtitle'] ?? null)
                    <p class="mt-4 text-xl opacity-70 max-w-2xl mx-auto">
                        {{ $config['subtitle'] }}
                    </p>
                @endif

                @if($config['show_toggle'] ?? false)
                    <div class="mt-10 flex justify-center items-center gap-4">
                        <span class="text-sm font-bold tracking-wide transition" :class="billing === 'monthly' ? 'text-primary' : 'opacity-50'">{{ __('Monthly') }}</span>
                        <button 
                            type="button"
                            x-on:click="billing = billing === 'monthly' ? 'annual' : 'monthly'"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-zinc-200 dark:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                        >
                            <span 
                                :class="billing === 'annual' ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            ></span>
                        </button>
                        <span class="text-sm font-bold tracking-wide transition" :class="billing === 'annual' ? 'text-primary' : 'opacity-50'">
                            {{ __('Annual') }}
                            @if($config['annual_discount_text'] ?? null)
                                <span class="ml-1 text-xs text-emerald-500 font-black uppercase tracking-tighter">({{ $config['annual_discount_text'] }})</span>
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid gap-8 lg:grid-cols-3 items-center">
            @foreach($config['plans'] ?? [] as $plan)
                <div class="relative flex flex-col p-8 md:p-10 rounded-[2.5rem] shadow-sm border transition-all duration-500 hover:shadow-2xl {{ ($plan['is_featured'] ?? false) ? 'bg-white dark:bg-zinc-800 border-primary ring-4 ring-primary/10 scale-105 z-10 py-12 md:py-14' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700 hover:-translate-y-1' }}">
                    @if($plan['badge'] ?? null)
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 px-4 py-1 bg-primary text-white text-xs font-black uppercase tracking-widest rounded-full shadow-lg shadow-primary/30">
                            {{ $plan['badge'] }}
                        </div>
                    @endif

                    <div class="mb-8">
                        <h3 class="text-xl font-black uppercase tracking-tight mb-2">{{ $plan['name'] ?? '' }}</h3>
                        <p class="text-sm opacity-60 leading-relaxed">{{ $plan['description'] ?? '' }}</p>
                    </div>

                    <div class="mb-8 flex items-baseline gap-1">
                        <span class="text-5xl font-black tracking-tighter">
                            <span x-show="billing === 'monthly'">{{ $plan['currency'] ?? '$' }}{{ $plan['price_monthly'] ?? '0' }}</span>
                            <span x-show="billing === 'annual'">{{ $plan['currency'] ?? '$' }}{{ $plan['price_annual'] ?? '0' }}</span>
                        </span>
                        <span class="text-lg font-bold opacity-40">/{{ __('mo') }}</span>
                    </div>

                    <ul class="mb-10 space-y-4 flex-1">
                        @foreach($plan['features'] ?? [] as $feature)
                            <li class="flex items-start text-sm">
                                <flux:icon.check-circle variant="solid" class="mr-3 h-5 w-5 {{ ($feature['included'] ?? true) ? 'text-primary' : 'text-zinc-300 dark:text-zinc-600 opacity-50' }}" />
                                <span class="{{ ($feature['included'] ?? true) ? 'font-medium' : 'opacity-40 line-through' }}">{{ $feature['text'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a 
                        href="{{ $plan['cta_url'] ?? '#' }}" 
                        target="{{ $plan['cta_target'] ?? '_self' }}"
                        class="w-full flex items-center justify-center px-8 py-4 border border-transparent text-base font-black rounded-2xl transition shadow-lg {{ ($plan['is_featured'] ?? false) ? 'bg-primary text-white hover:opacity-90 shadow-primary/20' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white hover:bg-zinc-200 dark:hover:bg-zinc-600' }}"
                    >
                        {{ $plan['cta_text'] ?? __('Get Started') }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
