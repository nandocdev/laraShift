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
        'surface' => 'bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-white'
    };

    $columnsCount = $config['columns_count'] ?? 4;
    $columnsClass = match((int)$columnsCount) {
        2 => 'grid-cols-2',
        3 => 'grid-cols-2 lg:grid-cols-3',
        6 => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-6',
        default => 'grid-cols-2 lg:grid-cols-4'
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

        @if($variant === 'horizontal')
            <div class="flex flex-wrap justify-center gap-12 md:gap-24">
                @foreach($config['stats'] ?? [] as $stat)
                    <div class="text-center group hover:scale-105 transition-transform duration-300">
                        <div class="text-4xl md:text-6xl font-black mb-2 flex items-center justify-center tracking-tighter">
                            @if($stat['prefix'] ?? null) <span class="text-2xl md:text-3xl mr-1 opacity-50 font-bold">{{ $stat['prefix'] }}</span> @endif
                            <span class="text-primary">{{ $stat['value'] ?? '0' }}</span>
                            @if($stat['suffix'] ?? null) <span class="text-2xl md:text-3xl ml-1 opacity-50 font-bold">{{ $stat['suffix'] }}</span> @endif
                        </div>
                        <div class="text-sm md:text-base font-bold opacity-60 uppercase tracking-widest">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'grid')
            <div class="grid gap-8 {{ $columnsClass }}">
                @foreach($config['stats'] ?? [] as $stat)
                    <div class="bg-white/5 p-8 md:p-10 rounded-[2.5rem] border border-current/10 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        @if($stat['icon'] ?? null)
                            <div class="mb-6 flex justify-center text-primary">
                                <div class="p-4 bg-primary/10 rounded-2xl">
                                    <flux:icon :icon="$stat['icon']" size="lg" />
                                </div>
                            </div>
                        @endif
                        <div class="text-4xl md:text-5xl font-black mb-2 tracking-tighter">
                            @if($stat['prefix'] ?? null) <span class="text-xl opacity-40">{{ $stat['prefix'] }}</span> @endif
                            {{ $stat['value'] ?? '0' }}
                            @if($stat['suffix'] ?? null) <span class="text-xl opacity-40">{{ $stat['suffix'] }}</span> @endif
                        </div>
                        <div class="text-sm opacity-50 font-black uppercase tracking-widest">{{ $stat['label'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'highlighted')
            <div class="flex flex-col lg:flex-row items-center justify-between gap-16 md:gap-24">
                @php $highlight = ($config['stats'] ?? [])[0] ?? null; @endphp
                @if($highlight)
                    <div class="text-center lg:text-left">
                        <div class="text-7xl md:text-9xl font-black text-primary mb-4 tracking-tighter">
                            @if($highlight['prefix'] ?? null) <span class="text-3xl md:text-5xl opacity-40">{{ $highlight['prefix'] }}</span> @endif
                            {{ $highlight['value'] ?? '0' }}
                            @if($highlight['suffix'] ?? null) <span class="text-3xl md:text-5xl opacity-40">{{ $highlight['suffix'] }}</span> @endif
                        </div>
                        <div class="text-2xl md:text-3xl font-black opacity-80 uppercase tracking-tight">{{ $highlight['label'] ?? '' }}</div>
                    </div>
                @endif
                
                <div class="grid grid-cols-2 gap-12 md:gap-20">
                    @foreach(array_slice($config['stats'] ?? [], 1) as $stat)
                        <div class="text-center lg:text-left">
                            <div class="text-4xl md:text-5xl font-black mb-2 tracking-tighter text-zinc-900 dark:text-white">
                                @if($stat['prefix'] ?? null) <span class="text-xl opacity-30">{{ $stat['prefix'] }}</span> @endif
                                {{ $stat['value'] ?? '0' }}
                                @if($stat['suffix'] ?? null) <span class="text-xl opacity-30">{{ $stat['suffix'] }}</span> @endif
                            </div>
                            <div class="text-xs md:text-sm opacity-50 font-bold uppercase tracking-widest">{{ $stat['label'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
