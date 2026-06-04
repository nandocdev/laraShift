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
        default => 'bg-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'centered')
            <div class="text-center">
                @if($config['badge_text'] ?? null)
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-4">
                        {{ $config['badge_text'] }}
                    </span>
                @endif
                
                <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-6xl">
                    {{ $config['headline'] ?? 'Hero Headline' }}
                </h1>
                
                @if($config['subtitle'] ?? null)
                    <p class="mt-3 max-w-md mx-auto text-base sm:text-lg md:mt-5 md:text-xl md:max-w-3xl opacity-90">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
                
                <div class="mt-5 max-w-md mx-auto sm:flex sm:justify-center md:mt-8">
                    @if($config['button_primary_text'] ?? null)
                        <div class="rounded-md shadow">
                            <a href="{{ $config['button_primary_url'] ?? '#' }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:opacity-90 md:py-4 md:text-lg md:px-10">
                                {{ $config['button_primary_text'] }}
                            </a>
                        </div>
                    @endif
                    
                    @if($config['button_secondary_text'] ?? null)
                        <div class="mt-3 rounded-md shadow sm:mt-0 sm:ml-3">
                            <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="w-full flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-primary bg-white hover:bg-gray-50 md:py-4 md:text-lg md:px-10">
                                {{ $config['button_secondary_text'] }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        @elseif($variant === 'split')
            <div class="lg:grid lg:grid-cols-12 lg:gap-8">
                <div class="sm:text-center md:max-w-2xl md:mx-auto lg:col-span-6 lg:text-left">
                    @if($config['badge_text'] ?? null)
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-4">
                            {{ $config['badge_text'] }}
                        </span>
                    @endif
                    
                    <h1 class="text-4xl tracking-tight font-extrabold sm:text-5xl md:text-6xl">
                        {{ $config['headline'] ?? 'Hero Headline' }}
                    </h1>
                    
                    @if($config['subtitle'] ?? null)
                        <p class="mt-3 text-base sm:text-lg md:mt-5 md:text-xl opacity-90">
                            {{ $config['subtitle'] }}
                        </p>
                    @endif
                    
                    <div class="mt-8 sm:max-w-lg sm:mx-auto sm:text-center lg:text-left lg:mx-0">
                        <div class="flex flex-col sm:flex-row gap-3">
                            @if($config['button_primary_text'] ?? null)
                                <a href="{{ $config['button_primary_url'] ?? '#' }}" class="flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:opacity-90 md:py-4 md:text-lg md:px-10">
                                    {{ $config['button_primary_text'] }}
                                </a>
                            @endif
                            
                            @if($config['button_secondary_text'] ?? null)
                                <a href="{{ $config['button_secondary_url'] ?? '#' }}" class="flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-primary bg-white hover:bg-gray-50 md:py-4 md:text-lg md:px-10">
                                    {{ $config['button_secondary_text'] }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="mt-12 relative sm:max-w-lg sm:mx-auto lg:mt-0 lg:max-w-none lg:mx-0 lg:col-span-6 lg:flex lg:items-center">
                    <div class="relative mx-auto w-full rounded-lg shadow-lg lg:max-w-md">
                        @if($config['image_url'] ?? null)
                            <img class="w-full rounded-lg" src="{{ $config['image_url'] }}" alt="{{ $config['image_alt'] ?? '' }}">
                        @else
                            <div class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center text-gray-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
