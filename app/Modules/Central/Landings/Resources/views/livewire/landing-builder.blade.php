<div 
    x-data="{
        blocks: @entangle('blocks'),
        theme: @entangle('theme'),
        selectedBlockId: null,
        isDirty: false,
        viewMode: 'desktop',
        
        get selectedBlock() {
            return this.blocks.find(b => b.id === this.selectedBlockId);
        },

        addBlock(type) {
            const id = 'block_' + Math.random().toString(36).substr(2, 9);
            const newBlock = {
                id: id,
                type: type,
                version: 1,
                variant: 'centered',
                order: this.blocks.length,
                visible: true,
                config: { headline: 'New ' + type + ' block' },
                styles: { padding: 'lg', background: 'white' }
            };
            this.blocks.push(newBlock);
            this.selectedBlockId = id;
            this.isDirty = true;
        },

        removeBlock(id) {
            this.blocks = this.blocks.filter(b => b.id !== id);
            if (this.selectedBlockId === id) this.selectedBlockId = null;
            this.isDirty = true;
        },

        moveBlock(id, direction) {
            const index = this.blocks.findIndex(b => b.id === id);
            if (direction === 'up' && index > 0) {
                [this.blocks[index-1], this.blocks[index]] = [this.blocks[index], this.blocks[index-1]];
            } else if (direction === 'down' && index < this.blocks.length - 1) {
                [this.blocks[index+1], this.blocks[index]] = [this.blocks[index], this.blocks[index+1]];
            }
            this.blocks.forEach((b, i) => b.order = i);
            this.isDirty = true;
        },

        save() {
            if (!this.isDirty) return;
            $wire.save(this.blocks, this.theme).then(() => {
                this.isDirty = false;
            });
        },

        publish() {
            $wire.publish().then(() => {
                this.isDirty = false;
                alert('{{ __("Landing published successfully!") }}');
            });
        }
    }"
    class="flex flex-col h-screen overflow-hidden bg-zinc-100"
    x-on:landing-saved.window="isDirty = false"
>
    <!-- Top Toolbar -->
    <header class="flex items-center justify-between px-6 py-3 bg-white border-b border-zinc-200">
        <div class="flex items-center gap-4">
            <flux:heading size="lg">{{ __('Landing Builder') }}</flux:heading>
            <flux:badge color="zinc" variant="outline">{{ $landing->title }}</flux:badge>
            <span x-show="isDirty" class="text-xs text-orange-500 font-medium">● {{ __('Unsaved changes') }}</span>
        </div>

        <div class="flex items-center gap-2">
            <div class="flex items-center border border-zinc-200 rounded-lg overflow-hidden">
                <flux:button x-on:click="viewMode = 'desktop'" x-bind:variant="viewMode === 'desktop' ? 'primary' : 'ghost'" icon="computer-desktop" size="sm" square class="rounded-none" />
                <flux:button x-on:click="viewMode = 'tablet'" x-bind:variant="viewMode === 'tablet' ? 'primary' : 'ghost'" icon="device-tablet" size="sm" square class="rounded-none" />
                <flux:button x-on:click="viewMode = 'mobile'" x-bind:variant="viewMode === 'mobile' ? 'primary' : 'ghost'" icon="device-phone-mobile" size="sm" square class="rounded-none" />
            </div>

            <div class="mx-2 h-6 w-px bg-zinc-200"></div>

            <flux:button x-on:click="save" variant="outline" size="sm" icon="cloud-arrow-up" x-bind:disabled="!isDirty">
                {{ __('Save Draft') }}
            </flux:button>
            
            <flux:button x-on:click="publish" variant="primary" size="sm" icon="arrow-up-tray">
                {{ __('Publish') }}
            </flux:button>
            
            <flux:button variant="outline" size="sm" icon="eye">
                {{ __('Preview') }}
            </flux:button>
        </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- Left Sidebar - Block Library -->
        <aside class="w-64 bg-white border-e border-zinc-200 overflow-y-auto p-4">
            <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">{{ __('Add Blocks') }}</flux:heading>
            
            <div class="grid gap-2">
                <flux:button x-on:click="addBlock('hero')" variant="outline" class="justify-start" icon="presentation-chart-line">{{ __('Hero') }}</flux:button>
                <flux:button x-on:click="addBlock('cta')" variant="outline" class="justify-start" icon="megaphone">{{ __('Call to Action') }}</flux:button>
                <flux:button x-on:click="addBlock('footer')" variant="outline" class="justify-start" icon="window">{{ __('Footer') }}</flux:button>
            </div>
            
            <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">{{ __('Conversion') }}</flux:heading>
            <div class="grid gap-2">
                <flux:button x-on:click="addBlock('features')" variant="outline" class="justify-start" icon="list-bullet">{{ __('Features') }}</flux:button>
                <flux:button x-on:click="addBlock('pricing')" variant="outline" class="justify-start" icon="credit-card">{{ __('Pricing') }}</flux:button>
                <flux:button x-on:click="addBlock('testimonials')" variant="outline" class="justify-start" icon="chat-bubble-left-right">{{ __('Testimonials') }}</flux:button>
            </div>
            
            <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">{{ __('Support & Corporate') }}</flux:heading>
            <div class="grid gap-2">
                <flux:button x-on:click="addBlock('faq')" variant="outline" class="justify-start" icon="question-mark-circle">{{ __('FAQ') }}</flux:button>
                <flux:button x-on:click="addBlock('contact')" variant="outline" class="justify-start" icon="envelope">{{ __('Contact') }}</flux:button>
                <flux:button x-on:click="addBlock('about')" variant="outline" class="justify-start" icon="information-circle">{{ __('About') }}</flux:button>
            </div>
            
            <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">{{ __('Advanced') }}</flux:heading>
            <div class="grid gap-2">
                <flux:button x-on:click="addBlock('statistics')" variant="outline" class="justify-start" icon="chart-bar">{{ __('Statistics') }}</flux:button>
                <flux:button x-on:click="addBlock('gallery')" variant="outline" class="justify-start" icon="photo">{{ __('Gallery') }}</flux:button>
                <flux:button x-on:click="addBlock('lead-form')" variant="outline" class="justify-start" icon="user-plus">{{ __('Lead Form') }}</flux:button>
                <flux:button x-on:click="addBlock('trust-signals')" variant="outline" class="justify-start" icon="shield-check">{{ __('Trust Signals') }}</flux:button>
            </div>
            
            <div class="my-6 h-px w-full bg-zinc-200"></div>
            
            <flux:heading size="sm" class="mb-4 uppercase tracking-wider text-zinc-500">{{ __('Page Structure') }}</flux:heading>
            <div class="grid gap-2">
                <template x-for="(block, index) in blocks" :key="block.id">
                    <div 
                        x-on:click="selectedBlockId = block.id"
                        class="flex flex-col p-2 rounded-md cursor-pointer border transition relative"
                        :class="selectedBlockId === block.id ? 'bg-zinc-100 border-zinc-300' : 'bg-white border-transparent hover:bg-zinc-50'"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-bold capitalize" x-text="block.type"></span>
                            <div class="flex gap-1">
                                <button x-on:click.stop="moveBlock(block.id, 'up')" class="p-1 hover:bg-zinc-200 rounded text-zinc-500">
                                    <flux:icon.chevron-up size="xs" />
                                </button>
                                <button x-on:click.stop="moveBlock(block.id, 'down')" class="p-1 hover:bg-zinc-200 rounded text-zinc-500">
                                    <flux:icon.chevron-down size="xs" />
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 mt-1">
                            <flux:icon.hashtag size="xs" class="text-zinc-400" />
                            <span class="text-[10px] font-mono text-zinc-400 select-all" x-text="block.id"></span>
                        </div>
                        <button 
                            x-show="selectedBlockId === block.id" 
                            x-on:click.stop="removeBlock(block.id)" 
                            class="absolute -right-2 -top-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-1 shadow-sm transition"
                            title="{{ __('Delete Block') }}"
                        >
                            <flux:icon.x-mark size="xs" stroke-width="3" />
                        </button>
                    </div>
                </template>
            </div>
        </aside>

        <!-- Main Canvas -->
        <main class="flex-1 overflow-y-auto p-8 flex justify-center items-start">
            <div 
                class="bg-white shadow-2xl transition-all duration-300 border border-zinc-200"
                :class="{
                    'w-full max-w-5xl': viewMode === 'desktop',
                    'w-[768px]': viewMode === 'tablet',
                    'w-[375px]': viewMode === 'mobile'
                }"
            >
                <div class="bg-zinc-50 p-4 border-b border-zinc-200 flex items-center justify-between">
                    <div class="flex gap-1.5">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                    </div>
                    <div class="text-xs text-zinc-400 font-mono" x-text="'localhost/' + '{{ $landing->slug }}'"></div>
                    <div class="w-6"></div>
                </div>

                <!-- Canvas Content Render -->
                <div class="min-h-[600px] overflow-x-hidden">
                    <template x-if="blocks.length === 0">
                        <div class="flex flex-col items-center justify-center h-[400px] text-zinc-400 italic">
                            <flux:icon.plus-circle class="mb-2" />
                            {{ __('Click on a block to start building') }}
                        </div>
                    </template>

                    <template x-for="(block, index) in blocks" :key="block.id">
                        <div 
                            x-on:click="selectedBlockId = block.id"
                            class="relative group cursor-pointer border-2 transition"
                            :class="[
                                selectedBlockId === block.id ? 'border-primary' : 'border-transparent hover:border-zinc-200',
                                block.styles.background === 'primary' ? 'bg-primary text-white' : '',
                                block.styles.background === 'secondary' ? 'bg-secondary text-white' : '',
                                block.styles.background === 'dark' ? 'bg-zinc-900 text-white' : '',
                                block.styles.background === 'surface' ? 'bg-zinc-50' : 'bg-white'
                            ]"
                        >
                            <!-- Visual Block Representation (Alpine-based) -->
                            <div class="pointer-events-none">
                                @include('landings::livewire.previews.hero')
                                @include('landings::livewire.previews.cta')
                                @include('landings::livewire.previews.features')
                                @include('landings::livewire.previews.pricing')
                                @include('landings::livewire.previews.faq')
                                @include('landings::livewire.previews.trust-signals')
                                @include('landings::livewire.previews.about')
                                @include('landings::livewire.previews.statistics')
                                @include('landings::livewire.previews.gallery')
                                @include('landings::livewire.previews.lead-form')
                                @include('landings::livewire.previews.contact')
                                @include('landings::livewire.previews.footer')
                                @include('landings::livewire.previews.testimonials')

                                <div x-show="!['hero', 'cta', 'features', 'pricing', 'faq', 'contact', 'statistics', 'gallery', 'trust-signals', 'about', 'lead-form', 'footer', 'testimonials'].includes(block.type)" class="py-12 border-2 border-dashed border-zinc-200 rounded-2xl">
                                    <flux:heading size="lg" x-text="block.config.headline || block.type"></flux:heading>
                                    <flux:text x-text="'Preview for ' + block.type + ' coming soon'"></flux:text>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div 
                                x-show="selectedBlockId === block.id"
                                class="absolute -right-12 top-0 flex flex-col gap-1"
                            >
                                <flux:button x-on:click.stop="removeBlock(block.id)" icon="trash" variant="danger" size="xs" square />
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </main>

        <!-- Right Sidebar - Properties -->
        <aside class="w-80 bg-white border-s border-zinc-200 overflow-y-auto p-4">
            <template x-if="!selectedBlockId">
                <div class="text-center py-20 text-zinc-400">
                    <flux:icon.adjustments-horizontal class="mx-auto mb-2" />
                    <p>{{ __('Select a block to edit its properties') }}</p>
                </div>
            </template>

            <template x-if="selectedBlock">
                <div class="space-y-6">
                    <div>
                        <flux:heading size="lg" class="capitalize" x-text="selectedBlock.type + ' settings'"></flux:heading>
                        <flux:text size="sm">{{ __('Configure the content and style of this block.') }}</flux:text>
                    </div>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>{{ __('Variant') }}</flux:label>
                            <flux:select x-model="selectedBlock.variant" x-on:change="isDirty = true">
                                <template x-if="selectedBlock.type === 'hero'">
                                    <optgroup label="Hero">
                                        <option value="centered">{{ __('Centered') }}</option>
                                        <option value="split">{{ __('Split (Text | Image)') }}</option>
                                        <option value="image-left">{{ __('Split (Image | Text)') }}</option>
                                        <option value="bg-image">{{ __('Background Image') }}</option>
                                        <option value="fullscreen">{{ __('Fullscreen') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'features'">
                                    <optgroup label="Features">
                                        <option value="3-columns">{{ __('3 Columns') }}</option>
                                        <option value="4-columns">{{ __('4 Columns') }}</option>
                                        <option value="alternating-rows">{{ __('Alternating Rows') }}</option>
                                        <option value="cards">{{ __('Cards') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'pricing'">
                                    <optgroup label="Pricing">
                                        <option value="cards">{{ __('Cards') }}</option>
                                        <option value="featured-plan">{{ __('Featured Plan') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'testimonials'">
                                    <optgroup label="Testimonials">
                                        <option value="grid">{{ __('Grid') }}</option>
                                        <option value="carousel">{{ __('Carousel') }}</option>
                                        <option value="single-featured">{{ __('Single Featured') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'cta'">
                                    <optgroup label="Call to Action">
                                        <option value="centered">{{ __('Centered') }}</option>
                                        <option value="banner">{{ __('Banner (Horizontal)') }}</option>
                                        <option value="split">{{ __('Split (With Form)') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'footer'">
                                    <optgroup label="Footer">
                                        <option value="simple">{{ __('Simple') }}</option>
                                        <option value="multi-column">{{ __('Multi Column') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'faq'">
                                    <optgroup label="FAQ">
                                        <option value="accordion">{{ __('Accordion') }}</option>
                                        <option value="two-columns">{{ __('Two Columns') }}</option>
                                        <option value="simple-list">{{ __('Simple List') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'contact'">
                                    <optgroup label="Contact">
                                        <option value="form-info">{{ __('Form + Info') }}</option>
                                        <option value="compact">{{ __('Compact Form') }}</option>
                                        <option value="map-included">{{ __('Form + Map') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'about'">
                                    <optgroup label="About">
                                        <option value="image-right">{{ __('Image Right') }}</option>
                                        <option value="image-left">{{ __('Image Left') }}</option>
                                        <option value="story">{{ __('Story (Text only)') }}</option>
                                        <option value="team-intro">{{ __('Team Intro') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'statistics'">
                                    <optgroup label="Statistics">
                                        <option value="horizontal">{{ __('Horizontal') }}</option>
                                        <option value="grid">{{ __('Grid') }}</option>
                                        <option value="highlighted">{{ __('Highlighted') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'gallery'">
                                    <optgroup label="Gallery">
                                        <option value="grid">{{ __('Grid') }}</option>
                                        <option value="masonry">{{ __('Masonry') }}</option>
                                        <option value="carousel">{{ __('Carousel') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'lead-form'">
                                    <optgroup label="Lead Form">
                                        <option value="inline">{{ __('Inline') }}</option>
                                        <option value="multi-field">{{ __('Multi-field') }}</option>
                                        <option value="newsletter">{{ __('Newsletter') }}</option>
                                    </optgroup>
                                </template>
                                <template x-if="selectedBlock.type === 'trust-signals'">
                                    <optgroup label="Trust Signals">
                                        <option value="logo-strip">{{ __('Logo Strip') }}</option>
                                        <option value="certifications">{{ __('Certifications') }}</option>
                                    </optgroup>
                                </template>
                            </flux:select>
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Headline / Section Title') }}</flux:label>
                            <flux:input x-model="selectedBlock.config.headline" x-on:input="isDirty = true" />
                        </flux:field>

                        <template x-if="['hero', 'cta', 'features', 'pricing', 'testimonials', 'faq', 'contact', 'about', 'statistics', 'gallery', 'lead-form', 'trust-signals'].includes(selectedBlock.type)">
                            <flux:field>
                                <flux:label>{{ __('Subtitle / Section Description') }}</flux:label>
                                <flux:textarea x-model="selectedBlock.config.subtitle" x-on:input="isDirty = true" rows="3" />
                            </flux:field>
                        </template>

                        <!-- Statistics Editor -->
                        <template x-if="selectedBlock.type === 'statistics'">
                            <div class="space-y-6">
                                <template x-if="selectedBlock.variant === 'grid'">
                                    <flux:field>
                                        <flux:label>{{ __('Grid Columns') }}</flux:label>
                                        <flux:select x-model="selectedBlock.config.columns_count" x-on:change="isDirty = true">
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="6">6</option>
                                        </flux:select>
                                    </flux:field>
                                </template>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Metrics') }}</flux:heading>
                                    <template x-for="(stat, index) in selectedBlock.config.stats" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="stat.value" placeholder="Value (e.g. 99)" x-on:input="isDirty = true" size="sm" class="font-bold w-1/2" />
                                                <button x-on:click="selectedBlock.config.stats.splice(index, 1); isDirty = true" class="text-red-500 hover:text-red-700">
                                                    <flux:icon.trash size="xs" />
                                                </button>
                                            </div>
                                            <flux:input x-model="stat.label" placeholder="Label (e.g. Happy Users)" x-on:input="isDirty = true" size="sm" />
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="stat.prefix" placeholder="Prefix" x-on:input="isDirty = true" size="xs" />
                                                <flux:input x-model="stat.suffix" placeholder="Suffix" x-on:input="isDirty = true" size="xs" />
                                            </div>
                                            <template x-if="selectedBlock.variant === 'grid'">
                                                <flux:input x-model="stat.icon" placeholder="Icon Name" x-on:input="isDirty = true" size="xs" />
                                            </template>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.stats = selectedBlock.config.stats || []; selectedBlock.config.stats.push({value: '100', label: 'Metric', prefix: '', suffix: '+', icon: 'chart-bar'}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Metric') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <!-- Gallery Editor -->
                        <template x-if="selectedBlock.type === 'gallery'">
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <template x-if="['grid', 'masonry'].includes(selectedBlock.variant)">
                                        <flux:field>
                                            <flux:label>{{ __('Columns') }}</flux:label>
                                            <flux:select x-model="selectedBlock.config.columns_count" x-on:change="isDirty = true">
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </flux:select>
                                        </flux:field>
                                    </template>
                                    <template x-if="selectedBlock.variant === 'carousel'">
                                        <div class="flex items-center justify-between col-span-2">
                                            <flux:label>{{ __('Autoplay') }}</flux:label>
                                            <flux:switch x-model="selectedBlock.config.autoplay" x-on:change="isDirty = true" />
                                        </div>
                                    </template>
                                    <div class="flex items-center justify-between col-span-2 pt-1 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:label size="sm">{{ __('Show Captions on Hover') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_captions" x-on:change="isDirty = true" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Images') }}</flux:heading>
                                    <template x-for="(img, index) in selectedBlock.config.images" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="img.url" placeholder="Image URL" x-on:input="isDirty = true" size="sm" class="font-bold w-3/4" />
                                                <button x-on:click="selectedBlock.config.images.splice(index, 1); isDirty = true" class="text-red-500 hover:text-red-700">
                                                    <flux:icon.trash size="xs" />
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="img.alt" placeholder="Alt Text" x-on:input="isDirty = true" size="xs" />
                                                <flux:input x-model="img.caption" placeholder="Caption (optional)" x-on:input="isDirty = true" size="xs" />
                                            </div>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.images = selectedBlock.config.images || []; selectedBlock.config.images.push({url: '', alt: '', caption: 'New Image'}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Image') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <!-- Trust Signals Editor -->
                        <template x-if="selectedBlock.type === 'trust-signals'">
                            <div class="space-y-6">
                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:label size="sm">{{ __('Apply Grayscale Filter') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.grayscale" x-on:change="isDirty = true" />
                                    </div>
                                    <div class="flex items-center justify-between pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:label size="sm">{{ __('Show Original Colors on Hover') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_hover_color" x-on:change="isDirty = true" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Logos / Badges') }}</flux:heading>
                                    <template x-for="(item, index) in selectedBlock.config.items" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="item.logo_url" placeholder="Logo Image URL" x-on:input="isDirty = true" size="sm" class="font-bold w-3/4" />
                                                <button x-on:click="selectedBlock.config.items.splice(index, 1); isDirty = true" class="text-red-500 hover:text-red-700">
                                                    <flux:icon.trash size="xs" />
                                                </button>
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="item.alt" placeholder="Company / Badge Name" x-on:input="isDirty = true" size="xs" />
                                                <flux:input x-model="item.url" placeholder="Link URL (optional)" x-on:input="isDirty = true" size="xs" />
                                            </div>
                                            <template x-if="selectedBlock.variant === 'certifications'">
                                                <flux:textarea x-model="item.description" placeholder="Short description..." x-on:input="isDirty = true" rows="2" size="xs" />
                                            </template>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.items = selectedBlock.config.items || []; selectedBlock.config.items.push({logo_url: '', alt: 'Partner', description: '', url: ''}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Logo') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <!-- CTA Editor -->
                        <template x-if="selectedBlock.type === 'cta'">
                            <div class="space-y-6">
                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <flux:heading size="sm">{{ __('Primary Button') }}</flux:heading>
                                    <flux:input x-model="selectedBlock.config.button_primary_text" placeholder="Label" x-on:input="isDirty = true" size="sm" />
                                    <flux:input x-model="selectedBlock.config.button_primary_url" placeholder="URL" x-on:input="isDirty = true" size="sm" />
                                </div>

                                <template x-if="selectedBlock.variant === 'centered'">
                                    <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        <div class="flex items-center justify-between">
                                            <flux:heading size="sm">{{ __('Secondary Button') }}</flux:heading>
                                            <flux:switch x-model="selectedBlock.config.show_secondary_button" x-on:change="isDirty = true" />
                                        </div>
                                        <template x-if="selectedBlock.config.show_secondary_button">
                                            <div class="space-y-4 pt-2">
                                                <flux:input x-model="selectedBlock.config.button_secondary_text" placeholder="Label" x-on:input="isDirty = true" size="sm" />
                                                <flux:input x-model="selectedBlock.config.button_secondary_url" placeholder="URL" x-on:input="isDirty = true" size="sm" />
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Guarantee Label') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_guarantee" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_guarantee">
                                        <div class="pt-2">
                                            <flux:input x-model="selectedBlock.config.guarantee_text" placeholder="e.g. No credit card required" x-on:input="isDirty = true" size="sm" />
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- Footer Editor -->
                        <template x-if="selectedBlock.type === 'footer'">
                            <div class="space-y-6">
                                <flux:field>
                                    <flux:label>{{ __('Description / Tagline') }}</flux:label>
                                    <flux:textarea x-model="selectedBlock.config.description" placeholder="Short business description..." x-on:input="isDirty = true" rows="3" size="sm" />
                                </flux:field>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Navigation Columns') }}</flux:heading>
                                    <template x-for="(col, index) in selectedBlock.config.columns" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="col.title" placeholder="Column Title" x-on:input="isDirty = true" size="sm" class="font-bold" />
                                                <button x-on:click="selectedBlock.config.columns.splice(index, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>
                                            <div class="space-y-2">
                                                <template x-for="(link, lIndex) in col.links" :key="lIndex">
                                                    <div class="flex gap-2">
                                                        <flux:input x-model="link.label" placeholder="Label" size="xs" x-on:input="isDirty = true" />
                                                        <flux:input x-model="link.url" placeholder="URL" size="xs" x-on:input="isDirty = true" />
                                                        <button x-on:click="col.links.splice(lIndex, 1); isDirty = true" class="text-zinc-400"><flux:icon.x-mark size="xs" /></button>
                                                    </div>
                                                </template>
                                                <flux:button x-on:click="col.links = col.links || []; col.links.push({label: 'Link', url: '#'}); isDirty = true" variant="ghost" size="xs" class="w-full">+ Add Link</flux:button>
                                            </div>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.columns = selectedBlock.config.columns || []; selectedBlock.config.columns.push({title: 'Category', links: []}); isDirty = true" variant="outline" size="xs" class="w-full">+ Add Column</flux:button>
                                </div>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Social Links') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_social" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_social">
                                        <div class="space-y-2 pt-2">
                                            <template x-for="(social, sIndex) in selectedBlock.config.social_links" :key="sIndex">
                                                <div class="flex gap-2">
                                                    <select x-model="social.platform" class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 rounded p-1" x-on:change="isDirty = true">
                                                        <option value="twitter">X (Twitter)</option>
                                                        <option value="facebook">Facebook</option>
                                                        <option value="instagram">Instagram</option>
                                                        <option value="linkedin">LinkedIn</option>
                                                        <option value="github">GitHub</option>
                                                        <option value="youtube">YouTube</option>
                                                    </select>
                                                    <flux:input x-model="social.url" placeholder="https://..." size="xs" x-on:input="isDirty = true" />
                                                    <button x-on:click="selectedBlock.config.social_links.splice(sIndex, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                                </div>
                                            </template>
                                            <flux:button x-on:click="selectedBlock.config.social_links = selectedBlock.config.social_links || []; selectedBlock.config.social_links.push({platform: 'twitter', url: ''}); isDirty = true" variant="outline" size="xs" class="w-full">+ Add Social</flux:button>
                                        </div>
                                    </template>
                                </div>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Newsletter Section') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_newsletter" x-on:change="isDirty = true" />
                                    </div>
                                </div>

                                <flux:field>
                                    <flux:label>{{ __('Copyright Text') }}</flux:label>
                                    <flux:input x-model="selectedBlock.config.copyright_text" x-on:input="isDirty = true" size="sm" />
                                </flux:field>
                            </div>
                        </template>

                        <!-- Hero Editor -->
                        <template x-if="selectedBlock.type === 'hero'">
                            <div class="space-y-6">
                                <flux:field>
                                    <flux:label>{{ __('Badge Text') }}</flux:label>
                                    <flux:input x-model="selectedBlock.config.badge_text" placeholder="e.g. NEW FEATURE" x-on:input="isDirty = true" />
                                </flux:field>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <flux:heading size="sm">{{ __('Primary Button') }}</flux:heading>
                                    <flux:input x-model="selectedBlock.config.button_primary_text" placeholder="Label" x-on:input="isDirty = true" size="sm" />
                                    <flux:input x-model="selectedBlock.config.button_primary_url" placeholder="URL" x-on:input="isDirty = true" size="sm" />
                                </div>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Secondary Button') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_secondary_button" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_secondary_button">
                                        <div class="space-y-4 pt-2">
                                            <flux:input x-model="selectedBlock.config.button_secondary_text" placeholder="Label" x-on:input="isDirty = true" size="sm" />
                                            <flux:input x-model="selectedBlock.config.button_secondary_url" placeholder="URL" x-on:input="isDirty = true" size="sm" />
                                        </div>
                                    </template>
                                </div>

                                <template x-if="['split', 'image-left', 'bg-image'].includes(selectedBlock.variant)">
                                    <flux:field>
                                        <flux:label>{{ __('Image URL') }}</flux:label>
                                        <flux:input x-model="selectedBlock.config.image_url" placeholder="https://..." x-on:input="isDirty = true" />
                                    </flux:field>
                                </template>

                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <flux:label>{{ __('Show Stats Strip') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_stats" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_stats">
                                        <div class="space-y-3 pt-2">
                                            <template x-for="(stat, index) in selectedBlock.config.stats" :key="index">
                                                <div class="flex gap-2 items-center">
                                                    <flux:input x-model="stat.value" placeholder="99k" size="sm" class="w-20" x-on:input="isDirty = true" />
                                                    <flux:input x-model="stat.label" placeholder="Users" size="sm" class="flex-1" x-on:input="isDirty = true" />
                                                    <button x-on:click="selectedBlock.config.stats.splice(index, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                                </div>
                                            </template>
                                            <flux:button x-on:click="selectedBlock.config.stats = selectedBlock.config.stats || []; selectedBlock.config.stats.push({value: '10', label: 'New'}); isDirty = true" variant="outline" size="xs" class="w-full">+ Add Stat</flux:button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- FAQ Editor -->
                        <template x-if="selectedBlock.type === 'faq'">
                            <div class="space-y-6">
                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:label>{{ __('Open First Item') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.open_first" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.variant === 'accordion'">
                                        <flux:field>
                                            <flux:label>{{ __('Icon Type') }}</flux:label>
                                            <flux:select x-model="selectedBlock.config.icon_type" x-on:change="isDirty = true">
                                                <option value="chevron">{{ __('Chevron') }}</option>
                                                <option value="plus">{{ __('Plus/Minus') }}</option>
                                            </flux:select>
                                        </flux:field>
                                    </template>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage FAQ Items') }}</flux:heading>
                                    <template x-for="(item, index) in selectedBlock.config.items" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                            <div class="flex justify-between items-center">
                                                <flux:icon icon="question-mark-circle" size="xs" class="text-zinc-400" />
                                                <button x-on:click="selectedBlock.config.items.splice(index, 1); isDirty = true" class="text-red-500 hover:text-red-700">
                                                    <flux:icon.trash size="xs" />
                                                </button>
                                            </div>
                                            <flux:input x-model="item.question" placeholder="Question" x-on:input="isDirty = true" size="sm" class="font-bold" />
                                            <flux:textarea x-model="item.answer" placeholder="Answer" x-on:input="isDirty = true" rows="3" size="sm" />
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.items = selectedBlock.config.items || []; selectedBlock.config.items.push({question: 'New Question', answer: ''}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add FAQ Item') }}
                                    </flux:button>
                                </div>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Contact CTA') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_contact_cta" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_contact_cta">
                                        <div class="space-y-3 pt-2">
                                            <flux:input x-model="selectedBlock.config.contact_cta_text" placeholder="Button Label" x-on:input="isDirty = true" size="sm" />
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="selectedBlock.config.contact_cta_url" placeholder="URL or #anchor" x-on:input="isDirty = true" size="xs" />
                                                <select x-model="selectedBlock.config.contact_cta_target" class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 rounded p-1" x-on:change="isDirty = true">
                                                    <option value="_self">Same Tab</option>
                                                    <option value="_blank">New Tab</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <!-- About Editor -->
                        <template x-if="selectedBlock.type === 'about'">
                            <div class="space-y-6">
                                <flux:field>
                                    <flux:label>{{ __('Description / Story') }}</flux:label>
                                    <flux:textarea x-model="selectedBlock.config.description" x-on:input="isDirty = true" rows="5" size="sm" />
                                </flux:field>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:heading size="sm">{{ __('Call to Action') }}</flux:heading>
                                        <flux:switch x-model="selectedBlock.config.show_cta" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_cta">
                                        <div class="space-y-3 pt-2">
                                            <flux:input x-model="selectedBlock.config.cta_text" placeholder="Button Label" x-on:input="isDirty = true" size="sm" />
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="selectedBlock.config.cta_url" placeholder="URL or #anchor" x-on:input="isDirty = true" size="xs" />
                                                <select x-model="selectedBlock.config.cta_target" class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 rounded p-1" x-on:change="isDirty = true">
                                                    <option value="_self">Same Tab</option>
                                                    <option value="_blank">New Tab</option>
                                                </select>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Key Metrics') }}</flux:heading>
                                    <template x-for="(metric, mIndex) in selectedBlock.config.metrics" :key="mIndex">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="metric.value" placeholder="Value (e.g. 10+)" x-on:input="isDirty = true" size="xs" class="font-bold w-1/2" />
                                                <button x-on:click="selectedBlock.config.metrics.splice(mIndex, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>
                                            <flux:input x-model="metric.label" placeholder="Label (e.g. Years)" x-on:input="isDirty = true" size="xs" />
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.metrics = selectedBlock.config.metrics || []; selectedBlock.config.metrics.push({value: '10', label: 'New Metric'}); isDirty = true" variant="outline" size="xs" class="w-full">+ Add Metric</flux:button>
                                </div>
                                
                                <template x-if="selectedBlock.variant === 'team-intro'">
                                    <div class="space-y-4">
                                        <flux:heading size="sm">{{ __('Manage Team') }}</flux:heading>
                                        <template x-for="(member, index) in selectedBlock.config.team_members" :key="index">
                                            <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-2">
                                                <div class="flex justify-between items-center">
                                                    <flux:input x-model="member.name" placeholder="Full Name" x-on:input="isDirty = true" size="sm" class="font-bold" />
                                                    <button x-on:click="selectedBlock.config.team_members.splice(index, 1); isDirty = true" class="text-red-500 hover:text-red-700">
                                                        <flux:icon.trash size="xs" />
                                                    </button>
                                                </div>
                                                <flux:input x-model="member.role" placeholder="Role / Position" x-on:input="isDirty = true" size="xs" />
                                                <flux:input x-model="member.avatar_url" placeholder="Avatar URL" x-on:input="isDirty = true" size="xs" />
                                                <flux:textarea x-model="member.bio" placeholder="Short bio..." x-on:input="isDirty = true" rows="2" size="xs" />
                                            </div>
                                        </template>
                                        <flux:button x-on:click="selectedBlock.config.team_members = selectedBlock.config.team_members || []; selectedBlock.config.team_members.push({name: 'New Member', role: 'Team Lead', avatar_url: '', bio: ''}); isDirty = true" variant="outline" size="xs" class="w-full">
                                            {{ __('+ Add Member') }}
                                        </flux:button>
                                    </div>
                                </template>

                                <template x-if="['image-right', 'image-left'].includes(selectedBlock.variant)">
                                    <flux:field>
                                        <flux:label>{{ __('Image URL') }}</flux:label>
                                        <flux:input x-model="selectedBlock.config.image_url" placeholder="https://..." x-on:input="isDirty = true" />
                                    </flux:field>
                                </template>
                            </div>
                        </template>

                        <!-- Contact Editor -->
                        <template x-if="selectedBlock.type === 'contact'">
                            <div class="space-y-6">
                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Form Fields') }}</flux:heading>
                                    <template x-for="(field, fIndex) in selectedBlock.config.fields" :key="fIndex">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <select x-model="field.type" class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 rounded p-1" x-on:change="isDirty = true">
                                                    <option value="text">Text</option>
                                                    <option value="email">Email</option>
                                                    <option value="tel">Phone</option>
                                                    <option value="textarea">Message</option>
                                                </select>
                                                <button x-on:click="selectedBlock.config.fields.splice(fIndex, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>
                                            <flux:input x-model="field.label" placeholder="Label" size="xs" x-on:input="isDirty = true" />
                                            <flux:input x-model="field.placeholder" placeholder="Placeholder" size="xs" x-on:input="isDirty = true" />
                                            <div class="flex items-center justify-between">
                                                <flux:label size="sm">{{ __('Required') }}</flux:label>
                                                <flux:switch x-model="field.required" x-on:change="isDirty = true" />
                                            </div>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.fields = selectedBlock.config.fields || []; selectedBlock.config.fields.push({type: 'text', label: 'New Field', name: 'field_' + Date.now(), required: false}); isDirty = true" variant="outline" size="xs" class="w-full">+ Add Field</flux:button>
                                </div>

                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <flux:heading size="sm">{{ __('Submission Settings') }}</flux:heading>
                                    <flux:input x-model="selectedBlock.config.submit_text" placeholder="Submit Button Label" x-on:input="isDirty = true" size="sm" />
                                    <flux:textarea x-model="selectedBlock.config.success_message" placeholder="Success Message" x-on:input="isDirty = true" rows="2" size="sm" />
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Business Information') }}</flux:heading>
                                    
                                    <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <flux:label size="sm">{{ __('Show Email') }}</flux:label>
                                            <flux:switch x-model="selectedBlock.config.show_email" x-on:change="isDirty = true" />
                                        </div>
                                        <template x-if="selectedBlock.config.show_email">
                                            <flux:input x-model="selectedBlock.config.email" placeholder="Email Address" size="xs" x-on:input="isDirty = true" />
                                        </template>
                                    </div>

                                    <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <flux:label size="sm">{{ __('Show Phone') }}</flux:label>
                                            <flux:switch x-model="selectedBlock.config.show_phone" x-on:change="isDirty = true" />
                                        </div>
                                        <template x-if="selectedBlock.config.show_phone">
                                            <flux:input x-model="selectedBlock.config.phone" placeholder="Phone Number" size="xs" x-on:input="isDirty = true" />
                                        </template>
                                    </div>

                                    <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                        <div class="flex items-center justify-between">
                                            <flux:label size="sm">{{ __('Show Address') }}</flux:label>
                                            <flux:switch x-model="selectedBlock.config.show_address" x-on:change="isDirty = true" />
                                        </div>
                                        <template x-if="selectedBlock.config.show_address">
                                            <flux:textarea x-model="selectedBlock.config.address" placeholder="Physical Address" size="xs" x-on:input="isDirty = true" rows="2" />
                                        </template>
                                    </div>
                                </div>

                                <template x-if="selectedBlock.variant === 'map-included'">
                                    <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        <flux:heading size="sm">{{ __('Map Settings') }}</flux:heading>
                                        <flux:input x-model="selectedBlock.config.map_embed_url" placeholder="Google Maps Embed URL" x-on:input="isDirty = true" size="sm" />
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- Features Editor -->
                        <template x-if="selectedBlock.type === 'features'">
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>{{ __('Grid Columns') }}</flux:label>
                                        <flux:select x-model="selectedBlock.config.columns_count" x-on:change="isDirty = true">
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </flux:select>
                                    </flux:field>
                                    <div class="flex items-center justify-between pt-6">
                                        <flux:label>{{ __('Show Icons') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_icons" x-on:change="isDirty = true" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Features') }}</flux:heading>
                                    <template x-for="(item, index) in selectedBlock.config.features" :key="index">
                                        <div class="p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <div class="flex gap-2 items-center">
                                                    <flux:icon icon="star" size="xs" class="text-primary" />
                                                    <flux:input x-model="item.title" placeholder="Feature Title" x-on:input="isDirty = true" size="sm" class="font-bold" />
                                                </div>
                                                <button x-on:click="selectedBlock.config.features.splice(index, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>
                                            <flux:textarea x-model="item.description" placeholder="Description" x-on:input="isDirty = true" rows="2" size="sm" />
                                            
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="item.icon" placeholder="Icon Name" size="xs" x-on:input="isDirty = true" />
                                                <flux:input x-model="item.cta_text" placeholder="CTA Label" size="xs" x-on:input="isDirty = true" />
                                            </div>

                                            <template x-if="item.cta_text">
                                                <div class="grid grid-cols-2 gap-2">
                                                    <flux:input x-model="item.cta_url" placeholder="URL or #anchor" size="xs" x-on:input="isDirty = true" />
                                                    <select x-model="item.cta_target" class="text-xs bg-white dark:bg-zinc-800 border border-zinc-200 rounded p-1" x-on:change="isDirty = true">
                                                        <option value="_self">Same Tab</option>
                                                        <option value="_blank">New Tab</option>
                                                    </select>
                                                </div>
                                            </template>

                                            <template x-if="selectedBlock.variant === 'alternating-rows'">
                                                <flux:input x-model="item.image_url" placeholder="Image URL" size="xs" x-on:input="isDirty = true" />
                                            </template>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.features = selectedBlock.config.features || []; selectedBlock.config.features.push({title: 'New Feature', description: '', icon: 'star'}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Feature') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <!-- Pricing Editor -->
                        <template x-if="selectedBlock.type === 'pricing'">
                            <div class="space-y-6">
                                <div class="space-y-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <div class="flex items-center justify-between">
                                        <flux:label>{{ __('Show Monthly/Annual Toggle') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_toggle" x-on:change="isDirty = true" />
                                    </div>
                                    <template x-if="selectedBlock.config.show_toggle">
                                        <flux:input x-model="selectedBlock.config.annual_discount_text" placeholder="e.g. Save 20%" x-on:input="isDirty = true" size="sm" />
                                    </template>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Plans') }}</flux:heading>
                                    <template x-for="(plan, index) in selectedBlock.config.plans" :key="index">
                                        <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-4">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="plan.name" placeholder="Plan Name" x-on:input="isDirty = true" size="sm" class="font-bold w-2/3" />
                                                <button x-on:click="selectedBlock.config.plans.splice(index, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="flex items-center justify-between col-span-2 py-1">
                                                    <flux:label size="sm">{{ __('Featured Plan') }}</flux:label>
                                                    <flux:switch x-model="plan.is_featured" x-on:change="isDirty = true" />
                                                </div>
                                                <flux:input x-model="plan.badge" placeholder="Badge (e.g. Popular)" size="xs" x-on:input="isDirty = true" class="col-span-2" />
                                            </div>

                                            <div class="grid grid-cols-3 gap-2">
                                                <flux:input x-model="plan.currency" placeholder="$" size="xs" x-on:input="isDirty = true" />
                                                <flux:input type="number" x-model="plan.price_monthly" placeholder="Monthly" size="xs" x-on:input="isDirty = true" />
                                                <flux:input type="number" x-model="plan.price_annual" placeholder="Annual" size="xs" x-on:input="isDirty = true" />
                                            </div>

                                            <div class="space-y-2">
                                                <flux:label size="sm">{{ __('Features') }}</flux:label>
                                                <template x-for="(f, fIndex) in plan.features" :key="fIndex">
                                                    <div class="flex gap-2 items-center">
                                                        <input type="checkbox" x-model="f.included" x-on:change="isDirty = true" class="rounded text-primary focus:ring-primary h-3 w-3">
                                                        <flux:input x-model="f.text" placeholder="Feature..." size="xs" class="flex-1" x-on:input="isDirty = true" />
                                                        <button x-on:click="plan.features.splice(fIndex, 1); isDirty = true" class="text-zinc-400"><flux:icon.x-mark size="xs" /></button>
                                                    </div>
                                                </template>
                                                <flux:button x-on:click="plan.features = plan.features || []; plan.features.push({text: 'Feature', included: true}); isDirty = true" variant="ghost" size="xs" class="w-full">+ Add Feature</flux:button>
                                            </div>

                                            <div class="grid grid-cols-2 gap-2 pt-2 border-t border-zinc-200 dark:border-zinc-800">
                                                <flux:input x-model="plan.cta_text" placeholder="CTA Label" size="xs" x-on:input="isDirty = true" />
                                                <flux:input x-model="plan.cta_url" placeholder="CTA URL" size="xs" x-on:input="isDirty = true" />
                                            </div>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.plans = selectedBlock.config.plans || []; selectedBlock.config.plans.push({name: 'New Plan', price_monthly: 19, price_annual: 15, currency: '$', features: [], is_featured: false}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Plan') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <!-- Testimonials Editor -->
                        <template x-if="selectedBlock.type === 'testimonials'">
                            <div class="space-y-6">
                                <div class="grid grid-cols-2 gap-4 p-4 bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700">
                                    <template x-if="selectedBlock.variant === 'grid'">
                                        <flux:field>
                                            <flux:label>{{ __('Grid Columns') }}</flux:label>
                                            <flux:select x-model="selectedBlock.config.columns_count" x-on:change="isDirty = true">
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                            </flux:select>
                                        </flux:field>
                                    </template>
                                    <template x-if="selectedBlock.variant === 'carousel'">
                                        <div class="flex items-center justify-between col-span-2">
                                            <flux:label>{{ __('Autoplay') }}</flux:label>
                                            <flux:switch x-model="selectedBlock.config.autoplay" x-on:change="isDirty = true" />
                                        </div>
                                    </template>
                                    <div class="flex items-center justify-between col-span-1 pt-1">
                                        <flux:label size="sm">{{ __('Show Ratings') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_rating" x-on:change="isDirty = true" />
                                    </div>
                                    <div class="flex items-center justify-between col-span-1 pt-1">
                                        <flux:label size="sm">{{ __('Show Avatars') }}</flux:label>
                                        <flux:switch x-model="selectedBlock.config.show_avatars" x-on:change="isDirty = true" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <flux:heading size="sm">{{ __('Manage Testimonials') }}</flux:heading>
                                    <template x-for="(item, index) in selectedBlock.config.testimonials" :key="index">
                                        <div class="p-4 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 space-y-3">
                                            <div class="flex justify-between items-center">
                                                <flux:input x-model="item.name" placeholder="Client Name" x-on:input="isDirty = true" size="sm" class="font-bold w-2/3" />
                                                <button x-on:click="selectedBlock.config.testimonials.splice(index, 1); isDirty = true" class="text-red-500"><flux:icon.trash size="xs" /></button>
                                            </div>
                                            <flux:textarea x-model="item.quote" placeholder="Testimonial quote..." x-on:input="isDirty = true" rows="3" size="sm" />
                                            
                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="item.role" placeholder="Role (e.g. CEO)" size="xs" x-on:input="isDirty = true" />
                                                <flux:input x-model="item.company" placeholder="Company" size="xs" x-on:input="isDirty = true" />
                                            </div>

                                            <div class="grid grid-cols-2 gap-2">
                                                <flux:input x-model="item.avatar_url" placeholder="Avatar URL" size="xs" x-on:input="isDirty = true" />
                                                <flux:select x-model="item.rating" size="xs" x-on:change="isDirty = true">
                                                    <option value="5">5 Stars</option>
                                                    <option value="4">4 Stars</option>
                                                    <option value="3">3 Stars</option>
                                                </flux:select>
                                            </div>
                                        </div>
                                    </template>
                                    <flux:button x-on:click="selectedBlock.config.testimonials = selectedBlock.config.testimonials || []; selectedBlock.config.testimonials.push({name: 'New Client', quote: 'Amazing service!', role: 'Manager', company: 'Acme Inc', rating: 5}); isDirty = true" variant="outline" size="xs" class="w-full">
                                        {{ __('+ Add Testimonial') }}
                                    </flux:button>
                                </div>
                            </div>
                        </template>

                        <div class="my-4 h-px w-full bg-zinc-200"></div>

                        <flux:heading size="sm" class="uppercase text-zinc-500">{{ __('Styles') }}</flux:heading>
                        
                        <flux:field>
                            <flux:label>{{ __('Background') }}</flux:label>
                            <flux:select x-model="selectedBlock.styles.background" x-on:change="isDirty = true">
                                <option value="white">{{ __('White') }}</option>
                                <option value="surface">{{ __('Surface') }}</option>
                                <option value="primary">{{ __('Primary') }}</option>
                                <option value="dark">{{ __('Dark') }}</option>
                                <option value="gradient" x-show="['hero', 'cta'].includes(selectedBlock.type)">{{ __('Gradient') }}</option>
                            </flux:select>
                        </flux:field>

                        <template x-if="['hero', 'cta'].includes(selectedBlock.type)">
                            <div class="space-y-4 pt-4">
                                <flux:field>
                                    <flux:label>{{ __('Text Alignment') }}</flux:label>
                                    <flux:select x-model="selectedBlock.styles.text_align" x-on:change="isDirty = true">
                                        <option value="left">{{ __('Left') }}</option>
                                        <option value="center">{{ __('Center') }}</option>
                                        <option value="right">{{ __('Right') }}</option>
                                    </flux:select>
                                </flux:field>

                                <template x-if="selectedBlock.variant === 'bg-image'">
                                    <flux:field>
                                        <flux:label>{{ __('Overlay Opacity') }} (<span x-text="selectedBlock.styles.overlay_opacity || 50"></span>%)</flux:label>
                                        <input type="range" x-model="selectedBlock.styles.overlay_opacity" min="0" max="100" step="5" class="w-full" x-on:input="isDirty = true">
                                    </flux:field>
                                </template>

                                <flux:field>
                                    <flux:label>{{ __('Height') }}</flux:label>
                                    <flux:select x-model="selectedBlock.styles.height" x-on:change="isDirty = true">
                                        <option value="auto">{{ __('Auto (Content based)') }}</option>
                                        <option value="screen">{{ __('Full Screen (100vh)') }}</option>
                                    </flux:select>
                                </flux:field>
                            </div>
                        </template>
                        
                        <!-- FAQ Advanced Styles -->
                        <template x-if="selectedBlock.type === 'faq'">
                            <div class="space-y-4 pt-4">
                                <flux:field>
                                    <flux:label>{{ __('Header Alignment') }}</flux:label>
                                    <flux:select x-model="selectedBlock.styles.text_align" x-on:change="isDirty = true">
                                        <option value="center">{{ __('Center') }}</option>
                                        <option value="left">{{ __('Left') }}</option>
                                    </flux:select>
                                </flux:field>
                                <template x-if="selectedBlock.variant === 'accordion'">
                                    <flux:field>
                                        <flux:label>{{ __('Item Style') }}</flux:label>
                                        <flux:select x-model="selectedBlock.styles.item_style" x-on:change="isDirty = true">
                                            <option value="boxed">{{ __('Boxed') }}</option>
                                            <option value="separated">{{ __('Separated Cards') }}</option>
                                            <option value="flat">{{ __('Flat (Divider only)') }}</option>
                                        </flux:select>
                                    </flux:field>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </aside>
    </div>
</div>
