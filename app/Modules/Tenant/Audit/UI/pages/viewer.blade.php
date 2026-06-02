<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Audit Logs') }}</flux:heading>
            <flux:subheading>{{ __('Immutable history of actions performed within this account.') }}</flux:subheading>
        </div>
        
        <flux:button icon="document-arrow-up" wire:click="$set('showingExportModal', true)">{{ __('Export CSV') }}</flux:button>
    </div>

    <!-- Filter Bar -->
    <flux:card class="flex flex-wrap gap-4 items-end">
        <div class="flex-1 min-w-[200px]">
            <flux:select wire:model.live="filterUser" :label="__('Filter by Member')">
                <option value="">{{ __('All Members') }}</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <flux:input wire:model.live.debounce.500ms="filterAction" :label="__('Search Action')" placeholder="e.g. login, settings..." />
        </div>

        <div class="flex gap-4">
            <flux:input type="date" wire:model.live="dateFrom" :label="__('From')" />
            <flux:input type="date" wire:model.live="dateTo" :label="__('To')" />
        </div>

        <flux:button variant="ghost" icon="x-mark" wire:click="clearFilters" tooltip="{{ __('Clear Filters') }}" />
    </flux:card>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Member') }}</flux:table.column>
                <flux:table.column>{{ __('Resource') }}</flux:table.column>
                <flux:table.column>{{ __('IP') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline" class="font-mono text-xs uppercase">{{ $log->action }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($log->user)
                                <div class="flex items-center gap-2">
                                    <flux:avatar :name="$log->user->name" size="xs" />
                                    <span class="text-sm font-medium">{{ $log->user->name }}</span>
                                </div>
                            @else
                                <span class="text-xs text-zinc-400 italic">{{ __('System') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            {{ strtoupper($log->resource ?: '-') }} 
                            @if($log->resource_id)
                                <span class="text-[10px] block opacity-50">{{ $log->resource_id }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs font-mono text-zinc-400">
                            {{ $log->ip }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Export Modal -->
    <flux:modal name="export-logs" :open="$showingExportModal" class="min-w-[25rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Export Audit Log') }}</flux:heading>
                <flux:subheading>{{ __('Generate a CSV file for the selected date range. Max 90 days.') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input type="date" wire:model="exportFrom" :label="__('Start Date')" required />
                <flux:input type="date" wire:model="exportTo" :label="__('End Date')" required />
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="$set('showingExportModal', false)">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="export" :loading="$exporting">
                    {{ __('Generate & Email CSV') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
