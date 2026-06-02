<div class="flex flex-col gap-8 py-12">
    <div>
        <flux:heading size="xl">{{ __('Billing & Subscription') }}</flux:heading>
        <flux:subheading>{{ __('Manage your plan, payment methods, and download invoices.') }}</flux:subheading>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Current Plan -->
        <flux:card>
            <div class="flex justify-between items-start mb-6">
                <div>
                    <flux:heading size="lg">{{ __('Current Plan') }}</flux:heading>
                    <flux:badge size="sm" variant="success" class="mt-1">{{ strtoupper($tenant->plan_id) }}</flux:badge>
                </div>
                <flux:button variant="ghost" icon="arrow-path">{{ __('Change Plan') }}</flux:button>
            </div>

            <div class="space-y-4">
                @if($subscription)
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Status') }}</span>
                        <span class="font-medium">{{ strtoupper($subscription->stripe_status) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-500">{{ __('Next billing date') }}</span>
                        <span class="font-medium">{{ $subscription->nextPayment() ? $subscription->nextPayment()->date()->format('M j, Y') : 'N/A' }}</span>
                    </div>
                @else
                    <flux:text>{{ __('You are currently on the Free plan.') }}</flux:text>
                @endif
            </div>
        </flux:card>

        <!-- Payment Method -->
        <flux:card>
            <div class="flex justify-between items-start mb-6">
                <flux:heading size="lg">{{ __('Payment Method') }}</flux:heading>
                <flux:button :href="route('tenant.billing.update-payment')" variant="ghost" icon="pencil-square" wire:navigate>
                    {{ __('Edit') }}
                </flux:button>
            </div>

            @if($tenant->pm_type)
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                        <flux:icon icon="credit-card" size="lg" />
                    </div>
                    <div>
                        <div class="font-bold">{{ strtoupper($tenant->pm_type) }} •••• {{ $tenant->pm_last_four }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Default payment method') }}</div>
                    </div>
                </div>
            @else
                <flux:text>{{ __('No payment method on file.') }}</flux:text>
            @endif
        </flux:card>
    </div>

    <!-- Invoices -->
    <div>
        <flux:heading size="lg" class="mb-4">{{ __('Recent Invoices') }}</flux:heading>
        <flux:card class="p-0 overflow-hidden">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse($invoices as $invoice)
                        <flux:table.row :key="$invoice->id">
                            <flux:table.cell>{{ $invoice->created_at->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($invoice->amount_due / 100, 2) }} {{ strtoupper($invoice->currency) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :variant="$invoice->status === 'paid' ? 'success' : 'warning'">
                                    {{ strtoupper($invoice->status) }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                <flux:button icon="document-arrow-down" variant="ghost" size="sm" :href="route('central.billing.invoices.pdf', $invoice->id)" target="_blank" />
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4" class="text-center py-8 text-zinc-500">
                                {{ __('No invoices found.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</div>
