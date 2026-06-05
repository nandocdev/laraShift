<!-- Gallery Preview -->
<div x-show="block.type === 'gallery'" class="w-full relative overflow-hidden py-20">
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

        <template x-if="block.variant === 'grid'">
            <div class="grid gap-6 md:gap-8"
                 :class="[
                     block.config.columns_count == 2 ? 'sm:grid-cols-2' : '',
                     block.config.columns_count == 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : '',
                     (!block.config.columns_count || block.config.columns_count == 3) ? 'sm:grid-cols-2 lg:grid-cols-3' : ''
                 ]">
                <template x-for="image in (block.config.images || [])">
                    <div class="group relative overflow-hidden rounded-[2rem] shadow-sm hover:shadow-xl transition-all duration-500 aspect-square">
                        <template x-if="image.url">
                            <img :src="image.url" :alt="image.alt" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        </template>
                        <template x-if="!image.url">
                            <div class="w-full h-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                <flux:icon.photo size="xl" />
                            </div>
                        </template>
                        
                        <template x-if="block.config.show_captions && image.caption">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <p class="text-white font-bold text-lg translate-y-4 group-hover:translate-y-0 transition-transform duration-300" x-text="image.caption"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'masonry'">
            <div class="gap-6 md:gap-8 space-y-6 md:space-y-8"
                 :class="[
                     block.config.columns_count == 2 ? 'columns-1 sm:columns-2' : '',
                     block.config.columns_count == 4 ? 'columns-1 sm:columns-2 lg:columns-4' : '',
                     (!block.config.columns_count || block.config.columns_count == 3) ? 'columns-1 sm:columns-2 lg:columns-3' : ''
                 ]">
                <template x-for="image in (block.config.images || [])">
                    <div class="group relative overflow-hidden rounded-[2rem] shadow-sm hover:shadow-xl transition-all duration-500 break-inside-avoid">
                        <template x-if="image.url">
                            <img :src="image.url" :alt="image.alt" class="w-full h-auto object-cover transition-transform duration-700 group-hover:scale-110">
                        </template>
                        <template x-if="!image.url">
                            <div class="w-full aspect-square bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                <flux:icon.photo size="xl" />
                            </div>
                        </template>
                        
                        <template x-if="block.config.show_captions && image.caption">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-8 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <p class="text-white font-bold text-lg translate-y-4 group-hover:translate-y-0 transition-transform duration-300" x-text="image.caption"></p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'carousel'">
            <div class="relative max-w-5xl mx-auto rounded-[3rem] overflow-hidden shadow-2xl border border-zinc-100 dark:border-zinc-700 bg-black group"
                 x-data="{ current: 0, count: (block.config.images || []).length }"
                 x-effect="count = (block.config.images || []).length; if(current >= count) current = 0;">
                
                <div class="flex transition-transform duration-700 ease-[cubic-bezier(0.25,1,0.5,1)] h-[500px] md:h-[600px]" :style="'transform: translateX(-' + (current * 100) + '%)'">
                    <template x-for="image in (block.config.images || [])">
                        <div class="w-full h-full flex-shrink-0 relative">
                            <template x-if="image.url">
                                <img :src="image.url" :alt="image.alt" class="w-full h-full object-cover opacity-90">
                            </template>
                            <template x-if="!image.url">
                                <div class="w-full h-full bg-zinc-800 flex items-center justify-center text-zinc-600">
                                    <flux:icon.photo size="xl" />
                                </div>
                            </template>
                            
                            <template x-if="block.config.show_captions && image.caption">
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent p-10 pt-32">
                                    <p class="text-white font-bold text-2xl md:text-3xl tracking-tight" x-text="image.caption"></p>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
                
                <template x-if="(block.config.images || []).length > 1">
                    <div>
                        <div class="absolute inset-y-0 left-6 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <button x-on:click="current = (current - 1 + count) % count" class="p-4 bg-white/10 hover:bg-white/30 backdrop-blur-md rounded-full text-white transition transform hover:scale-110">
                                <flux:icon.chevron-left size="sm" />
                            </button>
                        </div>
                        <div class="absolute inset-y-0 right-6 flex items-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            <button x-on:click="current = (current + 1) % count" class="p-4 bg-white/10 hover:bg-white/30 backdrop-blur-md rounded-full text-white transition transform hover:scale-110">
                                <flux:icon.chevron-right size="sm" />
                            </button>
                        </div>

                        <div class="absolute bottom-6 left-0 right-0 flex justify-center gap-3">
                            <template x-for="(i, index) in (block.config.images || [])">
                                <button 
                                    x-on:click="current = index"
                                    class="h-2 rounded-full transition-all duration-500 shadow-sm"
                                    :class="current === index ? 'w-10 bg-white' : 'w-2 bg-white/40 hover:bg-white/60'"
                                ></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>