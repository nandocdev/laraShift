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
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($config['section_title'] ?? null)
            <div class="text-center mb-12">
                <h2 class="text-3xl font-extrabold sm:text-4xl">
                    {{ $config['section_title'] }}
                </h2>
                @if($config['section_subtitle'] ?? null)
                    <p class="mt-4 text-lg opacity-80">
                        {{ $config['section_subtitle'] }}
                    </p>
                @endif
            </div>
        @endif

        @if($variant === 'accordion')
            <div class="space-y-4" x-data="{ active: {{ ($config['open_first'] ?? false) ? 0 : 'null' }} }">
                @foreach($config['items'] ?? [] as $index => $item)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white dark:bg-zinc-800">
                        <button 
                            x-on:click="active = active === {{ $index }} ? null : {{ $index }}"
                            class="w-full flex items-center justify-between p-5 text-left font-semibold transition hover:bg-zinc-50 dark:hover:bg-zinc-700/50"
                        >
                            <span>{{ $item['question'] ?? '' }}</span>
                            <flux:icon.chevron-down 
                                class="transition-transform duration-200" 
                                x-bind:class="active === {{ $index }} ? 'rotate-180' : ''" 
                                size="sm" 
                            />
                        </button>
                        <div 
                            x-show="active === {{ $index }}" 
                            x-collapse
                            class="p-5 pt-0 opacity-80 leading-relaxed"
                        >
                            {!! nl2br(e($item['answer'] ?? '')) !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif($variant === 'two-columns')
            <div class="grid md:grid-cols-2 gap-10">
                @foreach($config['items'] ?? [] as $item)
                    <div>
                        <h3 class="font-bold text-lg mb-3">{{ $item['question'] ?? '' }}</h3>
                        <p class="opacity-80">{{ $item['answer'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="space-y-10">
                @foreach($config['items'] ?? [] as $item)
                    <div>
                        <h3 class="font-bold text-xl mb-3">{{ $item['question'] ?? '' }}</h3>
                        <p class="opacity-80">{{ $item['answer'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($config['show_contact_cta'] ?? false)
            <div class="mt-16 text-center pt-8 border-t border-zinc-100 dark:border-zinc-700">
                <p class="mb-4 opacity-60">{{ __('Still have questions?') }}</p>
                <a href="{{ $config['contact_cta_url'] ?? '#' }}" class="inline-flex items-center text-primary font-bold hover:underline">
                    {{ $config['contact_cta_text'] ?? __('Contact our support team') }}
                    <flux:icon.arrow-right class="ml-1" size="xs" />
                </a>
            </div>
        @endif
    </div>
</section>
