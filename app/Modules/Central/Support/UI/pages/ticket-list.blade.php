<div class="flex flex-col gap-6 py-8">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Support Tickets') }}</flux:heading>
            <flux:subheading>{{ __('Manage tenant issues and track resolution.') }}</flux:subheading>
        </div>
        <flux:button :href="route('central.support.tickets.create')" icon="plus" variant="primary" wire:navigate>
            {{ __('New Ticket') }}
        </flux:button>
    </div>

    <flux:card class="flex flex-wrap gap-4 items-end">
        <div class="w-40">
            <flux:select wire:model.live="filterStatus" :label="__('Status')">
                <option value="">{{ __('All') }}</option>
                <option value="open">{{ __('Open') }}</option>
                <option value="in_progress">{{ __('In Progress') }}</option>
                <option value="escalated">{{ __('Escalated') }}</option>
                <option value="resolved">{{ __('Resolved') }}</option>
                <option value="closed">{{ __('Closed') }}</option>
            </flux:select>
        </div>
        <div class="w-40">
            <flux:select wire:model.live="filterPriority" :label="__('Priority')">
                <option value="">{{ __('All') }}</option>
                <option value="low">{{ __('Low') }}</option>
                <option value="medium">{{ __('Medium') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="critical">{{ __('Critical') }}</option>
            </flux:select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <flux:input wire:model.live.debounce.500ms="search" :label="__('Search')" placeholder="{{ __('Search tickets...') }}" />
        </div>
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$tickets">
            <flux:table.columns>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Priority') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Assigned') }}</flux:table.column>
                <flux:table.column>{{ __('SLA') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell class="max-w-[200px] truncate font-medium">{{ $ticket->subject }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $ticket->tenant?->name ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $pcolors = ['critical' => 'red', 'high' => 'amber', 'medium' => 'blue', 'low' => 'zinc'];
                            @endphp
                            <flux:badge size="sm" :color="$pcolors[$ticket->priority] ?? 'zinc'">{{ ucfirst($ticket->priority) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $scolors = ['open' => 'blue', 'in_progress' => 'amber', 'escalated' => 'red', 'resolved' => 'emerald', 'closed' => 'zinc'];
                            @endphp
                            <flux:badge size="sm" :color="$scolors[$ticket->status] ?? 'zinc'">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $ticket->assignedTo?->name ?? __('Unassigned') }}</flux:table.cell>
                        <flux:table.cell class="text-xs {{ $ticket->isOverdueSla() ? 'text-red-500 font-bold' : 'text-zinc-400' }}">
                            {{ $ticket->sla_breach_at ? $ticket->sla_breach_at->diffForHumans() : '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button :href="route('central.support.tickets.show', $ticket->id)" size="sm" variant="ghost" icon="eye" wire:navigate />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-400">{{ __('No tickets found.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
