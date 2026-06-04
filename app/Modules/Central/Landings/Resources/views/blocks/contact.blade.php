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
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
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

        <div class="grid lg:grid-cols-2 gap-16 items-start">
            <!-- Form Side -->
            <div class="bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-sm border border-zinc-100 dark:border-zinc-700" x-data="{ sent: false, loading: false }">
                <div x-show="!sent">
                    <form 
                        x-on:submit.prevent="loading = true; setTimeout(() => { sent = true; loading = false; }, 1500)" 
                        class="space-y-6"
                    >
                        @foreach($config['fields'] ?? [['type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true], ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true], ['type' => 'textarea', 'label' => 'Message', 'name' => 'message', 'required' => true]] as $field)
                            <div>
                                <label class="block text-sm font-medium mb-2 opacity-80">{{ $field['label'] }}</label>
                                @if($field['type'] === 'textarea')
                                    <textarea 
                                        name="{{ $field['name'] }}" 
                                        rows="4" 
                                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary text-sm"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                    ></textarea>
                                @else
                                    <input 
                                        type="{{ $field['type'] }}" 
                                        name="{{ $field['name'] }}" 
                                        {{ ($field['required'] ?? false) ? 'required' : '' }}
                                        class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary text-sm"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                    >
                                @endif
                            </div>
                        @endforeach

                        <button 
                            type="submit" 
                            class="w-full py-4 bg-primary text-white rounded-xl font-bold transition hover:opacity-90 flex items-center justify-center gap-2"
                            :disabled="loading"
                        >
                            <span x-show="!loading">{{ $config['submit_text'] ?? __('Send Message') }}</span>
                            <span x-show="loading" class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></span>
                        </button>
                    </form>
                </div>

                <div x-show="sent" x-cloak class="text-center py-12">
                    <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                        <flux:icon.check size="lg" />
                    </div>
                    <h3 class="text-2xl font-bold mb-2">{{ __('Thank you!') }}</h3>
                    <p class="opacity-60">{{ $config['success_message'] ?? __('Your message has been sent successfully. We will get back to you soon.') }}</p>
                </div>
            </div>

            <!-- Info Side -->
            <div class="space-y-12">
                @if($variant === 'form-info' || $variant === 'map-included')
                    <div class="grid sm:grid-cols-2 gap-8">
                        @if($config['show_email'] ?? true)
                            <div>
                                <h4 class="font-bold text-lg mb-2">{{ __('Email us') }}</h4>
                                <p class="opacity-60">{{ $config['email'] ?? 'support@example.com' }}</p>
                            </div>
                        @endif
                        @if($config['show_phone'] ?? true)
                            <div>
                                <h4 class="font-bold text-lg mb-2">{{ __('Call us') }}</h4>
                                <p class="opacity-60">{{ $config['phone'] ?? '+1 (555) 000-0000' }}</p>
                            </div>
                        @endif
                        @if($config['show_address'] ?? true)
                            <div class="sm:col-span-2">
                                <h4 class="font-bold text-lg mb-2">{{ __('Visit us') }}</h4>
                                <p class="opacity-60 leading-relaxed">{{ $config['address'] ?? '123 Business St, Innovation City, 90210' }}</p>
                            </div>
                        @endif
                    </div>

                    @if($variant === 'map-included' && ($config['map_embed_url'] ?? null))
                        <div class="rounded-3xl overflow-hidden shadow-sm border border-zinc-100 dark:border-zinc-700 h-64">
                            <iframe 
                                src="{{ $config['map_embed_url'] }}" 
                                width="100%" height="100%" style="border:0;" 
                                allowfullscreen="" loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade"
                            ></iframe>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</section>
