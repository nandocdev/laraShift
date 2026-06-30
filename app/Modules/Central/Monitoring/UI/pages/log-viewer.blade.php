<div class="flex flex-col gap-6 py-8">
    <div>
        <flux:heading size="xl">{{ __('Activity Log') }}</flux:heading>
        <flux:subheading>{{ __('Centralized audit of all platform events.') }}</flux:subheading>
    </div>

    <flux:card class="flex flex-wrap gap-4 items-end">
        <div class="w-48">
            <flux:select wire:model.live="filterLog" :label="__('Log Type')">
                <option value="">All</option>
                @foreach($logNames as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </flux:select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Search events...') }}" />
        </div>
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Event') }}</flux:table.column>
                <flux:table.column>{{ __('Actor') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="text-xs text-zinc-400 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="xs" variant="outline">{{ $log->log_name }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs max-w-xs truncate font-mono">{{ $log->description }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $log->causer?->name ?? __('System') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">{{ __('No log entries found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
