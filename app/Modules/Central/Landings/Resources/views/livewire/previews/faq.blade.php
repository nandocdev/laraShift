<!-- FAQ Preview -->
<div x-show="block.type === 'faq'" class="w-full relative overflow-hidden py-20">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.config.headline || block.config.subtitle">
            <div class="mb-16" :class="block.styles.text_align === 'left' ? 'text-left' : 'text-center'">
                <template x-if="block.config.headline">
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight" x-text="block.config.headline"></h2>
                </template>
                <template x-if="block.config.subtitle">
                    <p class="mt-4 text-xl opacity-70 max-w-2xl" :class="block.styles.text_align === 'left' ? '' : 'mx-auto'" x-text="block.config.subtitle"></p>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'accordion'">
            <div x-data="{ active: block.config.open_first ? 0 : null }" 
                 :class="block.styles.item_style === 'separated' ? 'space-y-6' : (block.styles.item_style === 'boxed' ? 'space-y-4' : 'border-t border-zinc-200 dark:border-zinc-800')">
                <template x-for="(item, index) in (block.config.items || [])">
                    <div :class="[
                            block.styles.item_style === 'separated' ? 'bg-white dark:bg-zinc-800 rounded-[1.5rem] shadow-sm border border-zinc-100 dark:border-zinc-700 overflow-hidden' : '',
                            block.styles.item_style === 'boxed' ? 'border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden bg-white/5' : '',
                            (!block.styles.item_style || block.styles.item_style === 'flat') ? 'border-b border-zinc-200 dark:border-zinc-800' : ''
                        ]">
                        <button 
                            x-on:click="active = active === index ? null : index"
                            class="w-full flex items-center justify-between p-6 text-left font-bold text-lg transition hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                        >
                            <span class="pr-8" x-text="item.question"></span>
                            
                            <div class="flex-shrink-0 text-primary transition-transform duration-300" :class="active === index ? 'rotate-180' : ''">
                                <template x-if="block.config.icon_type === 'plus'">
                                    <span>
                                        <flux:icon icon="plus" x-show="active !== index" size="sm" />
                                        <flux:icon icon="minus" x-show="active === index" size="sm" x-cloak />
                                    </span>
                                </template>
                                <template x-if="block.config.icon_type !== 'plus'">
                                    <flux:icon icon="chevron-down" size="sm" />
                                </template>
                            </div>
                        </button>
                        <div 
                            x-show="active === index" 
                            x-collapse
                            class="p-6 pt-0 opacity-80 leading-relaxed text-base"
                            x-text="item.answer"
                        ></div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'two-columns'">
            <div class="grid md:grid-cols-2 gap-x-12 gap-y-10 text-left">
                <template x-for="item in (block.config.items || [])">
                    <div class="space-y-3">
                        <h3 class="font-bold text-xl leading-tight" x-text="item.question"></h3>
                        <p class="opacity-70 leading-relaxed" x-text="item.answer"></p>
                    </div>
                </template>
            </div>
        </template>
        
        <template x-if="block.variant === 'simple-list'">
            <div class="space-y-12 max-w-2xl mx-auto text-left">
                <template x-for="item in (block.config.items || [])">
                    <div class="space-y-4">
                        <h3 class="font-black text-2xl tracking-tight" x-text="item.question"></h3>
                        <div class="h-1 w-12 bg-primary rounded-full opacity-20"></div>
                        <p class="opacity-70 text-lg leading-relaxed" x-text="item.answer"></p>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.config.show_contact_cta">
            <div class="mt-20 text-center p-10 bg-primary/5 rounded-[2.5rem] border border-primary/10">
                <h3 class="text-xl font-bold mb-2">{{ __('Still have questions?') }}</h3>
                <p class="mb-6 opacity-60 max-w-md mx-auto">{{ __('If you cannot find the answer to your question in our FAQ, you can always contact us. We will answer to you shortly!') }}</p>
                <div class="inline-flex items-center px-6 py-3 bg-primary text-white font-bold rounded-xl shadow-lg shadow-primary/20">
                    <span x-text="block.config.contact_cta_text || '{{ __('Contact our support team') }}'"></span>
                    <flux:icon.arrow-right class="ml-2" size="xs" />
                </div>
            </div>
        </template>
    </div>
</div>