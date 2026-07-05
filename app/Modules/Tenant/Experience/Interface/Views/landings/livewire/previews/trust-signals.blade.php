<!-- Trust Signals Preview -->
<div x-show="block.type === 'trust-signals'" class="w-full relative overflow-hidden py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.config.section_title">
            <div class="text-center mb-12">
                <h3 class="text-sm font-black uppercase tracking-widest opacity-40" x-text="block.config.section_title"></h3>
            </div>
        </template>

        <template x-if="block.variant === 'logo-strip'">
            <div class="flex flex-wrap justify-center items-center gap-10 md:gap-20">
                <template x-for="item in (block.config.items || [])">
                    <div class="h-8 md:h-12 transition-all duration-300"
                         :class="[
                             block.config.grayscale !== false ? 'grayscale opacity-50' : 'opacity-80',
                             (block.config.grayscale !== false && block.config.show_hover_color !== false) ? 'hover:grayscale-0 hover:opacity-100' : 'hover:opacity-100'
                         ]"
                    >
                        <template x-if="item.logo_url">
                            <img :src="item.logo_url" :alt="item.alt" class="h-full w-auto object-contain">
                        </template>
                        <template x-if="!item.logo_url">
                            <div class="font-black text-2xl md:text-3xl tracking-tighter flex items-center h-full" x-text="item.alt || 'Company'"></div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'certifications'">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 md:gap-8">
                <template x-for="item in (block.config.items || [])">
                    <div class="group flex flex-col items-center text-center p-8 bg-zinc-50 dark:bg-zinc-800/50 rounded-3xl border border-zinc-100 dark:border-zinc-700 hover:shadow-xl hover:bg-white dark:hover:bg-zinc-800 hover:-translate-y-1 transition-all duration-300">
                        <div class="w-20 h-20 mb-6 flex items-center justify-center transition-all duration-300"
                             :class="[
                                 block.config.grayscale !== false ? 'grayscale opacity-50' : 'opacity-80',
                                 (block.config.grayscale !== false && block.config.show_hover_color !== false) ? 'hover:grayscale-0 hover:opacity-100' : 'hover:opacity-100'
                             ]"
                        >
                            <template x-if="item.logo_url">
                                <img :src="item.logo_url" :alt="item.alt" class="max-h-full object-contain">
                            </template>
                            <template x-if="!item.logo_url">
                                <flux:icon.shield-check size="xl" class="text-zinc-400 group-hover:text-primary transition-colors" />
                            </template>
                        </div>
                        <h4 class="font-black text-base mb-2 text-zinc-900 dark:text-white" x-text="item.alt || 'Certification'"></h4>
                        <template x-if="item.description">
                            <p class="text-sm opacity-60 leading-relaxed max-w-[200px]" x-text="item.description"></p>
                        </template>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>