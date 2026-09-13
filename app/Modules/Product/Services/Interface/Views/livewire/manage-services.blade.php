<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Services') }}</flux:heading>
            <flux:subheading>{{ __('Define what :tenant offers: duration, capacity, pricing, deposits and cancellation rules.', ['tenant' => tenant('name')]) }}
            </flux:subheading>
        </div>

        <div class="flex gap-2">
            <flux:modal.trigger name="category-form">
                <flux:button variant="ghost" icon="tag">{{ __('New Category') }}</flux:button>
            </flux:modal.trigger>
            <flux:modal.trigger name="service-form">
                <flux:button variant="primary" icon="plus">{{ __('New Service') }}</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="flex gap-2">
        <flux:button :variant="$activeTab === 'services' ? 'primary' : 'ghost'" icon="sparkles" wire:click="$set('activeTab', 'services')">{{ __('Services') }}</flux:button>
        <flux:button :variant="$activeTab === 'categories' ? 'primary' : 'ghost'" icon="tag" wire:click="$set('activeTab', 'categories')">{{ __('Categories') }}</flux:button>
    </div>

    @if ($activeTab === 'services')
        <div class="flex flex-col gap-4">
            <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between py-4">
                <div class="flex flex-col md:flex-row gap-3">
                    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="Haircut…" />
                    <flux:select wire:model.live="categoryFilter" :label="__('Category')" placeholder="All categories">
                        @foreach ($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:switch wire:model.live="showTrashed" :label="__('Show deleted')" />
            </div>

            <flux:card class="overflow-hidden">
                <flux:table :paginate="$services">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Service') }}</flux:table.column>
                        <flux:table.column>{{ __('Duration') }}</flux:table.column>
                        <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                        <flux:table.column>{{ __('Price') }}</flux:table.column>
                        <flux:table.column>{{ __('Deposit') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($services as $service)
                            <flux:table.row :key="$service->id">
                                <flux:table.cell>
                                    <div class="flex flex-col">
                                        <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $service->name }}</span>
                                        <span class="text-xs text-zinc-500">
                                            {{ $service->category?->name ?? __('Uncategorized') }}
                                            @if ($service->locations->isNotEmpty())
                                                · {{ $service->locations->pluck('name')->join(', ') }}
                                            @endif
                                        </span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $service->duration_minutes }} min</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $service->capacity }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">
                                    {{ number_format($service->price_cents / 100, 2) }} {{ $service->currency ?? '' }}
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">
                                    @if ($service->deposit_type->value === 'percentage')
                                        {{ $service->deposit_value }}%
                                    @elseif ($service->deposit_type->value === 'fixed')
                                        {{ number_format(($service->deposit_value ?? 0) / 100, 2) }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($service->trashed())
                                        <flux:badge size="sm" variant="danger">{{ __('DELETED') }}</flux:badge>
                                    @elseif ($service->is_active)
                                        <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="warning">{{ __('INACTIVE') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Service actions') }}" />
                                        <flux:menu>
                                            @if ($service->trashed())
                                                <flux:menu.item icon="arrow-uturn-left" wire:click="restoreService('{{ $service->id }}')">
                                                    {{ __('Restore') }}</flux:menu.item>
                                            @else
                                                <flux:modal.trigger name="service-form">
                                                    <flux:menu.item icon="pencil" wire:click="editService('{{ $service->id }}')">
                                                        {{ __('Edit') }}</flux:menu.item>
                                                </flux:modal.trigger>
                                                <flux:menu.separator />
                                                <flux:menu.item variant="danger" icon="trash" wire:click="deleteService('{{ $service->id }}')">
                                                    {{ __('Delete') }}</flux:menu.item>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @else
        <div class="flex flex-col gap-4">
            <flux:card class="overflow-hidden mt-4">
                <flux:table :paginate="$categoriesList">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Category') }}</flux:table.column>
                        <flux:table.column>{{ __('Services') }}</flux:table.column>
                        <flux:table.column>{{ __('Order') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($categoriesList as $category)
                            <flux:table.row :key="$category->id">
                                <flux:table.cell>
                                    <div class="flex items-center gap-2">
                                        @if ($category->color)
                                            <span class="inline-block size-4 rounded-full" style="background-color: {{ $category->color }}"></span>
                                        @endif
                                        <div class="flex flex-col">
                                            <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $category->name }}</span>
                                            <span class="text-xs text-zinc-500">{{ $category->slug }}</span>
                                        </div>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $category->services_count }}</flux:table.cell>
                                <flux:table.cell class="text-sm text-zinc-500">{{ $category->sort_order }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($category->trashed())
                                        <flux:badge size="sm" variant="danger">{{ __('DELETED') }}</flux:badge>
                                    @elseif ($category->is_active)
                                        <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                                    @else
                                        <flux:badge size="sm" variant="warning">{{ __('INACTIVE') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    @if (! $category->trashed())
                                        <flux:dropdown>
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Category actions') }}" />
                                            <flux:menu>
                                                <flux:modal.trigger name="category-form">
                                                    <flux:menu.item icon="pencil" wire:click="editCategory('{{ $category->id }}')">
                                                        {{ __('Edit') }}</flux:menu.item>
                                                </flux:modal.trigger>
                                                <flux:menu.separator />
                                                <flux:menu.item variant="danger" icon="trash" wire:click="deleteCategory('{{ $category->id }}')">
                                                    {{ __('Delete') }}</flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        </div>
    @endif

    <flux:modal name="service-form" class="md:max-w-3xl">
        <form wire:submit="saveService" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit Service') : __('New Service') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" :label="__('Name')" placeholder="Haircut" required />
                <flux:input wire:model="slug" :label="__('Slug')" placeholder="haircut" required />
                <flux:select wire:model="categoryId" :label="__('Category')" placeholder="Uncategorized">
                    <option value="">{{ __('Uncategorized') }}</option>
                    @foreach ($this->categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </flux:select>
                <div class="flex items-end pb-2">
                    <flux:switch wire:model="isActive" :label="__('Active')" />
                </div>
            </div>

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <flux:input wire:model="durationMinutes" type="number" :label="__('Duration (min)')" min="1" required />
                <flux:input wire:model="capacity" type="number" :label="__('Capacity')" min="1" required />
                <flux:input wire:model="bufferBeforeMinutes" type="number" :label="__('Buffer before (min)')" min="0" />
                <flux:input wire:model="bufferAfterMinutes" type="number" :label="__('Buffer after (min)')" min="0" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="price" :label="__('Price')" placeholder="25.00" required />
                <flux:input wire:model="currency" :label="__('Currency (empty = business default)')" placeholder="USD" maxlength="3" />
            </div>

            <flux:heading size="sm">{{ __('Deposit Policy') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:select wire:model.live="depositType" :label="__('Deposit type')">
                    <option value="none">{{ __('None') }}</option>
                    <option value="percentage">{{ __('Percentage') }}</option>
                    <option value="fixed">{{ __('Fixed amount') }}</option>
                </flux:select>
                @if ($depositType !== 'none')
                    <flux:input wire:model="depositValue" :label="__('Deposit value')" :placeholder="$depositType === 'percentage' ? '20' : '10.00'" />
                @endif
            </div>
            <flux:error name="depositValue" />

            <flux:heading size="sm">{{ __('Cancellation Policy') }}</flux:heading>

            <flux:input wire:model="cancellationWindowHours" type="number" :label="__('Free cancellation up to (hours before)')" min="0" />

            @foreach ($cancellationRules as $index => $rule)
                <div class="grid grid-cols-[1fr_1fr_auto] gap-2 items-end" wire:key="cancel-rule-{{ $index }}">
                    <flux:input wire:model="cancellationRules.{{ $index }}.min_hours" type="number" :label="__('From (hours before)')" min="0" />
                    <flux:input wire:model="cancellationRules.{{ $index }}.refund_percent" type="number" :label="__('Refund %')" min="0" max="100" />
                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeCancellationRule({{ $index }})" aria-label="{{ __('Remove rule') }}" />
                </div>
            @endforeach

            <div>
                <flux:button variant="ghost" size="sm" icon="plus" wire:click="addCancellationRule">{{ __('Add refund rule') }}</flux:button>
            </div>

            @if ($this->locations->isNotEmpty())
                <flux:heading size="sm">{{ __('Offered at') }}</flux:heading>

                <div class="flex flex-col gap-2">
                    @foreach ($this->locations as $location)
                        <label class="flex items-center gap-2 text-sm">
                            <flux:checkbox wire:model="locationIds" value="{{ $location->id }}" />
                            {{ $location->name }}
                        </label>
                    @endforeach
                </div>
            @endif

            <flux:error name="slug" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelServiceEdit">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="category-form" class="md:max-w-xl">
        <form wire:submit="saveCategory" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingCategoryId ? __('Edit Category') : __('New Category') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="categoryName" :label="__('Name')" placeholder="Hair" required />
                <flux:input wire:model="categorySlug" :label="__('Slug')" placeholder="hair" required />
                <flux:input wire:model="categoryColor" type="color" :label="__('Color')" />
                <flux:input wire:model="categorySortOrder" type="number" :label="__('Order')" min="0" />
            </div>

            <flux:textarea wire:model="categoryDescription" :label="__('Description')" rows="2" />

            <div class="flex items-center">
                <flux:switch wire:model="categoryIsActive" :label="__('Active')" />
            </div>

            <flux:error name="categorySlug" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelCategoryEdit">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
