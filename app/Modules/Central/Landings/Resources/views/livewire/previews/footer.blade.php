<!-- Footer Preview -->
<div x-show="block.type === 'footer'" class="w-full relative py-12 md:py-20 text-left border-t" 
     :class="[
        block.styles.background === 'white' || block.styles.background === 'surface' ? 'border-zinc-200 dark:border-zinc-800' : 'border-white/10'
     ]"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.variant === 'simple'">
            <div class="flex flex-col md:flex-row justify-between items-center gap-8">
                <div class="flex flex-col items-center md:items-start gap-4">
                    <template x-if="block.config.logo_url">
                        <img :src="block.config.logo_url" :alt="block.config.logo_alt" class="h-8 w-auto">
                    </template>
                    <template x-if="!block.config.logo_url">
                        <span class="text-xl font-black tracking-tighter">LaraShift</span>
                    </template>
                </div>
                
                <nav class="flex flex-wrap justify-center gap-x-8 gap-y-4">
                    <template x-for="link in (block.config.legal_links || [])">
                        <span class="text-sm font-medium transition cursor-pointer" 
                              :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500 hover:text-primary' : 'text-gray-400 hover:text-white'"
                              x-text="link.label"></span>
                    </template>
                </nav>
                
                <div class="text-sm" :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500' : 'text-gray-400'" x-text="block.config.copyright_text || '© {{ date('Y') }} All rights reserved.'"></div>
            </div>
        </template>

        <template x-if="block.variant === 'multi-column'">
            <div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-12 md:gap-8">
                    <div class="col-span-2 space-y-6">
                        <template x-if="block.config.logo_url">
                            <img :src="block.config.logo_url" :alt="block.config.logo_alt" class="h-8 w-auto">
                        </template>
                        <template x-if="!block.config.logo_url">
                            <span class="text-2xl font-black tracking-tighter block">LaraShift</span>
                        </template>
                        
                        <template x-if="block.config.description">
                            <p class="text-base max-w-xs leading-relaxed" 
                               :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500' : 'text-gray-400'"
                               x-text="block.config.description"></p>
                        </template>

                        <template x-if="block.config.show_social && block.config.social_links">
                            <div class="flex gap-4">
                                <template x-for="social in block.config.social_links">
                                    <span class="transition transform hover:scale-110 cursor-pointer"
                                          :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500 hover:text-primary' : 'text-gray-400 hover:text-white'">
                                        <!-- Simplified social icon for preview -->
                                        <flux:icon.link size="sm" />
                                    </span>
                                </template>
                            </div>
                        </template>
                    </div>
                    
                    <template x-for="col in (block.config.columns || [])">
                        <div class="col-span-1">
                            <h3 class="text-sm font-bold uppercase tracking-widest mb-6" x-text="col.title"></h3>
                            <ul class="space-y-4">
                                <template x-for="link in (col.links || [])">
                                    <li>
                                        <span class="text-sm transition cursor-pointer"
                                              :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500 hover:text-primary' : 'text-gray-400 hover:text-white'"
                                              x-text="link.label"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="block.config.show_newsletter">
                        <div class="col-span-2 lg:col-span-2 space-y-6">
                            <h3 class="text-sm font-bold uppercase tracking-widest" x-text="block.config.newsletter_title || '{{ __('Subscribe to our newsletter') }}'"></h3>
                            <p class="text-sm" 
                               :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'text-zinc-500' : 'text-gray-400'"
                               x-text="block.config.newsletter_description || '{{ __('The latest news, articles, and resources, sent to your inbox weekly.') }}'"></p>
                            <form onsubmit="event.preventDefault();" class="flex gap-2">
                                <input type="email" placeholder="{{ __('Email address') }}" disabled class="flex-1 min-w-0 px-4 py-2 bg-white/5 border rounded-lg text-sm" :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'border-zinc-200 dark:border-zinc-800' : 'border-white/10'">
                                <button type="button" class="px-4 py-2 bg-primary text-white rounded-lg font-bold text-sm hover:opacity-90 transition">{{ __('Subscribe') }}</button>
                            </form>
                        </div>
                    </template>
                </div>
                
                <div class="border-t mt-16 pt-8 flex flex-col md:flex-row justify-between items-center gap-6 text-sm"
                     :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'border-zinc-200 dark:border-zinc-800 text-zinc-500' : 'border-white/10 text-gray-400'">
                    <div x-text="block.config.copyright_text || '© {{ date('Y') }} All rights reserved.'"></div>
                    <div class="flex flex-wrap justify-center gap-x-8 gap-y-2 font-medium">
                        <template x-for="link in (block.config.legal_links || [])">
                            <span class="transition cursor-pointer"
                                  :class="block.styles.background === 'white' || block.styles.background === 'surface' ? 'hover:text-primary' : 'hover:text-white'"
                                  x-text="link.label"></span>
                        </template>
                    </div>
                </div>
            </div>
        </template>

    </div>
</div>