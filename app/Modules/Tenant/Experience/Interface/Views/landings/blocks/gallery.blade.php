@props([
    'config' => [],
    'styles' => [],
    'variant' => 'grid',
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

    $columnsCount = $config['columns_count'] ?? 3;
    $columnsClass = match((int)$columnsCount) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);
    
    $aspectRatio = $styles['aspect_ratio'] ?? 'aspect-square'; // aspect-square | aspect-video | aspect-auto
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}">
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
            </div>
        @endif

        @if($variant === 'grid')
            <div class="grid gap-6 md:gap-8 {{ $columnsClass }}">
                @foreach($config['images'] ?? [] as $image)
                    <div class="group relative overflow-hidden rounded-[2rem] shadow-sm hover:shadow-xl transition-all duration-500 {{ $aspectRatio }}">
                        @if($image['url'] ?? null)
                            <img 
                                src="{{ $image['url'] }}" 
                                alt="{{ $image['alt'] ?? '' }}" 
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full h-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                <flux:icon.photo size="xl" />
                            </div>
                        @endif
                        
                        @if(($config['show_captions'] ?? false) && ($image['caption'] ?? null))
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <p class="text-white font-bold text-lg translate-y-4 group-hover:translate-y-0 transition-transform duration-300">{{ $image['caption'] }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'masonry')
            <div class="columns-1 sm:columns-2 lg:columns-{{ $columnsCount }} gap-6 md:gap-8 space-y-6 md:space-y-8">
                @foreach($config['images'] ?? [] as $image)
                    <div class="group relative overflow-hidden rounded-[2rem] shadow-sm hover:shadow-xl transition-all duration-500 break-inside-avoid">
                        @if($image['url'] ?? null)
                            <img 
                                src="{{ $image['url'] }}" 
                                alt="{{ $image['alt'] ?? '' }}" 
                                class="w-full h-auto object-cover transition-transform duration-700 group-hover:scale-110"
                                loading="lazy"
                            >
                        @else
                            <div class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                <flux:icon.photo size="xl" />
                            </div>
                        @endif
                        
                        @if(($config['show_captions'] ?? false) && ($image['caption'] ?? null))
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <p class="text-white font-bold text-lg translate-y-4 group-hover:translate-y-0 transition-transform duration-300">{{ $image['caption'] }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'carousel')
            <div x-data="{ 
                current: 0, 
                count: {{ count($config['images'] ?? []) }},
                autoplay: {{ ($config['autoplay'] ?? false) ? 'true' : 'false' }},
                interval: null,
                init() {
                    if (this.autoplay && this.count > 1) {
                        this.startAutoplay();
                    }
                },
                startAutoplay() {
                    this.interval = setInterval(() => { this.next() }, 5000);
                },
                stopAutoplay() {
                    if (this.interval) clearInterval(this.interval);
                },
                next() {
                    this.current = (this.current + 1) % this.count;
                },
                prev() {
                    this.current = (this.current - 1 + this.count) % this.count;
                }
            }" 
            x-on:mouseenter="stopAutoplay"
            x-on:mouseleave="startAutoplay"
            class="relative max-w-5xl mx-auto rounded-[3rem] overflow-hidden shadow-2xl border border-zinc-100 dark:border-zinc-700 bg-black group">
                
                <div class="flex transition-transform duration-700 ease-[cubic-bezier(0.25,1,0.5,1)] h-[500px] md:h-[600px]" :style="'transform: translateX(-' + (current * 100) + '%)'">
                    @foreach($config['images'] ?? [] as $image)
                        <div class="w-full h-full flex-shrink-0 relative">
                            @if($image['url'] ?? null)
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? '' }}" class="w-full h-full object-cover opacity-90">
                            @else
                                <div class="w-full h-full bg-zinc-800 flex items-center justify-center text-zinc-600">
                                    <flux:icon.photo size="xl" />
                                </div>
                            @endif
                            
                            @if(($config['show_captions'] ?? false) && ($image['caption'] ?? null))
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent p-10 pt-32">
                                    <p class="text-white font-bold text-2xl md:text-3xl tracking-tight">{{ $image['caption'] }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
                
                @if(count($config['images'] ?? []) > 1)
                    <div class="absolute inset-y-0 left-6 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <button x-on:click="prev" class="p-4 bg-white/10 hover:bg-white/30 backdrop-blur-md rounded-full text-white transition transform hover:scale-110">
                            <flux:icon.chevron-left size="sm" />
                        </button>
                    </div>
                    <div class="absolute inset-y-0 right-6 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <button x-on:click="next" class="p-4 bg-white/10 hover:bg-white/30 backdrop-blur-md rounded-full text-white transition transform hover:scale-110">
                            <flux:icon.chevron-right size="sm" />
                        </button>
                    </div>

                    <div class="absolute bottom-6 left-0 right-0 flex justify-center gap-3">
                        @foreach($config['images'] ?? [] as $index => $i)
                            <button 
                                x-on:click="current = {{ $index }}"
                                class="h-2 rounded-full transition-all duration-500 shadow-sm"
                                :class="current === {{ $index }} ? 'w-10 bg-white' : 'w-2 bg-white/40 hover:bg-white/60'"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
