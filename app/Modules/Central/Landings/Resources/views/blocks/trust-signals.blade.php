@props([
    'config' => [],
    'styles' => [],
    'variant' => 'logo-strip',
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

    $isGrayscale = $config['grayscale'] ?? true;
    $showHoverColor = $config['show_hover_color'] ?? true;
    
    $filterClass = $isGrayscale ? 'grayscale opacity-50' : 'opacity-80';
    $hoverClass = ($isGrayscale && $showHoverColor) ? 'hover:grayscale-0 hover:opacity-100' : 'hover:opacity-100';

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($config['section_title'] ?? null)
            <div class="text-center mb-12">
                <h3 class="text-sm font-black uppercase tracking-widest opacity-40">{{ $config['section_title'] }}</h3>
            </div>
        @endif

        @if($variant === 'logo-strip')
            <div class="flex flex-wrap justify-center items-center gap-10 md:gap-20">
                @foreach($config['items'] ?? [] as $item)
                    @if($item['url'] ?? null)
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="h-8 md:h-12 {{ $filterClass }} {{ $hoverClass }} transition-all duration-300 transform hover:scale-105">
                    @else
                        <div class="h-8 md:h-12 {{ $filterClass }} {{ $hoverClass }} transition-all duration-300">
                    @endif

                        @if($item['logo_url'] ?? null)
                            <img src="{{ $item['logo_url'] }}" alt="{{ $item['alt'] ?? '' }}" class="h-full w-auto object-contain">
                        @else
                            <div class="font-black text-2xl md:text-3xl tracking-tighter flex items-center h-full">
                                {{ $item['alt'] ?? 'Company' }}
                            </div>
                        @endif

                    @if($item['url'] ?? null)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>

        @elseif($variant === 'certifications')
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                @foreach($config['items'] ?? [] as $item)
                    @if($item['url'] ?? null)
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="group flex flex-col items-center text-center p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-3xl border border-zinc-100 dark:border-zinc-700 hover:shadow-xl hover:bg-white dark:hover:bg-zinc-800 hover:-translate-y-1 transition-all duration-300">
                    @else
                        <div class="group flex flex-col items-center text-center p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-3xl border border-zinc-100 dark:border-zinc-700 hover:shadow-xl hover:bg-white dark:hover:bg-zinc-800 hover:-translate-y-1 transition-all duration-300">
                    @endif

                        <div class="w-20 h-20 mb-6 flex items-center justify-center {{ $filterClass }} {{ $hoverClass }} transition-all duration-300">
                            @if($item['logo_url'] ?? null)
                                <img src="{{ $item['logo_url'] }}" alt="{{ $item['alt'] ?? '' }}" class="max-h-full object-contain">
                            @else
                                <flux:icon.shield-check size="xl" class="text-zinc-400 group-hover:text-primary transition-colors" />
                            @endif
                        </div>
                        <h4 class="font-black text-base mb-2 text-zinc-900 dark:text-white">{{ $item['alt'] ?? 'Certification' }}</h4>
                        @if($item['description'] ?? null)
                            <p class="text-sm opacity-60 leading-relaxed max-w-[200px]">{{ $item['description'] }}</p>
                        @endif

                    @if($item['url'] ?? null)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
