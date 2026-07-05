@props([
    'config' => [],
    'styles' => [],
    'variant' => 'form-info',
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

    $textAlign = $styles['text_align'] ?? 'center';
    $alignmentClass = match($textAlign) {
        'left' => 'text-left',
        default => 'text-center'
    };

    $blockId = $attributes->get('id') ?? ($config['id'] ?? null);

    $defaultFields = [
        ['type' => 'text', 'label' => __('Name'), 'name' => 'name', 'required' => true, 'placeholder' => __('John Doe')],
        ['type' => 'email', 'label' => __('Email'), 'name' => 'email', 'required' => true, 'placeholder' => __('john@example.com')],
        ['type' => 'textarea', 'label' => __('Message'), 'name' => 'message', 'required' => true, 'placeholder' => __('How can we help?')],
    ];

    $fields = $config['fields'] ?? $defaultFields;
@endphp

<section id="{{ $blockId }}" class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
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

        <div class="grid {{ $variant === 'compact' ? 'grid-cols-1 max-w-2xl mx-auto' : 'lg:grid-cols-2 gap-16' }} items-start">
            <!-- Form Side -->
            <div class="bg-white dark:bg-zinc-800 p-8 md:p-10 rounded-[2.5rem] shadow-sm border border-zinc-100 dark:border-zinc-700" x-data="{ sent: false, loading: false }">
                <div x-show="!sent">
                    <form 
                        x-on:submit.prevent="loading = true; setTimeout(() => { sent = true; loading = false; }, 1500)" 
                        class="space-y-6 text-left"
                    >
                        @foreach($fields as $field)
                            <div>
                                <label class="block text-sm font-bold mb-2 opacity-80 uppercase tracking-wider text-zinc-900 dark:text-white">{{ $field['label'] }}</label>
                                @if($field['type'] === 'textarea')
                                    <textarea 
                                        name="{{ $field['name'] }}" 
                                        rows="4" 
                                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary text-base placeholder-zinc-400 dark:text-white transition"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                    ></textarea>
                                @else
                                    <input 
                                        type="{{ $field['type'] }}" 
                                        name="{{ $field['name'] }}" 
                                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary py-4 px-5 text-base placeholder-zinc-400 dark:text-white transition"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                    >
                                @endif
                            </div>
                        @endforeach

                        <button 
                            type="submit" 
                            class="w-full py-4 bg-primary text-white rounded-2xl font-black text-lg shadow-lg shadow-primary/20 transition hover:opacity-90 hover:scale-[1.02] flex items-center justify-center gap-3"
                            :disabled="loading"
                        >
                            <span x-show="!loading">{{ $config['submit_text'] ?? __('Send Message') }}</span>
                            <span x-show="loading" class="animate-spin rounded-full h-6 w-6 border-3 border-white border-t-transparent"></span>
                            <flux:icon.paper-airplane x-show="!loading" size="sm" />
                        </button>
                    </form>
                </div>

                <div x-show="sent" x-cloak class="text-center py-12">
                    <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8 animate-bounce">
                        <flux:icon.check size="lg" variant="solid" />
                    </div>
                    <h3 class="text-3xl font-black mb-4 text-zinc-900 dark:text-white">{{ __('Message Sent!') }}</h3>
                    <p class="text-lg opacity-60 leading-relaxed">{{ $config['success_message'] ?? __('Thank you for reaching out. We have received your message and will get back to you as soon as possible.') }}</p>
                    <button x-on:click="sent = false" class="mt-8 text-primary font-bold hover:underline">{{ __('Send another message') }}</button>
                </div>
            </div>

            <!-- Info Side -->
            @if($variant !== 'compact')
                <div class="space-y-12">
                    <div class="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-10">
                        @if($config['show_email'] ?? true)
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.envelope size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Email us') }}</h4>
                                    <p class="text-lg opacity-60">{{ $config['email'] ?? 'hello@example.com' }}</p>
                                </div>
                            </div>
                        @endif

                        @if($config['show_phone'] ?? true)
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.phone size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Call us') }}</h4>
                                    <p class="text-lg opacity-60">{{ $config['phone'] ?? '+1 (555) 123-4567' }}</p>
                                </div>
                            </div>
                        @endif

                        @if($config['show_address'] ?? true)
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.map-pin size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Visit us') }}</h4>
                                    <p class="text-lg opacity-60 leading-relaxed">{{ $config['address'] ?? '742 Evergreen Terrace, Springfield' }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($variant === 'map-included' && ($config['map_embed_url'] ?? null))
                        <div class="relative group">
                            <div class="absolute -inset-2 bg-primary/5 rounded-[2.5rem] -z-10 group-hover:scale-[1.02] transition"></div>
                            <div class="rounded-[2rem] overflow-hidden shadow-xl border border-zinc-100 dark:border-zinc-700 h-80">
                                <iframe 
                                    src="{{ $config['map_embed_url'] }}" 
                                    width="100%" height="100%" style="border:0;" 
                                    allowfullscreen="" loading="lazy" 
                                    referrerpolicy="no-referrer-when-downgrade"
                                    class="grayscale contrast-[1.1] brightness-[0.9] hover:grayscale-0 transition-all duration-700"
                                ></iframe>
                            </div>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
