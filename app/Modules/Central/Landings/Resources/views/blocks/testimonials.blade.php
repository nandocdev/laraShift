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
    
    $bgClass = match($styles['background'] ?? 'surface') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-surface'
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

        @if($variant === 'single-featured')
            <div class="max-w-4xl mx-auto bg-white dark:bg-zinc-800 rounded-3xl p-12 shadow-sm border border-zinc-100 dark:border-zinc-700 flex flex-col md:flex-row items-center gap-12">
                @php $testimonial = ($config['testimonials'] ?? [])[0] ?? null; @endphp
                @if($testimonial)
                    <div class="w-32 h-32 flex-shrink-0">
                        @if($testimonial['avatar_url'] ?? null)
                            <img src="{{ $testimonial['avatar_url'] }}" alt="{{ $testimonial['name'] }}" class="w-full h-full rounded-full object-cover">
                        @else
                            <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-3xl font-bold">
                                {{ substr($testimonial['name'] ?? 'U', 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 text-center md:text-left">
                        <flux:icon.chat-bubble-bottom-center-text class="mb-4 text-primary opacity-20" size="xl" />
                        <blockquote class="text-2xl font-medium mb-6 italic">"{{ $testimonial['quote'] ?? '' }}"</blockquote>
                        <div>
                            <p class="text-lg font-bold">{{ $testimonial['name'] ?? '' }}</p>
                            <p class="text-sm opacity-60">{{ $testimonial['role'] ?? '' }} {{ $testimonial['company'] ? '@ ' . $testimonial['company'] : '' }}</p>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach($config['testimonials'] ?? [] as $testimonial)
                    <div class="bg-white dark:bg-zinc-800 p-8 rounded-2xl shadow-sm border border-zinc-100 dark:border-zinc-700 flex flex-col h-full">
                        <div class="flex items-center gap-4 mb-6">
                            <div class="w-12 h-12 flex-shrink-0">
                                @if($testimonial['avatar_url'] ?? null)
                                    <img src="{{ $testimonial['avatar_url'] }}" alt="{{ $testimonial['name'] }}" class="w-full h-full rounded-full object-cover">
                                @else
                                    <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-lg font-bold">
                                        {{ substr($testimonial['name'] ?? 'U', 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ $testimonial['name'] ?? '' }}</p>
                                <p class="text-xs opacity-60">{{ $testimonial['role'] ?? '' }}</p>
                            </div>
                        </div>

                        @if($config['show_rating'] ?? false)
                            <div class="flex gap-1 mb-4 text-amber-400">
                                @for($i = 0; $i < 5; $i++)
                                    <flux:icon.star variant="solid" size="xs" />
                                @endfor
                            </div>
                        @endif

                        <blockquote class="text-base opacity-90 italic flex-1">"{{ $testimonial['quote'] ?? '' }}"</blockquote>
                        
                        @if($testimonial['company'] ?? null)
                            <div class="mt-6 pt-6 border-t border-zinc-50 dark:border-zinc-700">
                                <span class="text-xs font-bold uppercase tracking-widest opacity-40">{{ $testimonial['company'] }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
