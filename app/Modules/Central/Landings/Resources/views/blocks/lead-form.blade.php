@props([
    'config' => [],
    'styles' => [],
    'variant' => 'inline',
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
        'surface' => 'bg-surface text-zinc-900 dark:text-white',
        'dark' => 'bg-gray-900 text-white',
        'white' => 'bg-white text-zinc-900',
        default => 'bg-white'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);

    $defaultFields = [
        ['type' => 'text', 'label' => __('Full Name'), 'name' => 'name', 'required' => true, 'placeholder' => __('Jane Doe')],
        ['type' => 'email', 'label' => __('Work Email'), 'name' => 'email', 'required' => true, 'placeholder' => __('jane@company.com')],
    ];
    
    $fields = $config['fields'] ?? $defaultFields;
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($variant === 'inline' || $variant === 'multi-field')
            <div class="max-w-3xl mx-auto text-center mb-12">
                @if($config['headline'] ?? null)
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight tracking-tight">
                        {{ $config['headline'] }}
                    </h2>
                @endif
                @if($config['subtitle'] ?? null)
                    <p class="mt-4 text-xl opacity-70">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
            </div>

            <div class="max-w-2xl mx-auto bg-white dark:bg-zinc-800 p-8 md:p-12 rounded-[2.5rem] shadow-2xl border border-zinc-100 dark:border-zinc-700" x-data="{ sent: false, loading: false }">
                <div x-show="!sent">
                    <form 
                        x-on:submit.prevent="loading = true; setTimeout(() => { sent = true; loading = false; }, 1500)" 
                        class="space-y-6 text-left"
                    >
                        <div class="grid grid-cols-1 {{ $variant === 'multi-field' ? 'sm:grid-cols-2' : '' }} gap-6">
                            @foreach($fields as $field)
                                <div class="{{ ($field['type'] === 'textarea' || $variant === 'inline') ? 'sm:col-span-2' : '' }}">
                                    <label class="block text-sm font-bold mb-2 opacity-80 uppercase tracking-widest text-zinc-900 dark:text-white">{{ $field['label'] }}</label>
                                    @if($field['type'] === 'textarea')
                                        <textarea 
                                            name="{{ $field['name'] }}" 
                                            rows="4" 
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary text-base transition text-zinc-900 dark:text-white"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        ></textarea>
                                    @else
                                        <input 
                                            type="{{ $field['type'] }}" 
                                            name="{{ $field['name'] }}" 
                                            {{ ($field['required'] ?? false) ? 'required' : '' }}
                                            class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary py-4 px-5 text-base transition text-zinc-900 dark:text-white"
                                            placeholder="{{ $field['placeholder'] ?? '' }}"
                                        >
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <button 
                            type="submit" 
                            class="w-full py-4 mt-4 bg-primary text-white rounded-2xl font-black text-lg transition hover:opacity-90 hover:scale-[1.02] flex items-center justify-center gap-3 shadow-xl shadow-primary/20"
                            :disabled="loading"
                        >
                            <span x-show="!loading">{{ $config['submit_text'] ?? __('Get Started Now') }}</span>
                            <span x-show="loading" class="animate-spin rounded-full h-6 w-6 border-3 border-white border-t-transparent"></span>
                            <flux:icon.arrow-right x-show="!loading" size="sm" />
                        </button>
                        
                        @if($config['show_social_proof'] ?? null)
                            <div class="mt-6 flex items-center justify-center gap-2 opacity-60 text-sm font-medium text-zinc-900 dark:text-white">
                                <flux:icon.lock-closed size="xs" />
                                <span>{{ $config['show_social_proof'] }}</span>
                            </div>
                        @endif
                    </form>
                </div>

                <div x-show="sent" x-cloak class="text-center py-12">
                    <div class="w-24 h-24 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8">
                        <flux:icon.check size="xl" variant="solid" />
                    </div>
                    <h3 class="text-4xl font-black mb-4 text-zinc-900 dark:text-white">{{ __('Success!') }}</h3>
                    <p class="text-xl opacity-70 mb-10 text-zinc-900 dark:text-white">{{ $config['success_message'] ?? __('We have received your details. Check your inbox shortly.') }}</p>
                    
                    @if($config['redirect_url'] ?? null)
                        <a href="{{ $config['redirect_url'] }}" class="inline-flex items-center px-8 py-4 bg-primary text-white font-bold rounded-xl hover:opacity-90 transition shadow-lg shadow-primary/20">
                            {{ __('Continue') }}
                            <flux:icon.arrow-right class="ml-2" size="sm" />
                        </a>
                    @else
                        <button x-on:click="sent = false" class="text-primary font-bold hover:underline">{{ __('Submit another response') }}</button>
                    @endif
                </div>
            </div>
            
        @elseif($variant === 'newsletter')
            <div class="max-w-4xl mx-auto bg-white dark:bg-zinc-800 p-8 md:p-16 rounded-[3rem] shadow-2xl border border-zinc-100 dark:border-zinc-700 text-center" x-data="{ sent: false, loading: false }">
                <div x-show="!sent">
                    @if($config['headline'] ?? null)
                        <h2 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-4">
                            {{ $config['headline'] }}
                        </h2>
                    @endif
                    @if($config['subtitle'] ?? null)
                        <p class="text-lg md:text-xl opacity-70 max-w-2xl mx-auto mb-10">
                            {{ $config['subtitle'] }}
                        </p>
                    @endif

                    <form x-on:submit.prevent="loading = true; setTimeout(() => { sent = true; loading = false; }, 1000)" class="max-w-md mx-auto relative">
                        <input 
                            type="email" 
                            name="email" 
                            required
                            class="w-full rounded-full border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary py-4 md:py-5 pl-6 pr-36 text-base shadow-inner text-zinc-900 dark:text-white"
                            placeholder="{{ $config['fields'][0]['placeholder'] ?? __('Enter your email') }}"
                        >
                        <button 
                            type="submit" 
                            class="absolute right-2 top-2 bottom-2 px-6 bg-primary text-white rounded-full font-bold transition hover:opacity-90 flex items-center justify-center gap-2"
                            :disabled="loading"
                        >
                            <span x-show="!loading">{{ $config['submit_text'] ?? __('Subscribe') }}</span>
                            <span x-show="loading" class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></span>
                        </button>
                    </form>
                    
                    @if($config['show_social_proof'] ?? null)
                        <p class="mt-6 text-sm opacity-50 font-medium text-zinc-900 dark:text-white">{{ $config['show_social_proof'] }}</p>
                    @endif
                </div>

                <div x-show="sent" x-cloak class="py-8">
                    <h3 class="text-2xl font-black mb-2 text-zinc-900 dark:text-white flex items-center justify-center gap-3">
                        <flux:icon.check-circle class="text-emerald-500" />
                        {{ __('Subscribed!') }}
                    </h3>
                    <p class="opacity-70 text-zinc-900 dark:text-white">{{ $config['success_message'] ?? __('Thanks for joining our newsletter.') }}</p>
                </div>
            </div>
        @endif
    </div>
</section>
