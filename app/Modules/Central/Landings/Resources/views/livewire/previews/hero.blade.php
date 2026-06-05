<!-- Hero Preview -->
<div x-show="block.type === 'hero'" class="w-full relative overflow-hidden" 
    :class="[
        (block.variant === 'fullscreen' || block.styles.height === 'screen') ? 'min-h-[800px] flex items-center' : 'py-20',
        block.styles.text_align === 'center' ? 'text-center' : (block.styles.text_align === 'right' ? 'text-right' : 'text-left')
    ]"
>
    <!-- Background Image Variant -->
    <template x-if="block.variant === 'bg-image'">
        <div class="absolute inset-0 z-0">
            <template x-if="block.config.image_url">
                <img :src="block.config.image_url" class="w-full h-full object-cover">
            </template>
            <div class="absolute inset-0 bg-black" :style="'opacity: ' + ((block.styles.overlay_opacity || 50) / 100)"></div>
        </div>
    </template>

    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
        <!-- Centered Layouts -->
        <template x-if="['centered', 'bg-image', 'fullscreen'].includes(block.variant)">
            <div class="max-w-4xl" :class="block.styles.text_align === 'center' ? 'mx-auto' : (block.styles.text_align === 'right' ? 'ml-auto' : '')">
                
                <template x-if="block.config.badge_text">
                    <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-6 border border-primary/20" x-text="block.config.badge_text"></span>
                </template>
                
                <h1 class="text-4xl tracking-tight font-black sm:text-5xl md:text-7xl leading-tight" x-text="block.config.headline || 'Hero Headline'"></h1>
                
                <template x-if="block.config.subtitle">
                    <p class="mt-6 text-lg sm:text-xl opacity-90 max-w-2xl" :class="block.styles.text_align === 'center' ? 'mx-auto' : (block.styles.text_align === 'right' ? 'ml-auto' : '')" x-text="block.config.subtitle"></p>
                </template>
                
                <div class="mt-10 flex flex-wrap gap-4" :class="block.styles.text_align === 'center' ? 'justify-center' : (block.styles.text_align === 'right' ? 'justify-end' : '')">
                    <template x-if="block.config.button_primary_text">
                        <div class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-lg shadow-lg shadow-primary/20" x-text="block.config.button_primary_text"></div>
                    </template>
                    
                    <template x-if="block.config.show_secondary_button !== false && block.config.button_secondary_text">
                        <div class="px-8 py-4 border border-current/20 rounded-xl font-bold text-lg" :class="block.variant === 'bg-image' ? 'bg-white/10 backdrop-blur-md' : 'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white'" x-text="block.config.button_secondary_text"></div>
                    </template>
                </div>

                <template x-if="block.config.show_stats && block.config.stats && block.config.stats.length > 0">
                    <div class="mt-16 pt-8 border-t border-current/10 flex flex-wrap gap-12" :class="block.styles.text_align === 'center' ? 'justify-center' : (block.styles.text_align === 'right' ? 'justify-end' : '')">
                        <template x-for="stat in block.config.stats">
                            <div>
                                <div class="text-3xl font-black" x-text="stat.value"></div>
                                <div class="text-xs uppercase tracking-widest opacity-60 font-bold" x-text="stat.label"></div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <!-- Split Layouts -->
        <template x-if="['split', 'image-left'].includes(block.variant)">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div :class="block.variant === 'image-left' ? 'lg:order-2' : ''">
                    <template x-if="block.config.badge_text">
                        <span class="inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium bg-primary/10 text-primary mb-6" x-text="block.config.badge_text"></span>
                    </template>
                    
                    <h1 class="text-4xl tracking-tight font-black sm:text-5xl md:text-6xl leading-tight" x-text="block.config.headline || 'Hero Headline'"></h1>
                    
                    <template x-if="block.config.subtitle">
                        <p class="mt-6 text-lg opacity-80 leading-relaxed" x-text="block.config.subtitle"></p>
                    </template>
                    
                    <div class="mt-10 flex flex-wrap gap-4" :class="block.styles.text_align === 'center' ? 'justify-center' : (block.styles.text_align === 'right' ? 'justify-end' : '')">
                        <template x-if="block.config.button_primary_text">
                            <div class="px-8 py-4 bg-primary text-white rounded-xl font-bold" x-text="block.config.button_primary_text"></div>
                        </template>
                        <template x-if="block.config.show_secondary_button !== false && block.config.button_secondary_text">
                            <div class="px-8 py-4 bg-zinc-100 dark:bg-zinc-800 rounded-xl font-bold text-zinc-900 dark:text-white" x-text="block.config.button_secondary_text"></div>
                        </template>
                    </div>
                </div>

                <div :class="block.variant === 'image-left' ? 'lg:order-1' : ''">
                    <div class="relative">
                        <div class="absolute -inset-4 bg-primary/10 rounded-[3rem] rotate-3 -z-10"></div>
                        <template x-if="block.config.image_url">
                            <img class="w-full rounded-[2.5rem] shadow-2xl" :src="block.config.image_url">
                        </template>
                        <template x-if="!block.config.image_url">
                            <div class="aspect-square bg-zinc-100 dark:bg-zinc-800 rounded-[2.5rem] flex items-center justify-center text-zinc-400">
                                <flux:icon.photo size="xl" />
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>
