<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Billing') }}</flux:heading>
            <flux:subheading>{{ __('Subscription status, recent payments and invoices.') }}</flux:subheading>
        </div>
        <flux:button variant="ghost" :href="route('tenant.billing.plans')" wire:navigate>{{ __('Change plan') }}</flux:button>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card>
        <flux:heading size="lg">{{ __('Subscription') }}</flux:heading>
        @if ($subscription)
            <div class="mt-4 flex flex-col gap-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Status') }}</span>
                    <flux:badge size="sm" variant="outline">{{ $subscription->status->value }}</flux:badge>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Gateway') }}</span>
                    <span>{{ $subscription->gateway }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">{{ __('Current period ends') }}</span>
                    <span>{{ $subscription->current_period_end?->toDateString() ?? '—' }}</span>
                </div>
                @if ($subscription->failed_attempts > 0)
                    <div class="flex justify-between">
                        <span class="text-zinc-500">{{ __('Failed attempts') }}</span>
                        <span>{{ $subscription->failed_attempts }}</span>
                    </div>
                @endif
            </div>

            <div class="mt-4 flex gap-2">
                @if ($subscription->status->value === 'active' && ! $subscription->cancel_at_period_end)
                    @if ($confirmingCancel)
                        <flux:button variant="danger" wire:click="cancel">{{ __('Confirm cancellation') }}</flux:button>
                        <flux:button variant="ghost" wire:click="$set('confirmingCancel', false)">{{ __('Keep it') }}</flux:button>
                    @else
                        <flux:button variant="ghost" wire:click="$set('confirmingCancel', true)">{{ __('Cancel at period end') }}</flux:button>
                    @endif
                @endif
                @if ($subscription->cancel_at_period_end && $subscription->status->value === 'active')
                    <flux:button variant="primary" wire:click="resume">{{ __('Resume subscription') }}</flux:button>
                @endif
            </div>
        @else
            <flux:text class="mt-2">{{ __('No subscription yet.') }}</flux:text>
            <flux:button class="mt-4" variant="primary" :href="route('tenant.billing.plans')" wire:navigate>{{ __('Choose a plan') }}</flux:button>
        @endif
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:heading size="lg">{{ __('Recent payments') }}</flux:heading>
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell class="font-mono text-xs">{{ $payment->display_id }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($payment->amount_cents / 100, 2) }} {{ $payment->currency }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" variant="outline">{{ $payment->status->value }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">{{ __('No payments yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:heading size="lg">{{ __('Recent invoices') }}</flux:heading>
        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>{{ $invoice->issued_at?->toDateString() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" variant="outline">{{ $invoice->status }}</flux:badge></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">{{ __('No invoices yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <flux:button class="mt-4" variant="ghost" :href="route('tenant.billing.invoices')" wire:navigate>{{ __('View all') }}</flux:button>
    </flux:card>
</div>
