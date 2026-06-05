@props([
    'config' => [],
    'styles' => [],
    'variant' => 'accordion',
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

    $textAlign = $styles['text_align'] ?? 'center';
    $alignmentClass = match($textAlign) {
        'left' => 'text-left',
        default => 'text-center'
    };

    $itemStyle = $styles['item_style'] ?? 'boxed'; // boxed | separated | flat
    
    $iconType = $config['icon_type'] ?? 'chevron'; // chevron | plus
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        @if(($config['headline'] ?? null) || ($config['subtitle'] ?? null))
            <div class="{{ $alignmentClass }} mb-16">
                @if($config['headline'] ?? null)
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight">
                        {{ $config['headline'] }}
                    </h2>
                @endif
                @if($config['subtitle'] ?? null)
                    <p class="mt-4 text-xl opacity-70 max-w-2xl {{ $textAlign === 'center' ? 'mx-auto' : '' }}">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
            </div>
        @endif

        @if($variant === 'accordion')
            <div class="{{ $itemStyle === 'separated' ? 'space-y-6' : ($itemStyle === 'boxed' ? 'space-y-4' : 'border-t border-zinc-200 dark:border-zinc-800') }}" x-data="{ active: {{ ($config['open_first'] ?? false) ? 0 : 'null' }} }">
                @foreach($config['items'] ?? [] as $index => $item)
                    @php
                        $itemClasses = match($itemStyle) {
                            'separated' => 'bg-white dark:bg-zinc-800 rounded-[1.5rem] shadow-sm border border-zinc-100 dark:border-zinc-700 overflow-hidden',
                            'boxed' => 'border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white/5',
                            'flat' => 'border-b border-zinc-200 dark:border-zinc-800',
                            default => 'border border-zinc-200 dark:border-zinc-700 rounded-xl'
                        };
                    @endphp
                    <div class="{{ $itemClasses }}">
                        <button 
                            x-on:click="active = active === {{ $index }} ? null : {{ $index }}"
                            class="w-full flex items-center justify-between p-6 text-left font-bold text-lg transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <span class="pr-8">{{ $item['question'] ?? '' }}</span>
                            
                            <div class="flex-shrink-0 text-primary transition-transform duration-300" :class="active === {{ $index }} ? 'rotate-180' : ''">
                                @if($iconType === 'plus')
                                    <flux:icon icon="plus" x-show="active !== {{ $index }}" size="sm" />
                                    <flux:icon icon="minus" x-show="active === {{ $index }}" size="sm" x-cloak />
                                @else
                                    <flux:icon icon="chevron-down" size="sm" />
                                @endif
                            </div>
                        </button>
                        <div 
                            x-show="active === {{ $index }}" 
                            x-collapse
                            class="p-6 pt-0 opacity-80 leading-relaxed text-base"
                        >
                            {!! nl2br(e($item['answer'] ?? '')) !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'two-columns')
            <div class="grid md:grid-cols-2 gap-x-12 gap-y-10">
                @foreach($config['items'] ?? [] as $item)
                    <div class="space-y-3">
                        <h3 class="font-bold text-xl leading-tight">{{ $item['question'] ?? '' }}</h3>
                        <p class="opacity-70 leading-relaxed">{{ $item['answer'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="space-y-12 max-w-2xl mx-auto">
                @foreach($config['items'] ?? [] as $item)
                    <div class="space-y-4">
                        <h3 class="font-black text-2xl tracking-tight">{{ $item['question'] ?? '' }}</h3>
                        <div class="h-1 w-12 bg-primary rounded-full opacity-20"></div>
                        <p class="opacity-70 text-lg leading-relaxed">{{ $item['answer'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($config['show_contact_cta'] ?? false)
            <div class="mt-20 text-center p-10 bg-primary/5 rounded-[2.5rem] border border-primary/10">
                <h3 class="text-xl font-bold mb-2">{{ __('Still have questions?') }}</h3>
                <p class="mb-6 opacity-60 max-w-md mx-auto">{{ __('If you cannot find the answer to your question in our FAQ, you can always contact us. We will answer to you shortly!') }}</p>
                <a 
                    href="{{ $config['contact_cta_url'] ?? '#' }}" 
                    target="{{ $config['contact_cta_target'] ?? '_self' }}"
                    class="inline-flex items-center px-6 py-3 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition shadow-lg shadow-primary/20"
                >
                    {{ $config['contact_cta_text'] ?? __('Contact our support team') }}
                    <flux:icon.arrow-right class="ml-2" size="xs" />
                </a>
            </div>
        @endif
    </div>
</section>
