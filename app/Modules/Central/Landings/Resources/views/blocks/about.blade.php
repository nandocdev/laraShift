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
        'surface' => 'bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-white'
    };

    $textAlign = $styles['text_align'] ?? 'left';
    $alignmentClass = match($textAlign) {
        'center' => 'text-center items-center',
        'right' => 'text-right items-end',
        default => 'text-left items-start'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'team-intro')
            <div class="text-center mb-20">
                @if($config['headline'] ?? null)
                    <h2 class="text-3xl font-extrabold sm:text-5xl tracking-tight mb-4">
                        {{ $config['headline'] }}
                    </h2>
                @endif
                @if($config['subtitle'] ?? null)
                    <p class="mt-4 text-xl opacity-70 max-w-2xl mx-auto">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-16">
                @foreach($config['team_members'] ?? [] as $member)
                    <div class="text-center group">
                        <div class="relative mb-6 inline-block">
                            <div class="absolute -inset-2 bg-primary/5 rounded-[2rem] group-hover:rotate-6 transition-transform duration-500"></div>
                            <div class="w-32 h-32 md:w-48 md:h-48 rounded-[2rem] overflow-hidden bg-zinc-100 dark:bg-zinc-800 shadow-md border border-zinc-50 dark:border-zinc-700 relative">
                                @if($member['avatar_url'] ?? null)
                                    <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-zinc-300 dark:text-zinc-600">
                                        <flux:icon.user size="xl" />
                                    </div>
                                @endif
                            </div>
                        </div>
                        <h3 class="font-black text-xl tracking-tight">{{ $member['name'] ?? '' }}</h3>
                        <p class="text-sm font-bold text-primary opacity-80 uppercase tracking-widest mt-1">{{ $member['role'] ?? '' }}</p>
                        @if($member['bio'] ?? null)
                            <p class="mt-3 text-sm opacity-60 max-w-[200px] mx-auto leading-relaxed">{{ $member['bio'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col lg:flex-row items-center gap-16 md:gap-24 {{ $variant === 'image-left' ? 'lg:flex-row-reverse' : '' }}">
                <div class="flex-1 flex flex-col {{ $alignmentClass }} space-y-8">
                    <div class="space-y-4">
                        @if($config['headline'] ?? null)
                            <h2 class="text-3xl font-extrabold sm:text-5xl tracking-tight leading-tight">
                                {{ $config['headline'] }}
                            </h2>
                        @endif
                        @if($config['subtitle'] ?? null)
                            <p class="text-xl font-bold text-primary opacity-90 uppercase tracking-widest">
                                {{ $config['subtitle'] }}
                            </p>
                        @endif
                    </div>
                    
                    <div class="text-lg opacity-70 leading-relaxed space-y-4">
                        {!! nl2br(e($config['description'] ?? '')) !!}
                    </div>

                    @if($config['metrics'] ?? null)
                        <div class="grid grid-cols-2 gap-x-12 gap-y-8 pt-4 w-full">
                            @foreach($config['metrics'] as $metric)
                                <div class="border-l-4 border-primary/20 pl-6">
                                    <div class="text-4xl font-black text-primary tracking-tighter">{{ $metric['value'] ?? '0' }}</div>
                                    <div class="text-sm font-bold opacity-50 uppercase tracking-wider mt-1">{{ $metric['label'] ?? '' }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($config['show_cta'] ?? false)
                        <div class="pt-4">
                            <a 
                                href="{{ $config['cta_url'] ?? '#' }}" 
                                target="{{ $config['cta_target'] ?? '_self' }}"
                                class="inline-flex items-center px-8 py-4 bg-primary text-white font-black rounded-2xl hover:opacity-90 transition shadow-lg shadow-primary/20 hover:scale-[1.02]"
                            >
                                {{ $config['cta_text'] ?? __('Learn More') }}
                                <flux:icon.arrow-right class="ml-2" size="xs" />
                            </a>
                        </div>
                    @endif
                </div>

                @if($variant !== 'story')
                    <div class="flex-1 w-full relative">
                        <div class="absolute -inset-4 bg-primary/5 rounded-[3rem] rotate-3 -z-10"></div>
                        <div class="absolute -inset-4 border-2 border-primary/10 rounded-[3rem] -rotate-2 -z-10"></div>
                        <div class="rounded-[2.5rem] overflow-hidden shadow-2xl border border-zinc-100 dark:border-zinc-700 aspect-[4/5] lg:aspect-auto">
                            @if($config['image_url'] ?? null)
                                <img src="{{ $config['image_url'] }}" alt="{{ $config['image_alt'] ?? '' }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-[500px] bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                    <flux:icon.photo size="xl" />
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
