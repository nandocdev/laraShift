<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Notification Templates') }}</flux:heading>
            <flux:subheading>{{ __('Customize email and in-app notification content for your tenant.') }}</flux:subheading>
        </div>
        <flux:button wire:click="resetForm" icon="plus" @click="$dispatch('show-template-form')">
            {{ __('New Template') }}
        </flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:modal name="template-form" wire:model.live="isEditing">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEditing ? __('Edit Template') : __('New Template') }}</flux:heading>
            </div>

            <flux:input wire:model="key" :label="__('Notification Key')" placeholder="e.g. user.invited" :disabled="$isEditing" />

            <flux:select wire:model="channel" :label="__('Channel')">
                <option value="email">{{ __('Email') }}</option>
                <option value="in-app">{{ __('In-App') }}</option>
            </flux:select>

            <flux:input wire:model="subject" :label="__('Subject')" placeholder="{{ __('Subject line for email') }}" />

            <flux:textarea wire:model="body" :label="__('Body')" rows="6"
                placeholder="Use {name}, {email}, {tenant} as placeholders." />

            <flux:checkbox wire:model="is_active" :label="__('Active')" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Key') }}</flux:table.column>
                <flux:table.column>{{ __('Channel') }}</flux:table.column>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Active') }}</flux:table.column>
                <flux:table.column>{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($templates as $template)
                    <flux:table.row :key="$template->id">
                        <flux:table.cell class="font-mono text-xs">{{ $template->key }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $template->channel }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $template->subject ?: '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$template->is_active ? 'emerald' : 'zinc'">
                                {{ $template->is_active ? __('Yes') : __('No') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:button wire:click="edit('{{ $template->id }}')" size="sm" variant="ghost" icon="pencil" />
                                <flux:button wire:click="delete('{{ $template->id }}')" size="sm" variant="ghost" icon="trash" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('No templates yet. Create one to customize notifications.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
