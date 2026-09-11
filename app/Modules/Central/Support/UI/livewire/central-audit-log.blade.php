<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Audit Log') }}</flux:heading>
            <flux:subheading>{{ __('Trazabilidad de acciones administrativas. Inmutable: sin borrado.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" size="sm" icon="arrow-down-tray" wire:click="export">{{ __('Export') }}</flux:button>
    </div>

    <flux:card>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Action…') }}" />
            <flux:select wire:model.live="logFilter" :label="__('Log')">
                <option value="">{{ __('All') }}</option>
                @foreach ($this->logs as $log)
                    <option value="{{ $log['value'] }}">{{ $log['label'] }}</option>
                @endforeach
            </flux:select>
            <flux:input wire:model.live.debounce.500ms="tenantSlug" :label="__('Tenant')" placeholder="{{ __('slug…') }}" />
            <flux:input wire:model.live="dateFrom" type="date" :label="__('From')" />
            <flux:input wire:model.live="dateTo" type="date" :label="__('To')" />
            <div class="flex items-end">
                <flux:button variant="ghost" size="sm" wire:click="clearFilters">{{ __('Clear') }}</flux:button>
            </div>
        </div>

        <flux:table :paginate="$entries">
            <flux:table.columns>
                <flux:table.column>{{ __('Actor') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell class="font-medium">
                            {{ $entry->causer?->name ?? __('System') }}
                            <div class="text-xs text-neutral-500">{{ $entry->log_name }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ str($entry->description)->replace('_', ' ')->title() }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry->subject_type === \App\Modules\Central\Provisioning\Models\Tenant::class && isset($tenantNames[$entry->subject_id]))
                                {{ $tenantNames[$entry->subject_id] }}
                            @elseif($entry->subject_type)
                                {{ class_basename($entry->subject_type) }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $entry->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:modal.trigger name="audit-event-view">
                                <flux:button variant="ghost" size="sm" wire:click="view('{{ $entry->id }}')">{{ __('View') }}</flux:button>
                            </flux:modal.trigger>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-sm text-zinc-500">
                            {{ __('No audit events match the current filters.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="audit-event-view" class="min-w-[28rem]">
        @if($this->selected)
            <div class="space-y-4">
                <flux:heading size="lg">{{ str($this->selected->description)->replace('_', ' ')->title() }}</flux:heading>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="font-semibold">{{ __('Log') }}</dt><dd>{{ $this->selected->log_name ?? '—' }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Date') }}</dt><dd>{{ $this->selected->created_at }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Actor') }}</dt><dd>{{ $this->selected->causer?->name ?? __('System') }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Event') }}</dt><dd>{{ $this->selected->event ?? '—' }}</dd></div>
                </dl>
                <div>
                    <flux:heading size="sm">{{ __('Properties') }}</flux:heading>
                    <pre class="mt-2 max-h-64 overflow-auto rounded bg-zinc-950 p-3 text-xs text-zinc-100">{{ json_encode($this->selected->properties?->toArray() ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
