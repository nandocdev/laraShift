<!-- Pricing Preview -->
<div x-show="block.type === 'pricing'" class="w-full relative overflow-hidden py-20" x-data="{ billing: 'monthly' }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <template x-if="block.config.headline || block.config.subtitle">
            <div class="text-center mb-16">
                <template x-if="block.config.headline">
                    <h2 class="text-3xl font-extrabold sm:text-5xl leading-tight" x-text="block.config.headline"></h2>
                </template>
                <template x-if="block.config.subtitle">
                    <p class="mt-4 text-xl opacity-70 max-w-2xl mx-auto" x-text="block.config.subtitle"></p>
                </template>

                <template x-if="block.config.show_toggle">
                    <div class="mt-10 flex justify-center items-center gap-4">
                        <span class="text-sm font-bold tracking-wide transition" :class="billing === 'monthly' ? 'text-primary' : 'opacity-50'">{{ __('Monthly') }}</span>
                        <button 
                            type="button"
                            x-on:click="billing = billing === 'monthly' ? 'annual' : 'monthly'"
                            class="relative inline-flex h-7 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-zinc-200 dark:bg-zinc-700"
                        >
                            <span 
                                :class="billing === 'annual' ? 'translate-x-5' : 'translate-x-0'"
                                class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                            ></span>
                        </button>
                        <span class="text-sm font-bold tracking-wide transition" :class="billing === 'annual' ? 'text-primary' : 'opacity-50'">
                            {{ __('Annual') }}
                            <template x-if="block.config.annual_discount_text">
                                <span class="ml-1 text-xs text-emerald-500 font-black uppercase tracking-tighter" x-text="'(' + block.config.annual_discount_text + ')'"></span>
                            </template>
                        </span>
                    </div>
                </template>
            </div>
        </template>

        <div class="grid gap-8 lg:grid-cols-3 items-center text-left">
            <template x-for="plan in (block.config.plans || [])">
                <div class="relative flex flex-col p-8 md:p-10 rounded-[2.5rem] shadow-sm border transition-all duration-500"
                     :class="plan.is_featured ? 'bg-white dark:bg-zinc-800 border-primary ring-4 ring-primary/10 scale-105 z-10 py-12 md:py-14' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700'"
                >
                    <template x-if="plan.badge">
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 px-4 py-1 bg-primary text-white text-xs font-black uppercase tracking-widest rounded-full shadow-lg shadow-primary/30" x-text="plan.badge"></div>
                    </template>

                    <div class="mb-8">
                        <h3 class="text-xl font-black uppercase tracking-tight mb-2" x-text="plan.name"></h3>
                        <p class="text-sm opacity-60 leading-relaxed h-10 overflow-hidden" x-text="plan.description"></p>
                    </div>

                    <div class="mb-8 flex items-baseline gap-1">
                        <span class="text-5xl font-black tracking-tighter">
                            <span x-show="billing === 'monthly'" x-text="(plan.currency || '$') + (plan.price_monthly || '0')"></span>
                            <span x-show="billing === 'annual'" x-text="(plan.currency || '$') + (plan.price_annual || '0')"></span>
                        </span>
                        <span class="text-lg font-bold opacity-40">/{{ __('mo') }}</span>
                    </div>

                    <ul class="mb-10 space-y-4 flex-1">
                        <template x-for="feature in (plan.features || [])">
                            <li class="flex items-start text-sm">
                                <flux:icon.check-circle variant="solid" class="mr-3 h-5 w-5" x-bind:class="feature.included !== false ? 'text-primary' : 'text-zinc-300 dark:text-zinc-600 opacity-50'" />
                                <span x-bind:class="feature.included !== false ? 'font-medium' : 'opacity-40 line-through'" x-text="feature.text"></span>
                            </li>
                        </template>
                    </ul>

                    <div class="w-full flex items-center justify-center px-8 py-4 border border-transparent text-base font-black rounded-2xl transition"
                         :class="plan.is_featured ? 'bg-primary text-white' : 'bg-zinc-100 dark:bg-zinc-700 text-zinc-900 dark:text-white'"
                         x-text="plan.cta_text || '{{ __('Get Started') }}'"
                    ></div>
                </div>
            </template>
        </div>
    </div>
</div>