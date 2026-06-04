@props([
    'config' => [],
    'styles' => [],
    'variant' => 'centered',
])

@php
    $padding = match($styles['padding'] ?? 'lg') {
        'md' => 'py-12',
        'lg' => 'py-20',
        'xl' => 'py-24',
        default => 'py-20'
    };
    
    $bgClass = match($styles['background'] ?? 'primary') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-primary text-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-extrabold sm:text-4xl">
            <span class="block">{{ $config['headline'] ?? 'Ready to dive in?' }}</span>
        </h2>
        
        @if($config['description'] ?? null)
            <p class="mt-4 text-lg leading-6 opacity-90">
                {{ $config['description'] }}
            </p>
        @endif
        
        <div class="mt-8 flex justify-center gap-3">
            @if($config['button_primary_text'] ?? null)
                <a href="{{ $config['button_primary_url'] ?? '#' }}" class="inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-primary bg-white hover:bg-gray-50">
                    {{ $config['button_primary_text'] }}
                </a>
            @endif
            
            @if($config['button_secondary_text'] ?? null)
                <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="inline-flex items-center justify-center px-5 py-3 border border-white text-base font-medium rounded-md text-white hover:bg-white/10">
                    {{ $config['button_secondary_text'] }}
                </a>
            @endif
        </div>
        
        @if($config['show_guarantee'] ?? false)
            <p class="mt-4 text-sm opacity-75">
                {{ $config['guarantee_text'] ?? 'No credit card required.' }}
            </p>
        @endif
    </div>
</section>
