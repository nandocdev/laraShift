<!-- Testimonials Preview -->
<div x-show="block.type === 'testimonials'" class="w-full relative overflow-hidden py-20">
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

        <template x-if="block.variant === 'single-featured'">
            <div class="max-w-4xl mx-auto bg-white dark:bg-zinc-800 rounded-[2.5rem] p-8 md:p-16 shadow-xl border border-zinc-100 dark:border-zinc-700 flex flex-col md:flex-row items-center gap-12 relative overflow-hidden">
                <div class="absolute top-10 left-10 opacity-[0.05] pointer-events-none">
                    <flux:icon.chat-bubble-bottom-center-text size="xl" class="scale-[4] text-primary" />
                </div>
                
                <template x-if="(block.config.testimonials || []).length > 0">
                    <div class="contents">
                        <div class="w-40 h-40 md:w-56 md:h-56 flex-shrink-0 relative">
                            <div class="absolute -inset-2 bg-primary/10 rounded-full rotate-6"></div>
                            <template x-if="block.config.show_avatars !== false && block.config.testimonials[0].avatar_url">
                                <img :src="block.config.testimonials[0].avatar_url" class="w-full h-full rounded-full object-cover shadow-lg relative">
                            </template>
                            <template x-if="block.config.show_avatars === false || !block.config.testimonials[0].avatar_url">
                                <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-5xl font-black relative" x-text="(block.config.testimonials[0].name || 'U').charAt(0)"></div>
                            </template>
                        </div>
                        <div class="flex-1 text-center md:text-left relative">
                            <template x-if="block.config.show_rating !== false">
                                <div class="flex gap-1 mb-6 text-amber-400 justify-center md:justify-start">
                                    <template x-for="i in parseInt(block.config.testimonials[0].rating || 5)">
                                        <flux:icon.star variant="solid" size="sm" />
                                    </template>
                                </div>
                            </template>

                            <blockquote class="text-2xl md:text-3xl font-medium mb-8 italic leading-relaxed text-zinc-800 dark:text-zinc-100" x-text="'&quot;' + block.config.testimonials[0].quote + '&quot;'"></blockquote>
                            
                            <div>
                                <p class="text-xl font-black text-primary" x-text="block.config.testimonials[0].name"></p>
                                <p class="text-base font-bold opacity-50" x-text="block.config.testimonials[0].role + (block.config.testimonials[0].company ? ' @ ' + block.config.testimonials[0].company : '')"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'carousel'">
            <div x-data="{ 
                current: 0, 
                count: (block.config.testimonials || []).length,
                autoplay: block.config.autoplay || false,
                interval: null,
                init() {
                    if (this.autoplay && this.count > 1) this.startAutoplay();
                },
                startAutoplay() {
                    this.interval = setInterval(() => { this.current = (this.current + 1) % this.count }, 5000)
                },
                stopAutoplay() {
                    if (this.interval) clearInterval(this.interval);
                }
            }" 
            x-effect="count = (block.config.testimonials || []).length; if(current >= count) current = 0;"
            x-on:mouseenter="stopAutoplay"
            x-on:mouseleave="startAutoplay"
            class="relative max-w-4xl mx-auto">
                <div class="overflow-hidden rounded-[2.5rem] bg-white dark:bg-zinc-800 border border-zinc-100 dark:border-zinc-700 shadow-xl">
                    <div class="flex transition-transform duration-700 ease-in-out" :style="'transform: translateX(-' + (current * 100) + '%)'">
                        <template x-for="testimonial in (block.config.testimonials || [])">
                            <div class="w-full flex-shrink-0 p-8 md:p-16 text-center">
                                <div class="w-20 h-20 mx-auto mb-8 rounded-full overflow-hidden bg-primary/10 flex items-center justify-center">
                                    <template x-if="block.config.show_avatars !== false && testimonial.avatar_url">
                                        <img :src="testimonial.avatar_url" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="block.config.show_avatars === false || !testimonial.avatar_url">
                                        <span class="text-primary font-black text-2xl" x-text="(testimonial.name || 'U').charAt(0)"></span>
                                    </template>
                                </div>
                                <blockquote class="text-xl md:text-2xl font-medium mb-8 italic leading-relaxed" x-text="'&quot;' + testimonial.quote + '&quot;'"></blockquote>
                                <p class="font-black text-primary" x-text="testimonial.name"></p>
                                <p class="text-sm font-bold opacity-40" x-text="testimonial.role + (testimonial.company ? ' @ ' + testimonial.company : '')"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <template x-if="(block.config.testimonials || []).length > 1">
                    <div class="flex justify-center gap-2 mt-8">
                        <template x-for="(t, index) in (block.config.testimonials || [])">
                            <button 
                                x-on:click="current = index"
                                class="h-2 rounded-full transition-all duration-300"
                                :class="current === index ? 'w-8 bg-primary' : 'w-2 bg-zinc-300 dark:bg-zinc-700'"
                            ></button>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="block.variant === 'grid' || !block.variant">
            <div class="grid gap-8 md:gap-10"
                 :class="[
                     block.config.columns_count == 2 ? 'sm:grid-cols-2' : '',
                     block.config.columns_count == 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : '',
                     (!block.config.columns_count || block.config.columns_count == 3) ? 'sm:grid-cols-2 lg:grid-cols-3' : ''
                 ]">
                <template x-for="testimonial in (block.config.testimonials || [])">
                    <div class="bg-white dark:bg-zinc-800 p-8 rounded-3xl shadow-sm border border-zinc-100 dark:border-zinc-700 flex flex-col h-full hover:shadow-xl hover:-translate-y-1 transition-all duration-300 text-left">
                        <template x-if="block.config.show_rating !== false">
                            <div class="flex gap-1 mb-6 text-amber-400">
                                <template x-for="i in parseInt(testimonial.rating || 5)">
                                    <flux:icon.star variant="solid" size="xs" />
                                </template>
                            </div>
                        </template>

                        <blockquote class="text-lg opacity-90 italic flex-1 leading-relaxed mb-8" x-text="'&quot;' + testimonial.quote + '&quot;'"></blockquote>
                        
                        <div class="flex items-center gap-4 mt-auto">
                            <template x-if="block.config.show_avatars !== false">
                                <div class="w-12 h-12 flex-shrink-0">
                                    <template x-if="testimonial.avatar_url">
                                        <img :src="testimonial.avatar_url" class="w-full h-full rounded-full object-cover ring-2 ring-zinc-50 dark:ring-zinc-700">
                                    </template>
                                    <template x-if="!testimonial.avatar_url">
                                        <div class="w-full h-full rounded-full bg-primary/10 flex items-center justify-center text-primary text-sm font-black" x-text="(testimonial.name || 'U').charAt(0)"></div>
                                    </template>
                                </div>
                            </template>
                            <div>
                                <p class="text-sm font-black tracking-tight" x-text="testimonial.name"></p>
                                <p class="text-xs font-bold opacity-50" x-text="testimonial.role + (testimonial.company ? ' @ ' + testimonial.company : '')"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>