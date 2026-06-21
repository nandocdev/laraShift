<div class="space-y-6">
    <div class="flex justify-between items-center">
        <flux:heading size="xl">{{ __('Withdrawals') }}</flux:heading>
        <flux:button :href="route('tenant.settings.payouts')" variant="ghost" icon="cog" wire:navigate>
            {{ __('Bank Settings') }}
        </flux:button>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <flux:card class="md:col-span-1 space-y-4">
            <flux:heading size="lg">{{ __('Request Withdrawal') }}</flux:heading>
            
            <div x-show="$wire.error" class="p-3 text-sm text-red-700 bg-red-100 rounded-lg">
                {{ $error }}
            </div>

            <flux:input wire:model="amount" type="number" step="0.01" label="{{ __('Amount to Withdraw') }}" suffix="USD" />
            
            <flux:button wire:click="requestPayout" variant="primary" class="w-full" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Request Payout') }}</span>
                <span wire:loading>{{ __('Processing...') }}</span>
            </flux:button>

            <flux:text size="xs" class="text-center text-zinc-500">
                {{ __('Funds will be sent to your configured bank account.') }}
            </flux:text>
        </flux:card>

        <flux:card class="md:col-span-2 space-y-4">
            <flux:heading size="lg">{{ __('Payout History') }}</flux:heading>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Reference') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($requests as $request)
                        <flux:table.row>
                            <flux:table.cell>{{ $request->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($request->amount, 2) }} {{ $request->currency }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :variant="match($request->status) {
                                    'paid' => 'success',
                                    'pending', 'processing' => 'warning',
                                    'rejected', 'failed' => 'danger',
                                    default => 'neutral'
                                }">{{ strtoupper($request->status) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">{{ $request->gateway_reference ?? '-' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="mt-4">
                {{ $requests->links() }}
            </div>
        </flux:card>
    </div>
</div>
