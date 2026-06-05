<!-- About Preview -->
<div x-show="block.type === 'about'" class="w-full relative overflow-hidden py-20 text-left">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <template x-if="block.variant === 'team-intro'">
            <div>
                <div class="text-center mb-20">
                    <template x-if="block.config.headline">
                        <h2 class="text-3xl font-extrabold sm:text-5xl tracking-tight mb-4" x-text="block.config.headline"></h2>
                    </template>
                    <template x-if="block.config.subtitle">
                        <p class="mt-4 text-xl opacity-70 max-w-2xl mx-auto" x-text="block.config.subtitle"></p>
                    </template>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-8 gap-y-16">
                    <template x-for="(member, index) in (block.config.team_members || [])">
                        <div class="text-center group">
                            <div class="relative mb-6 inline-block">
                                <div class="absolute -inset-2 bg-primary/5 rounded-[2rem] transition-transform duration-500 group-hover:rotate-6"></div>
                                <div class="w-32 h-32 md:w-48 md:h-48 rounded-[2rem] overflow-hidden bg-zinc-100 dark:bg-zinc-800 shadow-md border border-zinc-50 dark:border-zinc-700 relative">
                                    <template x-if="member.avatar_url">
                                        <img :src="member.avatar_url" :alt="member.name" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                    </template>
                                    <template x-if="!member.avatar_url">
                                        <div class="w-full h-full flex items-center justify-center text-zinc-300 dark:text-zinc-600">
                                            <flux:icon.user size="xl" />
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <h3 class="font-black text-xl tracking-tight" x-text="member.name"></h3>
                            <p class="text-sm font-bold text-primary opacity-80 uppercase tracking-widest mt-1" x-text="member.role"></p>
                            <template x-if="member.bio">
                                <p class="mt-3 text-sm opacity-60 max-w-[200px] mx-auto leading-relaxed" x-text="member.bio"></p>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        
        <template x-if="block.variant !== 'team-intro'">
            <div class="flex flex-col lg:flex-row items-center gap-16 md:gap-24" :class="block.variant === 'image-left' ? 'lg:flex-row-reverse' : ''">
                <div class="flex-1 flex flex-col space-y-8" :class="block.styles.text_align === 'center' ? 'items-center text-center' : (block.styles.text_align === 'right' ? 'items-end text-right' : 'items-start text-left')">
                    <div class="space-y-4">
                        <template x-if="block.config.headline">
                            <h2 class="text-3xl font-extrabold sm:text-5xl tracking-tight leading-tight" x-text="block.config.headline"></h2>
                        </template>
                        <template x-if="block.config.subtitle">
                            <p class="text-xl font-bold text-primary opacity-90 uppercase tracking-widest" x-text="block.config.subtitle"></p>
                        </template>
                    </div>
                    
                    <div class="text-lg opacity-70 leading-relaxed space-y-4" x-text="block.config.description"></div>

                    <template x-if="block.config.metrics && block.config.metrics.length > 0">
                        <div class="grid grid-cols-2 gap-x-12 gap-y-8 pt-4 w-full">
                            <template x-for="metric in block.config.metrics">
                                <div class="border-l-4 border-primary/20 pl-6 text-left">
                                    <div class="text-4xl font-black text-primary tracking-tighter" x-text="metric.value"></div>
                                    <div class="text-sm font-bold opacity-50 uppercase tracking-wider mt-1" x-text="metric.label"></div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="block.config.show_cta">
                        <div class="pt-4">
                            <span class="inline-flex items-center px-8 py-4 bg-primary text-white font-black rounded-2xl hover:opacity-90 transition shadow-lg shadow-primary/20 hover:scale-[1.02]">
                                <span x-text="block.config.cta_text || '{{ __('Learn More') }}'"></span>
                                <flux:icon.arrow-right class="ml-2" size="xs" />
                            </span>
                        </div>
                    </template>
                </div>

                <template x-if="block.variant !== 'story'">
                    <div class="flex-1 w-full relative">
                        <div class="absolute -inset-4 bg-primary/5 rounded-[3rem] rotate-3 -z-10"></div>
                        <div class="absolute -inset-4 border-2 border-primary/10 rounded-[3rem] -rotate-2 -z-10"></div>
                        <div class="rounded-[2.5rem] overflow-hidden shadow-2xl border border-zinc-100 dark:border-zinc-700 aspect-[4/5] lg:aspect-auto">
                            <template x-if="block.config.image_url">
                                <img :src="block.config.image_url" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!block.config.image_url">
                                <div class="w-full h-[500px] bg-zinc-50 dark:bg-zinc-800 flex items-center justify-center text-zinc-300">
                                    <flux:icon.photo size="xl" />
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>