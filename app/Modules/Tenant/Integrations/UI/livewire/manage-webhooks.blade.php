<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Webhooks') }}</flux:heading>
            <flux:subheading>{{ __('Send real-time events to external endpoints when actions occur in your tenant.') }}</flux:subheading>
        </div>
        <flux:button wire:click="resetForm" icon="plus" @click="$dispatch('show-webhook-form')">
            {{ __('New Webhook') }}
        </flux:button>
    </div>

    <flux:modal name="webhook-form" class="min-w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $isEditing ? __('Edit Webhook') : __('New Webhook') }}</flux:heading>
            </div>

            <flux:input wire:model="url" :label="__('Endpoint URL')" type="url" placeholder="https://example.com/webhooks/larashift" required />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="secret" :label="__('Signing Secret')" required />
                </div>
                <flux:button variant="ghost" wire:click="regenerateSecret" icon="arrow-path">{{ __('Regenerate') }}</flux:button>
            </div>

            <flux:field>
                <flux:label>{{ __('Subscribed Events') }}</flux:label>
                <div class="grid grid-cols-2 gap-2 mt-1">
                    @foreach ($availableEvents as $key => $label)
                        <flux:checkbox wire:model="selectedEvents" value="{{ $key }}" :label="$label" />
                    @endforeach
                </div>
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="max_retries" type="number" min="0" max="20" :label="__('Max Retries')" />
                <flux:input wire:model="timeout_seconds" type="number" min="1" max="30" :label="__('Timeout (seconds)')" />
            </div>

            <flux:checkbox wire:model="is_active" :label="__('Webhook is active')" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="resetForm">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('URL') }}</flux:table.column>
                <flux:table.column>{{ __('Events') }}</flux:table.column>
                <flux:table.column>{{ __('Retries') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($webhooks as $webhook)
                    <flux:table.row :key="$webhook->id">
                        <flux:table.cell class="max-w-[200px] truncate text-xs font-mono">{{ $webhook->url }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach (array_slice($webhook->events, 0, 3) as $event)
                                    <flux:badge size="sm" variant="outline" class="text-[10px]">{{ $event }}</flux:badge>
                                @endforeach
                                @if (count($webhook->events) > 3)
                                    <flux:badge size="sm" variant="ghost">+{{ count($webhook->events) - 3 }}</flux:badge>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $webhook->max_retries }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$webhook->is_active ? 'emerald' : 'zinc'">
                                {{ $webhook->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-1">
                                <flux:button wire:click="edit('{{ $webhook->id }}')" size="sm" variant="ghost" icon="pencil" />
                                <flux:button wire:click="delete('{{ $webhook->id }}')" size="sm" variant="ghost" icon="trash" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">
                            {{ __('No webhooks configured.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
