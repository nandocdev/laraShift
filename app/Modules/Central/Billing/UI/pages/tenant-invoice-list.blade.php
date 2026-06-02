<div class="flex flex-col gap-6">
    <div class="flex items-center gap-4">
        <flux:button icon="arrow-left" variant="ghost" :href="route('central.billing.subscriptions')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Invoices for') }} {{ $tenant->name }}</flux:heading>
            <flux:subheading>{{ __('Historical billing records and pro-forma downloads.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <flux:table :paginate="$invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('Invoice #') }}</flux:table.column>
                <flux:table.column>{{ __('Period') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell class="font-medium">
                            {{ $invoice->number }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $invoice->period_start->format('Y-m-d') }} - {{ $invoice->period_end->format('Y-m-d') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ number_format($invoice->amount_due / 100, 2) }} {{ strtoupper($invoice->currency) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :variant="$invoice->status === 'paid' ? 'success' : 'warning'">
                                {{ strtoupper($invoice->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $invoice->created_at->format('Y-m-d') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button 
                                icon="document-arrow-down" 
                                size="sm" 
                                variant="ghost" 
                                :href="route('central.billing.invoices.pdf', $invoice->id)"
                                target="_blank"
                            >
                                {{ __('PDF') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
