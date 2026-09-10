<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Invoices') }}</flux:heading>
        <flux:subheading>{{ __('Billing history for this workspace.') }}</flux:subheading>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Issued') }}</flux:table.column>
                <flux:table.column>{{ __('Amount') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Paid at') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse($invoices as $invoice)
                    <flux:table.row :key="$invoice->id">
                        <flux:table.cell>{{ $invoice->issued_at?->toDateString() ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($invoice->amount_cents / 100, 2) }} {{ $invoice->currency }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" variant="outline">{{ $invoice->status }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $invoice->paid_at?->toDateString() ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('No invoices yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </flux:card>
</div>
