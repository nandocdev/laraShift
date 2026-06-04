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
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($config['section_title'] ?? null)
            <div class="text-center mb-10">
                <h3 class="text-sm font-bold uppercase tracking-widest opacity-40">{{ $config['section_title'] }}</h3>
            </div>
        @endif

        @if($variant === 'logo-strip')
            <div class="flex flex-wrap justify-center items-center gap-8 md:gap-16">
                @foreach($config['items'] ?? [] as $item)
                    <div class="h-8 md:h-12 grayscale opacity-50 hover:grayscale-0 hover:opacity-100 transition-all duration-300">
                        @if($item['logo_url'] ?? null)
                            <img src="{{ $item['logo_url'] }}" alt="{{ $item['alt'] ?? '' }}" class="h-full w-auto">
                        @else
                            <div class="font-bold text-2xl tracking-tighter">{{ $item['alt'] ?? 'Company' }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'certifications')
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                @foreach($config['items'] ?? [] as $item)
                    <div class="flex flex-col items-center text-center p-6 bg-white/5 rounded-3xl border border-current/10">
                        <div class="w-16 h-16 mb-4 flex items-center justify-center grayscale">
                             @if($item['logo_url'] ?? null)
                                <img src="{{ $item['logo_url'] }}" alt="{{ $item['alt'] ?? '' }}" class="max-h-full">
                            @else
                                <flux:icon.shield-check size="lg" class="opacity-30" />
                            @endif
                        </div>
                        <h4 class="font-bold text-sm mb-1">{{ $item['alt'] ?? '' }}</h4>
                        <p class="text-xs opacity-50">{{ $item['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
