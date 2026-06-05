<!-- Statistics Preview -->
<div x-show="block.type === 'statistics'" class="w-full relative overflow-hidden py-20">
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

        <template x-if="block.variant === 'horizontal'">
            <div class="flex flex-wrap justify-center gap-12 md:gap-24">
                <template x-for="stat in (block.config.stats || [])">
                    <div class="text-center group hover:scale-105 transition-transform duration-300">
                        <div class="text-4xl md:text-6xl font-black mb-2 flex items-center justify-center tracking-tighter">
                            <template x-if="stat.prefix"><span class="text-2xl md:text-3xl mr-1 opacity-50 font-bold" x-text="stat.prefix"></span></template>
                            <span class="text-primary" x-text="stat.value"></span>
                            <template x-if="stat.suffix"><span class="text-2xl md:text-3xl ml-1 opacity-50 font-bold" x-text="stat.suffix"></span></template>
                        </div>
                        <div class="text-sm md:text-base font-bold opacity-60 uppercase tracking-widest" x-text="stat.label"></div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'grid'">
            <div class="grid gap-8" 
                 :class="[
                     block.config.columns_count == 2 ? 'grid-cols-2' : '',
                     block.config.columns_count == 3 ? 'grid-cols-2 lg:grid-cols-3' : '',
                     block.config.columns_count == 6 ? 'grid-cols-2 md:grid-cols-3 lg:grid-cols-6' : '',
                     (!block.config.columns_count || block.config.columns_count == 4) ? 'grid-cols-2 lg:grid-cols-4' : ''
                 ]">
                <template x-for="stat in (block.config.stats || [])">
                    <div class="bg-white/5 p-8 md:p-10 rounded-[2.5rem] border border-zinc-200 dark:border-zinc-700 text-center hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                        <template x-if="stat.icon">
                            <div class="mb-6 flex justify-center text-primary">
                                <div class="p-4 bg-primary/10 rounded-2xl">
                                    <flux:icon icon="star" size="lg" />
                                </div>
                            </div>
                        </template>
                        <div class="text-4xl md:text-5xl font-black mb-2 tracking-tighter">
                            <template x-if="stat.prefix"><span class="text-xl opacity-40" x-text="stat.prefix"></span></template>
                            <span x-text="stat.value"></span>
                            <template x-if="stat.suffix"><span class="text-xl opacity-40" x-text="stat.suffix"></span></template>
                        </div>
                        <div class="text-sm opacity-50 font-black uppercase tracking-widest" x-text="stat.label"></div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'highlighted'">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-16 md:gap-24">
                <template x-if="block.config.stats && block.config.stats.length > 0">
                    <div class="text-center lg:text-left">
                        <div class="text-7xl md:text-9xl font-black text-primary mb-4 tracking-tighter">
                            <template x-if="block.config.stats[0].prefix"><span class="text-3xl md:text-5xl opacity-40" x-text="block.config.stats[0].prefix"></span></template>
                            <span x-text="block.config.stats[0].value"></span>
                            <template x-if="block.config.stats[0].suffix"><span class="text-3xl md:text-5xl opacity-40" x-text="block.config.stats[0].suffix"></span></template>
                        </div>
                        <div class="text-2xl md:text-3xl font-black opacity-80 uppercase tracking-tight" x-text="block.config.stats[0].label"></div>
                    </div>
                </template>
                
                <div class="grid grid-cols-2 gap-12 md:gap-20">
                    <template x-for="(stat, index) in (block.config.stats || [])">
                        <template x-if="index > 0">
                            <div class="text-center lg:text-left">
                                <div class="text-4xl md:text-5xl font-black mb-2 tracking-tighter text-zinc-900 dark:text-white">
                                    <template x-if="stat.prefix"><span class="text-xl opacity-30" x-text="stat.prefix"></span></template>
                                    <span x-text="stat.value"></span>
                                    <template x-if="stat.suffix"><span class="text-xl opacity-30" x-text="stat.suffix"></span></template>
                                </div>
                                <div class="text-xs md:text-sm opacity-50 font-bold uppercase tracking-widest" x-text="stat.label"></div>
                            </div>
                        </template>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>