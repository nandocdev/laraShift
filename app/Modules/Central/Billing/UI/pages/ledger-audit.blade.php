<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Ledger Audit') }}</flux:heading>
            <flux:subheading>{{ __('Complete double-entry accounting ledger showing B2C transaction history across all tenants.') }}</flux:subheading>
        </div>
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search references or description...') }}" icon="magnifying-glass" />
        
        <flux:select wire:model.live="tenantId" placeholder="{{ __('All Tenants') }}">
            <flux:select.option value="">{{ __('All Tenants') }}</flux:select.option>
            @foreach ($tenants as $tenant)
                <flux:select.option value="{{ $tenant->id }}">{{ $tenant->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="type" placeholder="{{ __('All Types') }}">
            <flux:select.option value="">{{ __('All Types') }}</flux:select.option>
            <flux:select.option value="CREDIT">{{ __('CREDIT') }}</flux:select.option>
            <flux:select.option value="DEBIT">{{ __('DEBIT') }}</flux:select.option>
        </flux:select>
        
        @if($tenantId || $type || $search)
            <flux:button wire:click="$set('tenantId', ''); $set('type', ''); $set('search', '');" variant="ghost">
                {{ __('Clear Filters') }}
            </flux:button>
        @endif
    </div>

    <!-- Table -->
    <flux:card>
        <flux:table :paginate="$entries">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Tenant ID') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">{{ __('Type') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'amount'" :direction="$sortDirection" wire:click="sort('amount')">{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($entries as $entry)
                    <flux:table.row :key="$entry->id">
                        <flux:table.cell class="text-zinc-500 text-xs">
                            {{ $entry->created_at->format('Y-m-d H:i:s') }}
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            {{ $entry->tenant_id }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$entry->type === 'CREDIT' ? 'success' : 'danger'">
                                {{ $entry->type }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">
                            <span class="{{ $entry->type === 'CREDIT' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ $entry->type === 'CREDIT' ? '+' : '-' }}{{ number_format($entry->amount, 2) }} {{ strtoupper($entry->currency) }}
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($entry->reference_type)
                                <div class="text-sm font-medium">{{ class_basename($entry->reference_type) }}</div>
                                <div class="text-[10px] text-zinc-500 font-mono">#{{ $entry->reference_id }}</div>
                            @else
                                <span class="text-zinc-400 font-normal">-</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-600 dark:text-zinc-400 max-w-xs truncate">
                            {{ $entry->description }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8 text-zinc-400">
                            {{ __('No ledger entries found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
