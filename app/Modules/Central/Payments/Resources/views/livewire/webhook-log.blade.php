<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Webhook Log') }}</flux:heading>
            <flux:subheading>{{ __('Inbound payment webhooks from payment gateways.') }}</flux:subheading>
        </div>
    </div>

    {{-- Filters --}}
    <flux:card class="flex flex-wrap gap-4 items-end">
        <div class="w-40">
            <flux:select wire:model.live="filterGateway" :label="__('Gateway')">
                <option value="">{{ __('All') }}</option>
                <option value="CLAVE">{{ __('Clave') }}</option>
                <option value="DLOCAL">{{ __('dLocal') }}</option>
            </flux:select>
        </div>
        <div class="w-40">
            <flux:select wire:model.live="filterStatus" :label="__('Status')">
                <option value="">{{ __('All') }}</option>
                <option value="approved">{{ __('Approved') }}</option>
                <option value="declined">{{ __('Declined') }}</option>
                <option value="pending">{{ __('Pending') }}</option>
                <option value="error">{{ __('Error') }}</option>
            </flux:select>
        </div>
        <div class="w-44">
            <flux:input type="date" wire:model.live="dateFrom" :label="__('From')" />
        </div>
        <div class="w-44">
            <flux:input type="date" wire:model.live="dateTo" :label="__('To')" />
        </div>
    </flux:card>

    {{-- Payload Modal --}}
    @if($expandedPayload)
        <flux:modal name="payload-modal" class="max-w-2xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">{{ __('Raw Payload') }}</flux:heading>
                    <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="closePayload" />
                </div>
                <pre class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 overflow-x-auto text-xs leading-relaxed max-h-96 overflow-y-auto">{{ $expandedPayload }}</pre>
                <div class="mt-4 flex justify-end">
                    <flux:button variant="ghost" wire:click="closePayload">{{ __('Close') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    {{-- Table --}}
    <flux:card class="overflow-hidden">
        <flux:table :paginate="$webhooks">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Gateway') }}</flux:table.column>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($webhooks as $webhook)
                    <flux:table.row :key="$webhook->id">
                        <flux:table.cell class="text-xs whitespace-nowrap">{{ $webhook->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $webhook->gateway_code }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs font-mono">{{ $webhook->tenant_id ? substr($webhook->tenant_id, 0, 8).'...' : '-' }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">${{ number_format($webhook->amount, 2) }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColors = ['approved' => 'emerald', 'declined' => 'red', 'pending' => 'amber', 'error' => 'red'];
                            @endphp
                            <flux:badge size="sm" :color="$statusColors[$webhook->status] ?? 'zinc'">
                                {{ ucfirst($webhook->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs font-mono">{{ substr($webhook->gateway_reference, 0, 12) }}...</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" icon="eye" wire:click="showPayload('{{ $webhook->id }}')" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center text-zinc-400 py-8">
                            {{ __('No webhooks found matching the filters.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
