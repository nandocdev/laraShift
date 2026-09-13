<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Resources') }}</flux:heading>
            <flux:subheading>{{ __('Staff, rooms and equipment of :tenant. Assign services, skills and rates per resource.', ['tenant' => tenant('name')]) }}
            </flux:subheading>
        </div>

        <flux:modal.trigger name="resource-form">
            <flux:button variant="primary" icon="plus">{{ __('New Resource') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
        <div class="flex flex-col md:flex-row gap-3">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="Maria…" />
            <flux:select wire:model.live="typeFilter" :label="__('Type')" placeholder="All types">
                <option value="staff">{{ __('Staff') }}</option>
                <option value="room">{{ __('Room') }}</option>
                <option value="equipment">{{ __('Equipment') }}</option>
            </flux:select>
        </div>
        <flux:switch wire:model.live="showTrashed" :label="__('Show deleted')" />
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$resources">
            <flux:table.columns>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('Services') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($resources as $resource)
                    <flux:table.row :key="$resource->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $resource->name }}</span>
                                <span class="text-xs text-zinc-500">
                                    {{ __('Capacity') }}: {{ $resource->capacity }}
                                    @if ($resource->rate_cents !== null)
                                        · {{ number_format($resource->rate_cents / 100, 2) }} {{ $resource->currency ?? '' }}
                                    @endif
                                </span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ strtoupper($resource->type->value) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $resource->location?->name ?? __('All locations') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $resource->services->pluck('name')->join(', ') ?: '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($resource->trashed())
                                <flux:badge size="sm" variant="danger">{{ __('DELETED') }}</flux:badge>
                            @elseif ($resource->is_active)
                                <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="warning">{{ __('INACTIVE') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Resource actions') }}" />
                                <flux:menu>
                                    @if ($resource->trashed())
                                        <flux:menu.item icon="arrow-uturn-left" wire:click="restore('{{ $resource->id }}')">
                                            {{ __('Restore') }}</flux:menu.item>
                                    @else
                                        <flux:modal.trigger name="resource-form">
                                            <flux:menu.item icon="pencil" wire:click="edit('{{ $resource->id }}')">
                                                {{ __('Edit / Transfer') }}</flux:menu.item>
                                        </flux:modal.trigger>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete('{{ $resource->id }}')">
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

    <flux:modal name="resource-form" class="md:max-w-3xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit Resource') : __('New Resource') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" :label="__('Name')" placeholder="Maria Lopez" required />
                <flux:input wire:model="slug" :label="__('Slug')" placeholder="maria-lopez" required />
                <flux:select wire:model="type" :label="__('Type')">
                    <option value="staff">{{ __('Staff') }}</option>
                    <option value="room">{{ __('Room') }}</option>
                    <option value="equipment">{{ __('Equipment') }}</option>
                </flux:select>
                <flux:select wire:model="locationId" :label="__('Location (empty = all locations)')" placeholder="All locations">
                    <option value="">{{ __('All locations') }}</option>
                    @foreach ($this->locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="capacity" type="number" :label="__('Capacity')" min="1" required />
                <div class="flex items-end pb-2">
                    <flux:switch wire:model="isActive" :label="__('Active')" />
                </div>
            </div>

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="rate" :label="__('Rate override (empty = service price)')" placeholder="30.00" />
                <flux:input wire:model="currency" :label="__('Currency (empty = business default)')" placeholder="USD" maxlength="3" />
            </div>

            <flux:heading size="sm">{{ __('Skills & Specialties') }}</flux:heading>

            @foreach ($skills as $index => $skill)
                <div class="grid grid-cols-[1fr_auto] gap-2 items-end" wire:key="skill-{{ $index }}">
                    <flux:input wire:model="skills.{{ $index }}" :label="__('Skill')" placeholder="hair coloring" />
                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeSkill({{ $index }})" aria-label="{{ __('Remove skill') }}" />
                </div>
            @endforeach

            <div>
                <flux:button variant="ghost" size="sm" icon="plus" wire:click="addSkill">{{ __('Add skill') }}</flux:button>
            </div>

            @if ($this->services->isNotEmpty())
                <flux:heading size="sm">{{ __('Assigned Services') }}</flux:heading>

                <div class="flex flex-col gap-2">
                    @foreach ($this->services as $service)
                        <label class="flex items-center gap-2 text-sm">
                            <flux:checkbox wire:model="serviceIds" value="{{ $service->id }}" />
                            {{ $service->name }}
                        </label>
                    @endforeach
                </div>
            @endif

            <flux:error name="slug" />
            <flux:error name="locationId" />
            <flux:error name="serviceIds" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
