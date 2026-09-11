<div class="flex flex-col gap-6 py-12 max-w-3xl mx-auto">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.billing.subscriptions')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Subscription') }}: {{ $this->tenant?->name ?? $subscription->tenant_id }}</flux:heading>
            <flux:subheading>{{ $subscription->gateway }} · {{ $subscription->status->value }}</flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="p-6">
        <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 mb-4">{{ __('Detail') }}</flux:heading>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><dt class="font-semibold">{{ __('Plan') }}</dt><dd>{{ $this->plan?->name ?? $subscription->plan_id }}</dd></div>
            <div><dt class="font-semibold">{{ __('Price') }}</dt><dd>@if($this->plan){{ number_format($this->plan->price_monthly / 100, 2) }} {{ $this->plan->currency }} / {{ $this->plan->interval }}@else — @endif</dd></div>
            <div><dt class="font-semibold">{{ __('Billing Cycle') }}</dt><dd>{{ $subscription->current_period_start?->toDateString() ?? '—' }} → {{ $subscription->current_period_end?->toDateString() ?? '—' }}</dd></div>
            <div><dt class="font-semibold">{{ __('Status') }}</dt><dd><flux:badge size="sm" variant="outline">{{ $subscription->status->value }}</flux:badge></dd></div>
            <div><dt class="font-semibold">{{ __('Renewal') }}</dt><dd>{{ $subscription->next_payment_at?->toDateString() ?? '—' }}@if($subscription->cancel_at_period_end) · {{ __('Ends at period end') }}@endif</dd></div>
            <div><dt class="font-semibold">{{ __('Failed attempts') }}</dt><dd>{{ $subscription->failed_attempts }}</dd></div>
        </dl>
    </flux:card>

    <flux:card class="p-6">
        <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 mb-4">{{ __('Change Plan') }}</flux:heading>
        <form wire:submit="changePlan" class="flex flex-col gap-4">
            <flux:select wire:model="planSlug" :label="__('Plan')">
                @foreach ($this->plans as $plan)
                    <option value="{{ $plan['slug'] }}">{{ $plan['name'] }}</option>
                @endforeach
            </flux:select>
            <flux:error name="planSlug" />
            <div class="flex justify-end gap-2">
                <flux:button type="submit" variant="primary">{{ __('Change Plan') }}</flux:button>
                <flux:button variant="ghost" wire:click="reactivate">{{ __('Reactivate') }}</flux:button>
                <flux:button variant="danger" wire:click="cancel" wire:confirm="{{ __('Cancel at period end?') }}">{{ __('Cancel Subscription') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card class="p-6">
        <flux:heading size="sm" class="font-bold uppercase tracking-wider text-xs text-zinc-500 mb-4">{{ __('Billing History') }}</flux:heading>
        <flux:heading size="xs" class="mb-2">{{ __('Payments') }}</flux:heading>
        <div class="space-y-2 text-sm mb-6">
            @forelse($payments as $payment)
                <div class="flex items-center justify-between">
                    <span>{{ number_format($payment->amount_cents / 100, 2) }} {{ $payment->currency }}</span>
                    <flux:badge size="sm" variant="outline">{{ $payment->status->value }}</flux:badge>
                </div>
            @empty
                <p class="text-zinc-500">{{ __('No payments.') }}</p>
            @endforelse
        </div>
        <flux:heading size="xs" class="mb-2">{{ __('Invoices') }}</flux:heading>
        <div class="space-y-2 text-sm">
            @forelse($invoices as $invoice)
                <div class="flex items-center justify-between">
                    <span>{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</span>
                    <flux:badge size="sm" variant="outline">{{ $invoice->status }}</flux:badge>
                </div>
            @empty
                <p class="text-zinc-500">{{ __('No invoices.') }}</p>
            @endforelse
        </div>
    </flux:card>
</div>
