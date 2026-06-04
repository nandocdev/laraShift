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
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
    };

    $columns = match($config['columns'] ?? 3) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
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
            </div>
        @endif

        @if($variant === 'grid' || $variant === 'masonry')
            <div class="grid {{ $columns }} gap-4 md:gap-8">
                @foreach($config['images'] ?? [] as $image)
                    <div class="group relative overflow-hidden rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300">
                        <img 
                            src="{{ $image['url'] ?? '' }}" 
                            alt="{{ $image['alt'] ?? '' }}" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                            loading="lazy"
                        >
                        @if(($config['show_captions'] ?? false) && ($image['caption'] ?? null))
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex items-end p-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                <p class="text-white text-sm font-medium">{{ $image['caption'] }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'carousel')
            <div class="relative overflow-hidden" x-data="{ current: 0, count: {{ count($config['images'] ?? []) }} }">
                <div class="flex transition-transform duration-500 ease-out" :style="'transform: translateX(-' + (current * 100) + '%)'">
                    @foreach($config['images'] ?? [] as $image)
                        <div class="w-full flex-shrink-0 px-4">
                            <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? '' }}" class="w-full h-[500px] object-cover rounded-3xl shadow-lg">
                        </div>
                    @endforeach
                </div>
                
                @if(count($config['images'] ?? []) > 1)
                    <div class="absolute inset-y-0 left-8 flex items-center">
                        <button x-on:click="current = current === 0 ? count - 1 : current - 1" class="p-3 bg-white/20 hover:bg-white/40 backdrop-blur-md rounded-full text-white transition">
                            <flux:icon.chevron-left />
                        </button>
                    </div>
                    <div class="absolute inset-y-0 right-8 flex items-center">
                        <button x-on:click="current = current === count - 1 ? 0 : current + 1" class="p-3 bg-white/20 hover:bg-white/40 backdrop-blur-md rounded-full text-white transition">
                            <flux:icon.chevron-right />
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
