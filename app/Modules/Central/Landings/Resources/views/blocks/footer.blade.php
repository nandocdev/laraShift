@props([
    'config' => [],
    'styles' => [],
    'variant' => 'simple',
])

@php
    $bgClass = match($styles['background'] ?? 'dark') {
        'primary' => 'bg-primary text-white',
        'secondary' => 'bg-secondary text-white',
        'surface' => 'bg-zinc-50 dark:bg-zinc-900 text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-gray-900 text-white'
    };

    $borderColor = match($styles['background'] ?? 'dark') {
        'white', 'surface' => 'border-zinc-200 dark:border-zinc-800',
        default => 'border-white/10'
    };

    $mutedText = match($styles['background'] ?? 'dark') {
        'white', 'surface' => 'text-zinc-500',
        default => 'text-gray-400'
    };

    $hoverText = match($styles['background'] ?? 'dark') {
        'white', 'surface' => 'hover:text-primary',
        default => 'hover:text-white'
    };
@endphp

<footer class="{{ $bgClass }} border-t {{ $borderColor }} py-12 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'simple')
            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="flex flex-col items-center md:items-start gap-4">
                    @if($config['logo_url'] ?? null)
                        <img src="{{ $config['logo_url'] }}" alt="{{ $config['logo_alt'] ?? '' }}" class="h-8 w-auto">
                    @else
                        <span class="text-xl font-black tracking-tighter">LaraShift</span>
                    @endif
                </div>
                
                <nav class="flex flex-wrap justify-center gap-x-8 gap-y-4">
                    @foreach($config['legal_links'] ?? [] as $link)
                        <a href="{{ $link['url'] }}" class="{{ $mutedText }} {{ $hoverText }} text-sm font-medium transition">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
                
                <div class="{{ $mutedText }} text-sm">
                    {{ $config['copyright_text'] ?? '© ' . date('Y') . ' All rights reserved.' }}
                </div>
            </div>

        @elseif($variant === 'multi-column')
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-12 md:gap-8">
                <div class="col-span-2 space-y-6">
                    @if($config['logo_url'] ?? null)
                        <img src="{{ $config['logo_url'] }}" alt="{{ $config['logo_alt'] ?? '' }}" class="h-8 w-auto">
                    @else
                        <span class="text-2xl font-black tracking-tighter block">LaraShift</span>
                    @endif
                    
                    @if($config['description'] ?? null)
                        <p class="{{ $mutedText }} text-base max-w-xs leading-relaxed">
                            {{ $config['description'] }}
                        </p>
                    @endif

                    @if(($config['show_social'] ?? false) && ($config['social_links'] ?? null))
                        <div class="flex gap-4">
                            @foreach($config['social_links'] as $social)
                                <a href="{{ $social['url'] }}" target="_blank" class="{{ $mutedText }} {{ $hoverText }} transition transform hover:scale-110">
                                    <flux:icon :icon="'brand-' . ($social['platform'] ?? 'twitter')" size="sm" />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
                
                @foreach($config['columns'] ?? [] as $column)
                    <div class="col-span-1">
                        <h3 class="text-sm font-bold uppercase tracking-widest mb-6">{{ $column['title'] }}</h3>
                        <ul class="space-y-4">
                            @foreach($column['links'] ?? [] as $link)
                                <li>
                                    <a href="{{ $link['url'] }}" class="{{ $mutedText }} {{ $hoverText }} text-sm transition">
                                        {{ $link['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                @if($config['show_newsletter'] ?? false)
                    <div class="col-span-2 lg:col-span-2 space-y-6">
                        <h3 class="text-sm font-bold uppercase tracking-widest">{{ $config['newsletter_title'] ?? __('Subscribe to our newsletter') }}</h3>
                        <p class="{{ $mutedText }} text-sm">{{ $config['newsletter_description'] ?? __('The latest news, articles, and resources, sent to your inbox weekly.') }}</p>
                        <form class="flex gap-2">
                            <input type="email" placeholder="{{ __('Email address') }}" class="flex-1 min-w-0 px-4 py-2 bg-white/5 border {{ $borderColor }} rounded-lg text-sm focus:ring-primary focus:border-primary">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg font-bold text-sm hover:opacity-90 transition">{{ __('Subscribe') }}</button>
                        </form>
                    </div>
                @endif
            </div>
            
            <div class="border-t {{ $borderColor }} mt-16 pt-8 flex flex-col md:flex-row justify-between items-center gap-6 {{ $mutedText }} text-sm">
                <div>{{ $config['copyright_text'] ?? '© ' . date('Y') . ' All rights reserved.' }}</div>
                <div class="flex flex-wrap justify-center gap-x-8 gap-y-2 font-medium">
                    @foreach($config['legal_links'] ?? [] as $link)
                        <a href="{{ $link['url'] }}" class="{{ $hoverText }} transition">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</footer>
