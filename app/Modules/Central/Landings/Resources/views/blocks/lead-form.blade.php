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
        'surface' => 'bg-surface',
        'dark' => 'bg-gray-900 text-white',
        default => 'bg-white'
    };
@endphp

<section class="{{ $bgClass }} {{ $padding }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-12">
            @if($config['section_title'] ?? null)
                <h2 class="text-3xl font-extrabold sm:text-4xl">
                    {{ $config['section_title'] }}
                </h2>
            @endif
            @if($config['section_subtitle'] ?? null)
                <p class="mt-4 text-lg opacity-80">
                    {{ $config['section_subtitle'] }}
                </p>
            @endif
        </div>

        <div class="max-w-2xl mx-auto bg-white dark:bg-zinc-800 p-10 rounded-3xl shadow-xl border border-zinc-100 dark:border-zinc-700" x-data="{ sent: false, loading: false }">
            <div x-show="!sent">
                <form 
                    x-on:submit.prevent="loading = true; setTimeout(() => { sent = true; loading = false; }, 1500)" 
                    class="space-y-6"
                >
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        @foreach($config['fields'] ?? [['type' => 'text', 'label' => 'Full Name', 'name' => 'name', 'required' => true], ['type' => 'email', 'label' => 'Work Email', 'name' => 'email', 'required' => true]] as $field)
                            <div class="{{ ($field['type'] === 'textarea') ? 'sm:col-span-2' : '' }}">
                                <label class="block text-sm font-bold mb-2 opacity-80 uppercase tracking-wider">{{ $field['label'] }}</label>
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
                                        class="w-full rounded-xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 focus:ring-primary focus:border-primary py-3 px-4"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                    >
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-4 bg-primary text-white rounded-xl font-black text-lg transition hover:opacity-90 flex items-center justify-center gap-2 shadow-lg shadow-primary/20"
                        :disabled="loading"
                    >
                        <span x-show="!loading">{{ $config['submit_text'] ?? __('Get Started Now') }}</span>
                        <span x-show="loading" class="animate-spin rounded-full h-5 w-5 border-2 border-white border-t-transparent"></span>
                    </button>
                    
                    @if($config['show_social_proof'] ?? null)
                        <p class="text-center text-xs opacity-50 font-medium">{{ $config['show_social_proof'] }}</p>
                    @endif
                </form>
            </div>

            <div x-show="sent" x-cloak class="text-center py-12">
                <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8">
                    <flux:icon.check size="lg" />
                </div>
                <h3 class="text-3xl font-black mb-4">{{ __('Welcome Aboard!') }}</h3>
                <p class="text-lg opacity-70 mb-8">{{ $config['success_message'] ?? __('We have received your request. One of our experts will contact you within 24 hours.') }}</p>
                
                @if($config['redirect_url'] ?? null)
                    <a href="{{ $config['redirect_url'] }}" class="inline-flex items-center text-primary font-bold hover:underline">
                        {{ __('Continue to next step') }}
                        <flux:icon.arrow-right class="ml-2" size="sm" />
                    </a>
                @endif
            </div>
        </div>
    </div>
</section>
