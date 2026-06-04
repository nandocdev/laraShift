@props([
    'config' => [],
    'styles' => [],
    'variant' => '3-columns',
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

    $columns = match($variant) {
        '4-columns' => 'md:grid-cols-2 lg:grid-cols-4',
        '2-columns' => 'md:grid-cols-2',
        default => 'md:grid-cols-3'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($config['section_title'] ?? null)
            <div class="text-center mb-16">
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

        @if($variant === 'alternating-rows')
            <div class="space-y-24">
                @foreach($config['features'] ?? [] as $index => $feature)
                    <div class="flex flex-col lg:flex-row items-center gap-12 {{ $index % 2 !== 0 ? 'lg:flex-row-reverse' : '' }}">
                        <div class="flex-1">
                            @if($feature['icon'] ?? null)
                                <div class="mb-4 text-primary">
                                    <flux:icon :icon="$feature['icon']" size="lg" />
                                </div>
                            @endif
                            <h3 class="text-2xl font-bold mb-4">{{ $feature['title'] ?? '' }}</h3>
                            <p class="text-lg opacity-80 mb-6">{{ $feature['description'] ?? '' }}</p>
                            @if($feature['cta_text'] ?? null)
                                <a href="{{ $feature['cta_url'] ?? '#' }}" class="inline-flex items-center text-primary font-semibold hover:underline">
                                    {{ $feature['cta_text'] }}
                                    <flux:icon.arrow-right class="ml-2" size="sm" />
                                </a>
                            @endif
                        </div>
                        <div class="flex-1 w-full">
                            @if($feature['image_url'] ?? null)
                                <img src="{{ $feature['image_url'] }}" alt="{{ $feature['title'] ?? '' }}" class="rounded-xl shadow-lg w-full">
                            @else
                                <div class="bg-gray-100 rounded-xl aspect-video flex items-center justify-center text-gray-400">
                                    <flux:icon.photo size="xl" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="grid gap-12 {{ $columns }}">
                @foreach($config['features'] ?? [] as $feature)
                    <div class="{{ $variant === 'cards' ? 'bg-white dark:bg-zinc-800 p-8 rounded-2xl shadow-sm border border-zinc-100 dark:border-zinc-700' : '' }}">
                        <div class="flex flex-col {{ ($config['text_align'] ?? 'left') === 'center' ? 'items-center text-center' : '' }}">
                            @if($feature['icon'] ?? null)
                                <div class="mb-5 flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 text-primary">
                                    <flux:icon :icon="$feature['icon']" />
                                </div>
                            @endif
                            <h3 class="text-xl font-bold mb-3">{{ $feature['title'] ?? '' }}</h3>
                            <p class="opacity-80">{{ $feature['description'] ?? '' }}</p>
                            
                            @if($feature['cta_text'] ?? null)
                                <a href="{{ $feature['cta_url'] ?? '#' }}" class="mt-4 inline-flex items-center text-sm font-semibold text-primary hover:underline">
                                    {{ $feature['cta_text'] }}
                                    <flux:icon.arrow-right class="ml-1" size="xs" />
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
