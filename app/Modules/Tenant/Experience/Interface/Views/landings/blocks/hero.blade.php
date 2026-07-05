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
    
    $bgClass = match($styles['background'] ?? 'white') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white',
        default => 'bg-white'
    };

    $textAlign = $styles['text_align'] ?? ($variant === 'centered' ? 'center' : 'left');
    $alignmentClass = match($textAlign) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left'
    };

    $isFullscreen = ($variant === 'fullscreen' || ($styles['height'] ?? '') === 'screen');
    $containerClass = $isFullscreen ? 'min-h-screen flex items-center' : '';

    $overlayOpacity = (int) ($styles['overlay_opacity'] ?? 50);
@endphp

<section class="relative overflow-hidden {{ $bgClass }} {{ ! $isFullscreen ? $padding : '' }} {{ $containerClass }}">
    @if($variant === 'bg-image' && ($config['image_url'] ?? null))
        <div class="absolute inset-0 z-0">
            <img src="{{ $config['image_url'] }}" class="w-full h-full object-cover" alt="{{ $config['image_alt'] ?? '' }}">
            <div class="absolute inset-0 bg-black" style="opacity: {{ $overlayOpacity / 100 }}"></div>
        </div>
    @endif

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        @if($variant === 'centered' || $variant === 'bg-image' || $variant === 'fullscreen')
            <div class="max-w-4xl mx-auto {{ $alignmentClass }}">
                @if($config['badge_text'] ?? null)
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-6 border border-primary/20">
                        {{ $config['badge_text'] }}
                    </span>
                @endif
                
                <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-7xl leading-tight">
                    {{ $config['headline'] ?? 'Hero Headline' }}
                </h1>
                
                @if($config['subtitle'] ?? null)
                    <p class="mt-6 text-lg sm:text-xl opacity-90 max-w-2xl {{ $textAlign === 'center' ? 'mx-auto' : ($textAlign === 'right' ? 'ml-auto' : '') }}">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
                
                <div class="mt-10 flex flex-wrap gap-4 {{ $textAlign === 'center' ? 'justify-center' : ($textAlign === 'right' ? 'justify-end' : '') }}">
                    @if($config['button_primary_text'] ?? null)
                        <a href="{{ $config['button_primary_url'] ?? '#' }}" class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-lg hover:opacity-90 transition shadow-lg shadow-primary/20">
                            {{ $config['button_primary_text'] }}
                        </a>
                    @endif
                    
                    @if(($config['show_secondary_button'] ?? true) && ($config['button_secondary_text'] ?? null))
                        <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="px-8 py-4 bg-white/10 backdrop-blur-md border border-current/20 rounded-xl font-bold text-lg hover:bg-white/20 transition">
                            {{ $config['button_secondary_text'] }}
                        </a>
                    @endif
                </div>

                @if(($config['show_stats'] ?? false) && ($config['stats'] ?? null))
                    <div class="mt-16 pt-8 border-t border-current/10 flex flex-wrap gap-12 {{ $textAlign === 'center' ? 'justify-center' : ($textAlign === 'right' ? 'justify-end' : '') }}">
                        @foreach($config['stats'] as $stat)
                            <div>
                                <div class="text-3xl font-black">{{ $stat['value'] }}</div>
                                <div class="text-xs uppercase tracking-widest opacity-60 font-bold">{{ $stat['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        @elseif($variant === 'split' || $variant === 'image-left')
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="{{ $variant === 'image-left' ? 'lg:order-2' : '' }} {{ $alignmentClass }}">
                    @if($config['badge_text'] ?? null)
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-6">
                            {{ $config['badge_text'] }}
                        </span>
                    @endif
                    
                    <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-6xl leading-tight">
                        {{ $config['headline'] ?? 'Hero Headline' }}
                    </h1>
                    
                    @if($config['subtitle'] ?? null)
                        <p class="mt-6 text-lg opacity-80 leading-relaxed">
                            {{ $config['subtitle'] }}
                        </p>
                    @endif
                    
                    <div class="mt-10 flex flex-wrap gap-4">
                        @if($config['button_primary_text'] ?? null)
                            <a href="{{ $config['button_primary_url'] ?? '#' }}" class="px-8 py-4 bg-primary text-white rounded-xl font-bold hover:opacity-90 transition">
                                {{ $config['button_primary_text'] }}
                            </a>
                        @endif
                        @if(($config['show_secondary_button'] ?? true) && ($config['button_secondary_text'] ?? null))
                            <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="px-8 py-4 bg-zinc-100 dark:bg-zinc-800 rounded-xl font-bold hover:bg-zinc-200 dark:hover:bg-zinc-700 transition">
                                {{ $config['button_secondary_text'] }}
                            </a>
                        @endif
                    </div>
                </div>

                <div class="{{ $variant === 'image-left' ? 'lg:order-1' : '' }}">
                    <div class="relative">
                        <div class="absolute -inset-4 bg-primary/10 rounded-[3rem] rotate-3 -z-10"></div>
                        @if($config['image_url'] ?? null)
                            <img class="w-full rounded-[2.5rem] shadow-2xl" src="{{ $config['image_url'] }}" alt="{{ $config['image_alt'] ?? '' }}">
                        @else
                            <div class="aspect-square bg-zinc-100 dark:bg-zinc-800 rounded-[2.5rem] flex items-center justify-center text-zinc-400">
                                <flux:icon.photo size="xl" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
