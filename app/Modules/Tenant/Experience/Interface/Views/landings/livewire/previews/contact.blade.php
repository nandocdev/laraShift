<!-- Contact Preview -->
<div x-show="block.type === 'contact'" class="w-full relative overflow-hidden py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
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

        <div class="grid items-start" :class="block.variant === 'compact' ? 'grid-cols-1 max-w-2xl mx-auto' : 'lg:grid-cols-2 gap-16'">
            <!-- Form Side -->
            <div class="bg-white dark:bg-zinc-800 p-8 md:p-10 rounded-[2.5rem] shadow-sm border border-zinc-100 dark:border-zinc-700 text-left">
                <form onsubmit="event.preventDefault();" class="space-y-6">
                    <template x-for="field in (block.config.fields || [{type: 'text', label: 'Name'}, {type: 'email', label: 'Email'}, {type: 'textarea', label: 'Message'}])">
                        <div>
                            <label class="block text-sm font-bold mb-2 opacity-80 uppercase tracking-wider" x-text="field.label || 'Field'"></label>
                            <template x-if="field.type === 'textarea'">
                                <textarea 
                                    rows="4" 
                                    class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-base"
                                    :placeholder="field.placeholder || ''"
                                    disabled
                                ></textarea>
                            </template>
                            <template x-if="field.type !== 'textarea'">
                                <input 
                                    type="text" 
                                    class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 py-4 px-5 text-base"
                                    :placeholder="field.placeholder || ''"
                                    disabled
                                >
                            </template>
                        </div>
                    </template>

                    <button 
                        type="button" 
                        class="w-full py-4 bg-primary text-white rounded-2xl font-black text-lg shadow-lg shadow-primary/20 flex items-center justify-center gap-3 cursor-default"
                    >
                        <span x-text="block.config.submit_text || '{{ __('Send Message') }}'"></span>
                        <flux:icon.paper-airplane size="sm" />
                    </button>
                </form>
            </div>

            <!-- Info Side -->
            <template x-if="block.variant !== 'compact'">
                <div class="space-y-12 text-left">
                    <div class="grid sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-1 gap-10">
                        <template x-if="block.config.show_email !== false">
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.envelope size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Email us') }}</h4>
                                    <p class="text-lg opacity-60" x-text="block.config.email || 'hello@example.com'"></p>
                                </div>
                            </div>
                        </template>

                        <template x-if="block.config.show_phone !== false">
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.phone size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Call us') }}</h4>
                                    <p class="text-lg opacity-60" x-text="block.config.phone || '+1 (555) 123-4567'"></p>
                                </div>
                            </div>
                        </template>

                        <template x-if="block.config.show_address !== false">
                            <div class="flex gap-5">
                                <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                                    <flux:icon.map-pin size="sm" />
                                </div>
                                <div>
                                    <h4 class="font-black text-lg mb-1 uppercase tracking-tight">{{ __('Visit us') }}</h4>
                                    <p class="text-lg opacity-60 leading-relaxed" x-text="block.config.address || '742 Evergreen Terrace'"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <template x-if="block.variant === 'map-included' && block.config.map_embed_url">
                        <div class="relative group">
                            <div class="absolute -inset-2 bg-primary/5 rounded-[2.5rem] -z-10 transition"></div>
                            <div class="rounded-[2rem] overflow-hidden shadow-xl border border-zinc-100 dark:border-zinc-700 h-80 bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center">
                                <flux:icon.map size="xl" class="text-zinc-400" />
                                <span class="absolute text-zinc-500 font-bold bg-white/80 px-3 py-1 rounded">{{ __('Map Preview') }}</span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>