<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Locations') }}</flux:heading>
            <flux:subheading>{{ __('Manage the branches of :tenant. Each location can have its own address, contact and timezone.', ['tenant' => tenant('name')]) }}
            </flux:subheading>
        </div>

        <flux:modal.trigger name="location-form">
            <flux:button variant="primary" icon="plus">{{ __('New Location') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="flex items-center justify-end">
        <flux:switch wire:model.live="showTrashed" :label="__('Show deleted')" />
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$locations">
            <flux:table.columns>
                <flux:table.column>{{ __('Location') }}</flux:table.column>
                <flux:table.column>{{ __('City') }}</flux:table.column>
                <flux:table.column>{{ __('Timezone') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($locations as $location)
                    <flux:table.row :key="$location->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-sm text-zinc-900 dark:text-white">{{ $location->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $location->slug }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $location->address['city'] ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $location->timezone ?? __('Business default') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($location->trashed())
                                <flux:badge size="sm" variant="danger">{{ __('DELETED') }}</flux:badge>
                            @elseif ($location->is_active)
                                <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="warning">{{ __('INACTIVE') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" class="min-h-12 min-w-12" aria-label="{{ __('Location actions') }}" />
                                <flux:menu>
                                    @if ($location->trashed())
                                        <flux:menu.item icon="arrow-uturn-left" wire:click="restore('{{ $location->id }}')">
                                            {{ __('Restore') }}</flux:menu.item>
                                    @else
                                        <flux:modal.trigger name="location-form">
                                            <flux:menu.item icon="pencil" wire:click="edit('{{ $location->id }}')">
                                                {{ __('Edit') }}</flux:menu.item>
                                        </flux:modal.trigger>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="delete('{{ $location->id }}')">
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

    <flux:modal name="location-form" class="md:max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:heading size="lg">{{ $editingId ? __('Edit Location') : __('New Location') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="name" :label="__('Name')" placeholder="Downtown Branch" required />
                <flux:input wire:model="slug" :label="__('Slug')" placeholder="downtown-branch" required />
                <flux:input wire:model="email" type="email" :label="__('Contact Email')" />
                <flux:input wire:model="phone" :label="__('Contact Phone')" />
                <flux:select wire:model="timezone" :label="__('Timezone (empty = business default)')" placeholder="Business default">
                    <option value="">{{ __('Business default') }}</option>
                    @foreach (timezone_identifiers_list() as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </flux:select>
                <div class="flex items-end pb-2">
                    <flux:switch wire:model="isActive" :label="__('Active')" />
                </div>
            </div>

            <flux:heading size="sm">{{ __('Address') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <flux:input wire:model="address.line1" :label="__('Street')" class="md:col-span-2" />
                <flux:input wire:model="address.city" :label="__('City')" />
                <flux:input wire:model="address.state" :label="__('State / Province')" />
                <flux:input wire:model="address.postal_code" :label="__('Postal Code')" />
                <flux:input wire:model="address.country" :label="__('Country (ISO-2)')" maxlength="2" />
            </div>

            <flux:error name="slug" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
