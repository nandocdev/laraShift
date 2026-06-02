<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Audit Logs') }}</flux:heading>
        <flux:subheading>{{ __('Immutable history of actions performed within this account.') }}</flux:subheading>
    </div>

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
</div>
