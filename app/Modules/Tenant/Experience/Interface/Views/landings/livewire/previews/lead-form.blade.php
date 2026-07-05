<!-- Lead Form Preview -->
<div x-show="block.type === 'lead-form'" class="w-full relative overflow-hidden py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <template x-if="block.variant === 'inline' || block.variant === 'multi-field'">
            <div>
                <div class="max-w-3xl mx-auto text-center mb-12">
                    <template x-if="block.config.headline">
                        <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight tracking-tight" x-text="block.config.headline"></h2>
                    </template>
                    <template x-if="block.config.subtitle">
                        <p class="mt-4 text-xl opacity-70" x-text="block.config.subtitle"></p>
                    </template>
                </div>

                <div class="max-w-2xl mx-auto bg-white dark:bg-zinc-800 p-8 md:p-12 rounded-[2.5rem] shadow-2xl border border-zinc-100 dark:border-zinc-700 text-left">
                    <form onsubmit="event.preventDefault();" class="space-y-6">
                        <div class="grid grid-cols-1 gap-6" :class="block.variant === 'multi-field' ? 'sm:grid-cols-2' : ''">
                            <template x-for="field in (block.config.fields || [{type: 'text', label: 'Name'}, {type: 'email', label: 'Email'}])">
                                <div :class="(field.type === 'textarea' || block.variant === 'inline') ? 'sm:col-span-2' : ''">
                                    <label class="block text-sm font-bold mb-2 opacity-80 uppercase tracking-widest text-zinc-900 dark:text-white" x-text="field.label || 'Field'"></label>
                                    <template x-if="field.type === 'textarea'">
                                        <textarea 
                                            rows="4" 
                                            class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 text-base transition"
                                            :placeholder="field.placeholder || ''"
                                            disabled
                                        ></textarea>
                                    </template>
                                    <template x-if="field.type !== 'textarea'">
                                        <input 
                                            type="text" 
                                            class="w-full rounded-2xl border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 py-4 px-5 text-base transition"
                                            :placeholder="field.placeholder || ''"
                                            disabled
                                        >
                                    </template>
                                </div>
                            </template>
                        </div>

                        <button 
                            type="button" 
                            class="w-full py-4 mt-4 bg-primary text-white rounded-2xl font-black text-lg shadow-xl shadow-primary/20 flex items-center justify-center gap-3 cursor-default"
                        >
                            <span x-text="block.config.submit_text || '{{ __('Get Started Now') }}'"></span>
                            <flux:icon.arrow-right size="sm" />
                        </button>
                        
                        <template x-if="block.config.show_social_proof">
                            <div class="mt-6 flex items-center justify-center gap-2 opacity-60 text-sm font-medium">
                                <flux:icon.lock-closed size="xs" />
                                <span x-text="block.config.show_social_proof"></span>
                            </div>
                        </template>
                    </form>
                </div>
            </div>
        </template>

        <template x-if="block.variant === 'newsletter'">
            <div class="max-w-4xl mx-auto bg-white dark:bg-zinc-800 p-8 md:p-16 rounded-[3rem] shadow-2xl border border-zinc-100 dark:border-zinc-700 text-center">
                <template x-if="block.config.headline">
                    <h2 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-4" x-text="block.config.headline"></h2>
                </template>
                <template x-if="block.config.subtitle">
                    <p class="text-lg md:text-xl opacity-70 max-w-2xl mx-auto mb-10" x-text="block.config.subtitle"></p>
                </template>

                <div class="max-w-md mx-auto relative">
                    <input 
                        type="text" 
                        class="w-full rounded-full border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 py-4 md:py-5 pl-6 pr-36 text-base shadow-inner"
                        :placeholder="(block.config.fields && block.config.fields[0] ? block.config.fields[0].placeholder : '{{ __('Enter your email') }}')"
                        disabled
                    >
                    <button 
                        type="button" 
                        class="absolute right-2 top-2 bottom-2 px-6 bg-primary text-white rounded-full font-bold flex items-center justify-center gap-2 cursor-default"
                    >
                        <span x-text="block.config.submit_text || '{{ __('Subscribe') }}'"></span>
                    </button>
                </div>
                
                <template x-if="block.config.show_social_proof">
                    <p class="mt-6 text-sm opacity-50 font-medium" x-text="block.config.show_social_proof"></p>
                </template>
            </div>
        </template>
    </div>
</div>