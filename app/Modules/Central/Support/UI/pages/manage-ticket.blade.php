<div class="flex flex-col gap-6 py-8 max-w-4xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.support.tickets')" wire:navigate />
        <div class="flex-1">
            <flux:heading size="xl">{{ $ticket->subject }}</flux:heading>
            <flux:subheading>{{ __('Ticket for :tenant', ['tenant' => $ticket->tenant?->name ?? 'N/A']) }}</flux:subheading>
        </div>
        <div class="flex gap-2">
            @foreach (['resolved', 'closed'] as $action)
                @if (in_array($ticket->status, ['open', 'in_progress', 'escalated']))
                    <flux:button wire:click="changeStatus('{{ $action }}')" size="sm" variant="{{ $action === 'closed' ? 'danger' : 'primary' }}">
                        {{ __(ucfirst($action)) }}
                    </flux:button>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-4 gap-4">
        <flux:card>
            <flux:heading size="sm">{{ __('Status') }}</flux:heading>
            <flux:badge size="sm" class="mt-1">{{ str_replace('_', ' ', ucfirst($ticket->status)) }}</flux:badge>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Priority') }}</flux:heading>
            <flux:badge size="sm" class="mt-1">{{ ucfirst($ticket->priority) }}</flux:badge>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('SLA') }}</flux:heading>
            <flux:text class="text-sm {{ $ticket->isOverdueSla() ? 'text-red-500' : '' }}">
                {{ $ticket->sla_breach_at ? $ticket->sla_breach_at->diffForHumans() : __('No SLA') }}
            </flux:text>
        </flux:card>
        <flux:card>
            <flux:heading size="sm">{{ __('Assigned') }}</flux:heading>
            <flux:select wire:change="assign($event.target.value)" class="mt-1 text-sm">
                <option value="">{{ __('Unassigned') }}</option>
                @foreach ($agents as $agent)
                    <option value="{{ $agent->id }}" {{ $ticket->assigned_to === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                @endforeach
            </flux:select>
        </flux:card>
    </div>

    <flux:card>
        <flux:heading size="sm">{{ __('Description') }}</flux:heading>
        <flux:text class="mt-2 whitespace-pre-wrap">{{ $ticket->description }}</flux:text>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('Messages') }}</flux:heading>

        <div class="space-y-3 max-h-96 overflow-y-auto">
            @forelse($ticket->messages as $message)
                <div class="flex gap-3 {{ $message->is_internal ? 'bg-amber-50 dark:bg-amber-900/10 p-3 rounded-lg' : '' }}">
                    <div class="flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm">{{ $message->author?->name ?? __('System') }}</span>
                            @if ($message->is_internal)
                                <flux:badge size="xs" color="amber">{{ __('Internal') }}</flux:badge>
                            @endif
                            <span class="text-xs text-zinc-400">{{ $message->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-sm mt-1 whitespace-pre-wrap">{{ $message->content }}</p>
                    </div>
                </div>
            @empty
                <flux:text class="text-zinc-400">{{ __('No messages yet.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-2 border-t pt-4">
            <flux:textarea wire:model="newMessage" rows="3" :placeholder="__('Type a message...')" />
            <div class="flex items-center justify-between">
                <flux:checkbox wire:model="isInternal" :label="__('Internal note (not visible to tenant)')" />
                <flux:button wire:click="addMessage" variant="primary">{{ __('Send') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('Tenant Audit Logs') }}</flux:heading>
        <flux:text class="text-xs text-zinc-400">{{ __('Recent security and identity events (CRITICAL/HIGH severity).') }}</flux:text>
        <div class="max-h-48 overflow-y-auto space-y-1">
            @forelse($auditLogs as $log)
                <div class="flex items-center justify-between text-xs py-1 border-b last:border-0">
                    <span class="font-mono w-20 text-zinc-400">{{ $log->occurredAt ? \Carbon\Carbon::parse($log->occurredAt)->format('H:i') : '-' }}</span>
                    <span class="font-mono w-20">
                        <flux:badge size="xs" :color="$log->severity === 'CRITICAL' ? 'red' : 'amber'">{{ $log->severity }}</flux:badge>
                    </span>
                    <span class="flex-1 text-zinc-600 dark:text-zinc-300 px-2">{{ $log->action }}</span>
                    <span class="w-24 text-right text-zinc-400">{{ $log->userName }}</span>
                </div>
            @empty
                <flux:text class="text-zinc-400 text-sm">{{ __('No audit events found for this tenant.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>
</div>
