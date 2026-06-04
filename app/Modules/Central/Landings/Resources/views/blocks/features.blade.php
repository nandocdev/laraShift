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
        'surface' => 'bg-surface text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-white'
    };

    $columnsCount = $config['columns_count'] ?? match($variant) {
        '4-columns' => 4,
        '2-columns' => 2,
        default => 3
    };

    $columnsClass = match((int)$columnsCount) {
        1 => 'grid-cols-1 max-w-2xl mx-auto',
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3'
    };

    $textAlign = $styles['text_align'] ?? (($variant === 'cards' || $variant === '3-columns' || $variant === '4-columns') ? 'left' : 'left');
    $alignmentClass = match($textAlign) {
        'center' => 'items-center text-center',
        'right' => 'items-end text-right',
        default => 'items-start text-left'
    };

    $iconStyle = $styles['icon_style'] ?? 'rounded-lg'; // rounded-lg | circle | ghost
    $iconBg = match($iconStyle) {
        'ghost' => '',
        'circle' => 'rounded-full bg-primary/10 text-primary',
        default => 'rounded-xl bg-primary/10 text-primary'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);
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

        @if($variant === 'alternating-rows')
            <div class="space-y-24 md:space-y-32">
                @foreach($config['features'] ?? [] as $index => $feature)
                    <div class="flex flex-col lg:flex-row items-center gap-12 md:gap-20 {{ $index % 2 !== 0 ? 'lg:flex-row-reverse' : '' }}">
                        <div class="flex-1 space-y-6">
                            @if(($config['show_icons'] ?? true) && ($feature['icon'] ?? null))
                                <div class="inline-flex items-center justify-center w-14 h-14 {{ $iconBg }}">
                                    <flux:icon :icon="$feature['icon']" variant="outline" />
                                </div>
                            @endif
                            <h3 class="text-2xl md:text-3xl font-bold">{{ $feature['title'] ?? '' }}</h3>
                            <p class="text-lg opacity-80 leading-relaxed">{{ $feature['description'] ?? '' }}</p>
                            
                            @if($feature['cta_text'] ?? null)
                                <div class="pt-2">
                                    <a 
                                        href="{{ $feature['cta_url'] ?? '#' }}" 
                                        target="{{ $feature['cta_target'] ?? '_self' }}"
                                        class="inline-flex items-center text-primary font-bold group"
                                    >
                                        {{ $feature['cta_text'] }}
                                        <flux:icon.arrow-right class="ml-2 transition-transform group-hover:translate-x-1" size="xs" />
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 w-full">
                            <div class="relative group">
                                <div class="absolute -inset-4 bg-primary/5 rounded-[2rem] transition-transform group-hover:scale-105 -z-10"></div>
                                @if($feature['image_url'] ?? null)
                                    <img src="{{ $feature['image_url'] }}" alt="{{ $feature['title'] ?? '' }}" class="rounded-3xl shadow-xl w-full object-cover">
                                @else
                                    <div class="bg-zinc-100 dark:bg-zinc-800 rounded-3xl aspect-video flex items-center justify-center text-zinc-300">
                                        <flux:icon.photo size="xl" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="grid gap-8 md:gap-12 {{ $columnsClass }}">
                @foreach($config['features'] ?? [] as $feature)
                    <div class="group h-full {{ $variant === 'cards' ? 'bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-sm border border-zinc-100 dark:border-zinc-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300' : '' }}">
                        <div class="flex flex-col h-full {{ $alignmentClass }}">
                            @if(($config['show_icons'] ?? true) && ($feature['icon'] ?? null))
                                <div class="mb-6 flex items-center justify-center w-12 h-12 {{ $iconBg }}">
                                    <flux:icon :icon="$feature['icon']" size="sm" />
                                </div>
                            @endif
                            
                            <h3 class="text-xl font-bold mb-3">{{ $feature['title'] ?? '' }}</h3>
                            <p class="opacity-70 leading-relaxed flex-1">{{ $feature['description'] ?? '' }}</p>
                            
                            @if($feature['cta_text'] ?? null)
                                <a 
                                    href="{{ $feature['cta_url'] ?? '#' }}" 
                                    target="{{ $feature['cta_target'] ?? '_self' }}"
                                    class="mt-6 inline-flex items-center text-sm font-bold text-primary hover:underline"
                                >
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
