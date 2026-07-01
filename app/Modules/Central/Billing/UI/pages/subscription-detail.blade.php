<div class="flex flex-col gap-6">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.billing.subscriptions')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ $tenant->name }}</flux:heading>
            <flux:subheading>{{ __('Subscription details and billing history.') }}</flux:subheading>
        </div>
    </div>

    @if(session('status'))
        <flux:toast variant="success" :text="session('status')" />
    @endif

    {{-- Subscription Info Card --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <flux:card class="flex flex-col gap-2 lg:col-span-2">
            <flux:heading size="lg">{{ __('Subscription') }}</flux:heading>
            <flux:separator />

            @if($subscription)
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <flux:text variant="subtle">{{ __('Plan') }}</flux:text>
                        <flux:text class="font-medium block">{{ strtoupper($this->tenant->plan_id) }}</flux:text>
                    </div>
                    <div>
                        <flux:text variant="subtle">{{ __('Status') }}</flux:text>
                        <div class="mt-1">
                            @if($subscription->active())
                                <flux:badge size="sm" color="emerald">{{ __('Active') }}</flux:badge>
                            @elseif($subscription->onGracePeriod())
                                <flux:badge size="sm" color="amber">{{ __('Grace Period') }}</flux:badge>
                            @elseif($subscription->canceled())
                                <flux:badge size="sm" color="zinc">{{ __('Canceled') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="red">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div>
                        <flux:text variant="subtle">{{ __('Gateway') }}</flux:text>
                        <flux:text class="font-medium block">{{ $subscription->gateway ?? 'N/A' }}</flux:text>
                    </div>
                    <div>
                        <flux:text variant="subtle">{{ __('Next Billing') }}</flux:text>
                        <flux:text class="font-medium block">
                            {{ $subscription->current_period_end?->format('Y-m-d') ?? 'N/A' }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text variant="subtle">{{ __('Trial Ends') }}</flux:text>
                        <flux:text class="font-medium block">
                            {{ $subscription->trial_ends_at?->format('Y-m-d') ?? 'N/A' }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text variant="subtle">{{ __('Created') }}</flux:text>
                        <flux:text class="font-medium block">{{ $subscription->created_at->format('Y-m-d') }}</flux:text>
                    </div>
                </div>
            @else
                <flux:text variant="subtle" class="py-4 text-center">{{ __('No active subscription for this tenant.') }}</flux:text>
            @endif
        </flux:card>

        {{-- Quick Stats --}}
        <flux:card class="flex flex-col gap-2">
            <flux:heading size="lg">{{ __('Summary') }}</flux:heading>
            <flux:separator />
            <div class="space-y-4">
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Tenant Status') }}</flux:text>
                    <div class="mt-1">
                        @php
                            $statusColors = ['active' => 'emerald', 'trial' => 'blue', 'past_due' => 'amber', 'suspended' => 'red', 'archived' => 'zinc'];
                        @endphp
                        <flux:badge size="sm" :color="$statusColors[$this->tenant->status] ?? 'zinc'">
                            {{ str_replace('_', ' ', ucfirst($this->tenant->status ?? 'unknown')) }}
                        </flux:badge>
                    </div>
                </div>
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Total Invoices') }}</flux:text>
                    <flux:heading size="xl">{{ $invoices->total() }}</flux:heading>
                </div>
                <div>
                    <flux:text variant="subtle" size="sm">{{ __('Subscription ID') }}</flux:text>
                    <flux:text size="xs" class="font-mono block truncate">{{ $subscription?->provider_subscription_id ?? '-' }}</flux:text>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Invoice History --}}
    <flux:card class="overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3">
            <flux:heading size="lg">{{ __('Invoice History') }}</flux:heading>
        </div>

        <flux:table :paginate="$invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('Invoice #') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-mono text-xs font-medium">{{ $invoice->number }}</flux:table.cell>
                        <flux:table.cell>
                            {{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$invoice->status === 'paid' ? 'emerald' : ($invoice->status === 'pending' ? 'amber' : 'red')">
                                {{ strtoupper($invoice->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $invoice->issued_at?->format('Y-m-d') ?? $invoice->created_at->format('Y-m-d') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button icon="document-arrow-down" size="sm" variant="ghost"
                                :href="route('central.billing.invoices.pdf', $invoice->id)"
                                target="_blank" />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400 py-8">
                            {{ __('No invoices found for this tenant.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
