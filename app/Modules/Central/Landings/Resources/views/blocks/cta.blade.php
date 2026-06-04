@props([
    'config' => [],
    'styles' => [],
    'variant' => 'centered',
])

@php
    $padding = match($styles['padding'] ?? 'lg') {
        'md' => 'py-12',
        'lg' => 'py-20',
        'xl' => 'py-32',
        default => 'py-20'
    };
    
    $bgClass = match($styles['background'] ?? 'primary') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white',
        'gradient' => 'bg-gradient-to-br from-primary to-indigo-700 text-white',
        default => 'bg-primary text-white'
    };

    $textAlign = $styles['text_align'] ?? ($variant === 'centered' ? 'center' : 'left');
    $alignmentClass = match($textAlign) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'centered')
            <div class="max-w-4xl mx-auto {{ $alignmentClass }}">
                <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight">
                    {{ $config['headline'] ?? 'Ready to grow your business?' }}
                </h2>
                
                @if($config['description'] ?? null)
                    <p class="mt-6 text-xl opacity-90 max-w-2xl {{ $textAlign === 'center' ? 'mx-auto' : ($textAlign === 'right' ? 'ml-auto' : '') }}">
                        {{ $config['description'] }}
                    </p>
                @endif
                
                <div class="mt-10 flex flex-wrap gap-4 {{ $textAlign === 'center' ? 'justify-center' : ($textAlign === 'right' ? 'justify-end' : '') }}">
                    @if($config['button_primary_text'] ?? null)
                        <a href="{{ $config['button_primary_url'] ?? '#' }}" class="px-8 py-4 bg-white text-primary rounded-xl font-black text-lg shadow-xl hover:bg-gray-50 transition transform hover:scale-105">
                            {{ $config['button_primary_text'] }}
                        </a>
                    @endif
                    
                    @if(($config['show_secondary_button'] ?? false) && ($config['button_secondary_text'] ?? null))
                        <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="px-8 py-4 border-2 border-white/30 text-white rounded-xl font-bold text-lg hover:bg-white/10 transition">
                            {{ $config['button_secondary_text'] }}
                        </a>
                    @endif
                </div>

                @if($config['show_guarantee'] ?? false)
                    <p class="mt-6 text-sm font-medium opacity-70">
                        {{ $config['guarantee_text'] ?? 'No credit card required.' }}
                    </p>
                @endif
            </div>

        @elseif($variant === 'banner')
            <div class="bg-white/10 backdrop-blur-sm rounded-3xl p-8 md:p-12 border border-white/10">
                <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl md:text-3xl font-bold">{{ $config['headline'] ?? 'Start your free trial today.' }}</h2>
                        @if($config['description'] ?? null)
                            <p class="mt-2 text-lg opacity-80">{{ $config['description'] }}</p>
                        @endif
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-4">
                        @if($config['button_primary_text'] ?? null)
                            <a href="{{ $config['button_primary_url'] ?? '#' }}" class="px-8 py-4 bg-white text-primary rounded-xl font-black shadow-lg hover:bg-gray-50 transition">
                                {{ $config['button_primary_text'] }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

        @elseif($variant === 'split')
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="{{ $alignmentClass }}">
                    <h2 class="text-3xl font-extrabold sm:text-4xl">{{ $config['headline'] ?? 'Ready to join us?' }}</h2>
                    <p class="mt-4 text-lg opacity-80 leading-relaxed">{{ $config['description'] ?? 'Sign up for a 14-day free trial.' }}</p>
                    
                    @if($config['show_guarantee'] ?? false)
                        <div class="mt-6 flex items-center gap-2 opacity-70">
                            <flux:icon.check-circle size="xs" class="text-white" />
                            <span class="text-sm">{{ $config['guarantee_text'] ?? 'No credit card required.' }}</span>
                        </div>
                    @endif
                </div>
                
                <div class="bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-2xl border border-zinc-100 dark:border-zinc-700">
                    <div class="space-y-4">
                        <div class="h-12 bg-zinc-100 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center px-4 text-zinc-400 text-sm">
                            {{ __('Enter your work email...') }}
                        </div>
                        <button class="w-full py-4 bg-primary text-white rounded-xl font-black transition hover:opacity-90">
                            {{ $config['button_primary_text'] ?? __('Get Started') }}
                        </button>
                    </div>
                    <p class="mt-4 text-center text-xs text-zinc-500">{{ __('By signing up, you agree to our Terms of Service.') }}</p>
                </div>
            </div>
        @endif
    </div>
</section>
