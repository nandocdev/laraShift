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
        'surface' => 'bg-surface text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-surface'
    };

    $columnsCount = $config['columns_count'] ?? match($variant) {
        'grid' => 3,
        default => 3
    };

    $columnsClass = match((int)$columnsCount) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3'
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

        @if($variant === 'single-featured')
            <div class="max-w-4xl mx-auto bg-white dark:bg-zinc-800 rounded-[2.5rem] p-8 md:p-16 shadow-xl border border-zinc-100 dark:border-zinc-700 flex flex-col md:flex-row items-center gap-12 relative overflow-hidden">
                <div class="absolute top-10 left-10 opacity-[0.05] pointer-events-none">
                    <flux:icon.chat-bubble-bottom-center-text size="xl" class="scale-[4] text-primary" />
                </div>
                
                @php $testimonial = ($config['testimonials'] ?? [])[0] ?? null; @endphp
                @if($testimonial)
                    <div class="w-40 h-40 md:w-56 md:h-56 flex-shrink-0 relative">
                        <div class="absolute -inset-2 bg-primary/10 rounded-full rotate-6"></div>
                        @if(($config['show_avatars'] ?? true) && ($testimonial['avatar_url'] ?? null))
                            <img src="{{ $testimonial['avatar_url'] }}" alt="{{ $testimonial['name'] }}" class="w-full h-full rounded-full object-cover shadow-lg relative">
                        @else
                            <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-5xl font-black relative">
                                {{ substr($testimonial['name'] ?? 'U', 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 text-center md:text-left relative">
                        @if($config['show_rating'] ?? true)
                            <div class="flex gap-1 mb-6 text-amber-400 justify-center md:justify-start">
                                @for($i = 0; $i < (int)($testimonial['rating'] ?? 5); $i++)
                                    <flux:icon.star variant="solid" size="sm" />
                                @endfor
                            </div>
                        @endif

                        <blockquote class="text-2xl md:text-3xl font-medium mb-8 italic leading-relaxed text-zinc-800 dark:text-zinc-100">
                            "{{ $testimonial['quote'] ?? '' }}"
                        </blockquote>
                        
                        <div>
                            <p class="text-xl font-black text-primary">{{ $testimonial['name'] ?? '' }}</p>
                            <p class="text-base font-bold opacity-50">{{ $testimonial['role'] ?? '' }} {{ $testimonial['company'] ? '@ ' . $testimonial['company'] : '' }}</p>
                        </div>
                    </div>
                @endif
            </div>

        @elseif($variant === 'carousel')
            <div x-data="{ 
                current: 0, 
                count: {{ count($config['testimonials'] ?? []) }},
                autoplay: {{ ($config['autoplay'] ?? false) ? 'true' : 'false' }},
                init() {
                    if (this.autoplay) {
                        setInterval(() => { this.current = (this.current + 1) % this.count }, 5000)
                    }
                }
            }" class="relative max-w-4xl mx-auto">
                <div class="overflow-hidden rounded-[2.5rem] bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 shadow-xl">
                    <div class="flex transition-transform duration-700 ease-in-out" :style="'transform: translateX(-' + (current * 100) + '%)'">
                        @foreach($config['testimonials'] ?? [] as $testimonial)
                            <div class="w-full flex-shrink-0 p-8 md:p-16 text-center">
                                <div class="w-20 h-20 mx-auto mb-8 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center">
                                    @if($testimonial['avatar_url'] ?? null)
                                        <img src="{{ $testimonial['avatar_url'] }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="text-primary font-black text-2xl">{{ substr($testimonial['name'] ?? 'U', 0, 1) }}</span>
                                    @endif
                                </div>
                                <blockquote class="text-xl md:text-2xl font-medium mb-8 italic leading-relaxed">"{{ $testimonial['quote'] ?? '' }}"</blockquote>
                                <p class="font-black text-primary">{{ $testimonial['name'] }}</p>
                                <p class="text-sm font-bold opacity-40">{{ $testimonial['role'] ?? '' }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if(count($config['testimonials'] ?? []) > 1)
                    <div class="flex justify-center gap-2 mt-8">
                        @foreach($config['testimonials'] ?? [] as $index => $t)
                            <button 
                                x-on:click="current = {{ $index }}"
                                class="h-2 rounded-full transition-all duration-300"
                                :class="current === {{ $index }} ? 'w-8 bg-primary' : 'w-2 bg-zinc-300 dark:bg-zinc-700'"
                            ></button>
                        @endforeach
                    </div>
                @endif
            </div>

        @else
            <div class="grid gap-8 md:gap-10 {{ $columnsClass }}">
                @foreach($config['testimonials'] ?? [] as $testimonial)
                    <div class="bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-sm border border-zinc-100 dark:border-zinc-700 flex flex-col h-full hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        @if($config['show_rating'] ?? true)
                            <div class="flex gap-1 mb-6 text-amber-400">
                                @for($i = 0; $i < (int)($testimonial['rating'] ?? 5); $i++)
                                    <flux:icon.star variant="solid" size="xs" />
                                @endfor
                            </div>
                        @endif

                        <blockquote class="text-lg opacity-90 italic flex-1 leading-relaxed mb-8">"{{ $testimonial['quote'] ?? '' }}"</blockquote>
                        
                        <div class="flex items-center gap-4 mt-auto">
                            @if($config['show_avatars'] ?? true)
                                <div class="w-12 h-12 flex-shrink-0">
                                    @if($testimonial['avatar_url'] ?? null)
                                        <img src="{{ $testimonial['avatar_url'] }}" alt="{{ $testimonial['name'] }}" class="w-full h-full rounded-full object-cover ring-2 ring-zinc-50 dark:ring-zinc-700">
                                    @else
                                        <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-black">
                                            {{ substr($testimonial['name'] ?? 'U', 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <div>
                                <p class="text-sm font-black tracking-tight">{{ $testimonial['name'] ?? '' }}</p>
                                <p class="text-xs font-bold opacity-50">{{ $testimonial['role'] ?? '' }} {{ $testimonial['company'] ? '@ ' . $testimonial['company'] : '' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
