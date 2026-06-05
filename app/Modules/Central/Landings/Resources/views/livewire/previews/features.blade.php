<!-- Features Preview -->
<div x-show="block.type === 'features'" class="w-full relative overflow-hidden py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.config.headline || block.config.subtitle">
            <div class="text-center mb-16">
                <template x-if="block.config.headline">
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight" x-text="block.config.headline"></h2>
                </template>
                <template x-if="block.config.subtitle">
                    <p class="mt-4 text-xl opacity-70 max-w-2xl mx-auto" x-text="block.config.subtitle"></p>
                </template>
            </div>
        </template>

        <!-- Alternating Rows Variant -->
        <template x-if="block.variant === 'alternating-rows'">
            <div class="space-y-24 md:space-y-32">
                <template x-for="(feature, index) in (block.config.features || [])">
                    <div class="flex flex-col lg:flex-row items-center gap-12 md:gap-20" :class="index % 2 !== 0 ? 'lg:flex-row-reverse' : ''">
                        <div class="flex-1 space-y-6 text-left">
                            <template x-if="block.config.show_icons !== false">
                                <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-primary/10 text-primary">
                                    <flux:icon icon="star" variant="outline" />
                                </div>
                            </template>
                            <h3 class="text-2xl md:text-3xl font-bold" x-text="feature.title"></h3>
                            <p class="text-lg opacity-80 leading-relaxed" x-text="feature.description"></p>
                            
                            <template x-if="feature.cta_text">
                                <div class="pt-2">
                                    <span class="inline-flex items-center text-primary font-bold group">
                                        <span x-text="feature.cta_text"></span>
                                        <flux:icon.arrow-right class="ml-2 transition-transform group-hover:translate-x-1" size="xs" />
                                    </span>
                                </div>
                            </template>
                        </div>
                        <div class="flex-1 w-full">
                            <div class="relative group">
                                <div class="absolute -inset-4 bg-primary/5 rounded-[2rem] transition-transform group-hover:scale-105 -z-10"></div>
                                <template x-if="feature.image_url">
                                    <img :src="feature.image_url" class="rounded-3xl shadow-xl w-full object-cover">
                                </template>
                                <template x-if="!feature.image_url">
                                    <div class="bg-zinc-100 dark:bg-zinc-800 rounded-3xl aspect-video flex items-center justify-center text-zinc-300">
                                        <flux:icon.photo size="xl" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <!-- Grid Variants -->
        <template x-if="block.variant !== 'alternating-rows'">
            <div class="grid gap-8 md:gap-12" 
                :class="[
                    block.config.columns_count == 1 ? 'grid-cols-1 max-w-2xl mx-auto' : '',
                    block.config.columns_count == 2 ? 'sm:grid-cols-2' : '',
                    (!block.config.columns_count || block.config.columns_count == 3) ? 'sm:grid-cols-2 lg:grid-cols-3' : '',
                    block.config.columns_count == 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : ''
                ]"
            >
                <template x-for="feature in (block.config.features || [])">
                    <div class="group h-full" :class="block.variant === 'cards' ? 'bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-sm border border-zinc-100 dark:border-zinc-700' : ''">
                        <div class="flex flex-col h-full text-left items-start">
                            <template x-if="block.config.show_icons !== false">
                                <div class="mb-6 flex items-center justify-center w-12 h-12 rounded-xl bg-primary/10 text-primary">
                                    <flux:icon icon="star" size="sm" />
                                </div>
                            </template>
                            
                            <h3 class="text-xl font-bold mb-3" x-text="feature.title"></h3>
                            <p class="opacity-70 leading-relaxed flex-1" x-text="feature.description"></p>
                            
                            <template x-if="feature.cta_text">
                                <span class="mt-6 inline-flex items-center text-sm font-bold text-primary">
                                    <span x-text="feature.cta_text"></span>
                                    <flux:icon.arrow-right class="ml-1" size="xs" />
                                </span>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>