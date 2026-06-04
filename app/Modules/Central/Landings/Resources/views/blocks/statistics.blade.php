@props([
    'config' => [],
    'styles' => [],
    'variant' => 'horizontal',
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

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'horizontal')
            <div class="flex flex-wrap justify-center gap-12 md:gap-24">
                @foreach($config['stats'] ?? [] as $stat)
                    <div class="text-center">
                        <div class="text-4xl md:text-5xl font-extrabold mb-2 flex items-center justify-center">
                            @if($stat['prefix'] ?? null) <span class="text-2xl md:text-3xl mr-1 opacity-70">{{ $stat['prefix'] }}</span> @endif
                            <span>{{ $stat['value'] ?? '0' }}</span>
                            @if($stat['suffix'] ?? null) <span class="text-2xl md:text-3xl ml-1 opacity-70">{{ $stat['suffix'] }}</span> @endif
                        </div>
                        <div class="text-sm md:text-base font-medium opacity-60 uppercase tracking-widest">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'grid')
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
                @foreach($config['stats'] ?? [] as $stat)
                    <div class="bg-white/5 p-8 rounded-3xl border border-current/10 text-center">
                        @if($stat['icon'] ?? null)
                            <div class="mb-4 flex justify-center text-primary">
                                <flux:icon :icon="$stat['icon']" size="lg" />
                            </div>
                        @endif
                        <div class="text-4xl font-extrabold mb-2">
                            {{ ($stat['prefix'] ?? '') . ($stat['value'] ?? '0') . ($stat['suffix'] ?? '') }}
                        </div>
                        <div class="text-sm opacity-60 font-bold uppercase tracking-wider">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'highlighted')
            <div class="flex flex-col md:flex-row items-center justify-between gap-12">
                @php $highlight = ($config['stats'] ?? [])[0] ?? null; @endphp
                @if($highlight)
                    <div class="text-center md:text-left">
                        <div class="text-6xl md:text-8xl font-black text-primary mb-2">
                            {{ ($highlight['prefix'] ?? '') . ($highlight['value'] ?? '0') . ($highlight['suffix'] ?? '') }}
                        </div>
                        <div class="text-xl md:text-2xl font-bold opacity-80">{{ $highlight['label'] ?? '' }}</div>
                    </div>
                @endif
                
                <div class="grid grid-cols-2 gap-8 md:gap-16">
                    @foreach(array_slice($config['stats'] ?? [], 1) as $stat)
                        <div class="text-center md:text-left">
                            <div class="text-3xl font-extrabold mb-1">
                                {{ ($stat['prefix'] ?? '') . ($stat['value'] ?? '0') . ($stat['suffix'] ?? '') }}
                            </div>
                            <div class="text-sm opacity-60 font-medium uppercase">{{ $stat['label'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
