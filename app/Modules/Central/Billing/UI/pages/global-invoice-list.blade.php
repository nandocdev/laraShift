<div class="flex flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Global Invoices') }}</flux:heading>
            <flux:subheading>{{ __('Complete historical billing audit across all platform tenants.') }}</flux:subheading>
        </div>
    </div>

    <flux:card>
        <flux:table :paginate="$invoices">
            <flux:table.columns>
                <flux:table.column>{{ __('Tenant') }}</flux:table.column>
                <flux:table.column>{{ __('Invoice #') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>
                            <div class="font-medium text-sm">{{ $invoice->tenant->name }}</div>
                            <div class="text-[10px] text-zinc-500 uppercase">{{ $invoice->tenant->plan_id }}</div>
                        </flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">
                            {{ $invoice->number }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ \App\Modules\Shared\Infrastructure\Services\PriceFormatter::format($invoice->amount) }}
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
                            />
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
