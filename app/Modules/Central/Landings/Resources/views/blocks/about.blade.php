@props([
    'config' => [],
    'styles' => [],
    'variant' => 'image-right',
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
        @if($variant === 'team-intro')
            <div class="text-center mb-16">
                <h2 class="text-3xl font-extrabold sm:text-4xl">
                    {{ $config['section_title'] ?? __('Meet our team') }}
                </h2>
                @if($config['section_subtitle'] ?? null)
                    <p class="mt-4 text-lg opacity-80 max-w-2xl mx-auto">
                        {{ $config['section_subtitle'] }}
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-12">
                @foreach($config['team_members'] ?? [] as $member)
                    <div class="text-center">
                        <div class="relative mb-6 inline-block">
                            <div class="w-32 h-32 md:w-40 md:h-40 rounded-3xl overflow-hidden bg-zinc-100 shadow-sm border border-zinc-50 dark:border-zinc-700">
                                @if($member['avatar_url'] ?? null)
                                    <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="w-full h-full object-cover">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-zinc-300">
                                        <flux:icon.user size="xl" />
                                    </div>
                                @endif
                            </div>
                        </div>
                        <h3 class="font-bold text-lg">{{ $member['name'] ?? '' }}</h3>
                        <p class="text-sm opacity-60 font-medium">{{ $member['role'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col lg:flex-row items-center gap-16 {{ $variant === 'image-left' ? 'lg:flex-row-reverse' : '' }}">
                <div class="flex-1 space-y-6">
                    @if($config['section_title'] ?? null)
                        <h2 class="text-3xl font-extrabold sm:text-4xl">
                            {{ $config['section_title'] }}
                        </h2>
                    @endif
                    
                    <div class="text-lg opacity-80 leading-relaxed space-y-4">
                        {!! nl2br(e($config['description'] ?? 'About description goes here.')) !!}
                    </div>

                    @if($config['metrics'] ?? null)
                        <div class="grid grid-cols-2 gap-8 pt-6">
                            @foreach($config['metrics'] as $metric)
                                <div>
                                    <div class="text-3xl font-extrabold text-primary">{{ $metric['value'] ?? '0' }}</div>
                                    <div class="text-sm opacity-60 font-medium">{{ $metric['label'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($config['show_cta'] ?? false)
                        <div class="pt-6">
                            <a href="{{ $config['cta_url'] ?? '#' }}" class="inline-flex items-center px-6 py-3 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition">
                                {{ $config['cta_text'] ?? __('Learn More') }}
                            </a>
                        </div>
                    @endif
                </div>

                @if($variant !== 'story')
                    <div class="flex-1 w-full">
                        <div class="relative">
                            <div class="absolute -inset-4 bg-primary/10 rounded-[2.5rem] rotate-3 -z-10"></div>
                            <div class="rounded-[2rem] overflow-hidden shadow-2xl border border-zinc-100 dark:border-zinc-700">
                                @if($config['image_url'] ?? null)
                                    <img src="{{ $config['image_url'] }}" alt="{{ $config['image_alt'] ?? '' }}" class="w-full h-full object-cover">
                                @else
                                    <div class="aspect-[4/3] bg-zinc-50 flex items-center justify-center text-zinc-300">
                                        <flux:icon.photo size="xl" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
