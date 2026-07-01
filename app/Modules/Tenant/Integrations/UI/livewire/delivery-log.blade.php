<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Webhook Delivery Log') }}</flux:heading>
            <flux:subheading>{{ __('Track delivery attempts and troubleshoot failed webhooks.') }}</flux:subheading>
        </div>
    </div>

    <flux:card class="flex gap-4 items-end">
        <div class="w-48">
            <flux:select wire:model.live="filterStatus" :label="__('Filter by Status')">
                <option value="">{{ __('All') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="delivered">{{ __('Delivered') }}</option>
                <option value="failed">{{ __('Failed') }}</option>
                <option value="dead_lettered">{{ __('Dead Letter') }}</option>
            </flux:select>
        </div>
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$deliveries">
            <flux:table.columns>
                <flux:table.column>{{ __('Event') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('Response') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($deliveries as $delivery)
                    <flux:table.row :key="$delivery->id">
                        <flux:table.cell class="font-mono text-xs">{{ $delivery->event }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $colors = ['pending' => 'zinc', 'delivered' => 'emerald', 'failed' => 'amber', 'dead_lettered' => 'red'];
                            @endphp
                            <flux:badge size="sm" :color="$colors[$delivery->status] ?? 'zinc'">
                                {{ __(ucfirst(str_replace('_', ' ', $delivery->status))) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $delivery->attempt }}</flux:table.cell>
                        <flux:table.cell class="text-xs font-mono">
                            {{ $delivery->response_status ?? '-' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-400 whitespace-nowrap">
                            {{ $delivery->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if (in_array($delivery->status, ['failed', 'dead_lettered']))
                                <flux:button wire:click="retry('{{ $delivery->id }}')" size="sm" variant="ghost" icon="arrow-path" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-400">
                            {{ __('No delivery attempts yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
