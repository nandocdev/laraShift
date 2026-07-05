<!-- CTA Preview -->
<div x-show="block.type === 'cta'" class="w-full relative overflow-hidden py-20" :class="block.styles.text_align === 'center' ? 'text-center' : (block.styles.text_align === 'right' ? 'text-right' : 'text-left')">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.variant === 'centered'">
            <div class="max-w-4xl" :class="block.styles.text_align === 'center' ? 'mx-auto' : (block.styles.text_align === 'right' ? 'ml-auto' : '')">
                <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight" x-text="block.config.headline || 'Ready to grow your business?'"></h2>
                
                <template x-if="block.config.description">
                    <p class="mt-6 text-xl opacity-90 max-w-2xl" :class="block.styles.text_align === 'center' ? 'mx-auto' : (block.styles.text_align === 'right' ? 'ml-auto' : '')" x-text="block.config.description"></p>
                </template>
                
                <div class="mt-10 flex flex-wrap gap-4" :class="block.styles.text_align === 'center' ? 'justify-center' : (block.styles.text_align === 'right' ? 'justify-end' : '')">
                    <template x-if="block.config.button_primary_text">
                        <div class="px-8 py-4 bg-primary text-white rounded-xl font-black text-lg shadow-xl" :class="block.styles.background === 'primary' || block.styles.background === 'gradient' ? 'bg-white text-primary' : 'bg-primary text-white'" x-text="block.config.button_primary_text"></div>
                    </template>
                    
                    <template x-if="block.config.show_secondary_button !== false && block.config.button_secondary_text">
                        <div class="px-8 py-4 border-2 border-current/30 rounded-xl font-bold text-lg" x-text="block.config.button_secondary_text"></div>
                    </template>
                </div>

                <template x-if="block.config.show_guarantee">
                    <p class="mt-6 text-sm font-medium opacity-70" x-text="block.config.guarantee_text || 'No credit card required.'"></p>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'banner'">
            <div class="bg-zinc-100 dark:bg-zinc-800 rounded-3xl p-8 md:p-12 border border-zinc-200 dark:border-zinc-700" :class="block.styles.background === 'primary' || block.styles.background === 'dark' || block.styles.background === 'gradient' ? 'bg-white/10 border-white/10' : ''">
                <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                    <div class="flex-1 text-center md:text-left">
                        <h2 class="text-2xl md:text-3xl font-bold" x-text="block.config.headline || 'Start your free trial today.'"></h2>
                        <template x-if="block.config.description">
                            <p class="mt-2 text-lg opacity-80" x-text="block.config.description"></p>
                        </template>
                    </div>
                    <div class="flex flex-shrink-0 items-center gap-4">
                        <template x-if="block.config.button_primary_text">
                            <div class="px-8 py-4 rounded-xl font-black shadow-lg" :class="block.styles.background === 'primary' || block.styles.background === 'dark' || block.styles.background === 'gradient' ? 'bg-white text-primary' : 'bg-primary text-white'" x-text="block.config.button_primary_text"></div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template x-if="block.variant === 'split'">
            <div class="grid lg:grid-cols-2 gap-12 items-center text-left">
                <div>
                    <h2 class="text-3xl font-extrabold sm:text-4xl" x-text="block.config.headline || 'Ready to join us?'"></h2>
                    <p class="mt-4 text-lg opacity-80 leading-relaxed" x-text="block.config.description || 'Sign up for a 14-day free trial.'"></p>
                    
                    <template x-if="block.config.show_guarantee">
                        <div class="mt-6 flex items-center gap-2 opacity-70">
                            <flux:icon.check-circle size="xs" />
                            <span class="text-sm" x-text="block.config.guarantee_text || 'No credit card required.'"></span>
                        </div>
                    </template>
                </div>
                
                <div class="bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-2xl border border-zinc-100 dark:border-zinc-700 text-zinc-900 dark:text-white">
                    <div class="space-y-4">
                        <div class="h-12 bg-zinc-100 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 flex items-center px-4 text-zinc-400 text-sm">
                            {{ __('Enter your work email...') }}
                        </div>
                        <div class="w-full py-4 bg-primary text-white rounded-xl font-black text-center">
                            <span x-text="block.config.button_primary_text || '{{ __('Get Started') }}'"></span>
                        </div>
                    </div>
                    <p class="mt-4 text-center text-xs text-zinc-500">{{ __('By signing up, you agree to our Terms of Service.') }}</p>
                </div>
            </div>
        </template>

    </div>
</div>