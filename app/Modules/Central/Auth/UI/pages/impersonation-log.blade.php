<div class="flex flex-col gap-6 py-8">
    <div>
        <flux:heading size="xl">{{ __('Impersonation Log') }}</flux:heading>
        <flux:subheading>{{ __('Audit trail of all super-admin impersonation sessions.') }}</flux:subheading>
    </div>

    <flux:card class="flex flex-wrap gap-4 items-end">
        <div class="w-40">
            <flux:select wire:model.live="filterStatus" :label="__('Status')">
                <option value="">{{ __('All') }}</option>
                <option value="active">{{ __('Active') }}</option>
                <option value="ended">{{ __('Ended') }}</option>
            </flux:select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Search by operator, tenant or reason...') }}" />
        </div>
    </flux:card>

    @if(session('status'))
        <flux:toast variant="success" :text="session('status')" />
    @endif

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$sessions">
            <flux:table.columns>
                <flux:table.column>{{ __('Operator') }}</flux:table.column>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column>{{ __('Ended') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($sessions as $session)
                    <flux:table.row :key="$session->id">
                        <flux:table.cell>
                            <div class="flex flex-col">
                                <span class="font-medium text-sm">{{ $session->operator?->name ?? __('Deleted operator') }}</span>
                                <span class="text-xs text-zinc-400">{{ $session->operator?->email }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $session->tenant?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="max-w-[200px] truncate text-sm" title="{{ $session->reason }}">
                            {{ $session->reason }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs whitespace-nowrap">{{ $session->started_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell class="text-xs whitespace-nowrap">
                            {{ $session->ended_at?->format('Y-m-d H:i') ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($session->isActive())
                                <flux:badge size="sm" color="emerald">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Ended') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400 py-8">
                            {{ __('No impersonation sessions found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
