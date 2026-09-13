<div class="space-y-6 max-w-3xl">
    <div>
        <flux:heading size="xl">{{ __('Webhooks') }}</flux:heading>
        <flux:subheading>{{ __('Notify your systems in real time. Every delivery is HMAC-SHA256 signed.') }}</flux:subheading>
    </div>

    <flux:separator />

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    @if ($showingSecret)
        <flux:card class="border-amber-300">
            <flux:heading size="sm">{{ __('Signing secret (only time shown)') }}</flux:heading>
            <flux:text class="font-mono break-all">{{ $plainSecret }}</flux:text>
            <div class="mt-3">
                <flux:button wire:click="closeSecretModal" variant="ghost" size="sm">{{ __('I saved it') }}</flux:button>
            </div>
        </flux:card>
    @endif

    <flux:card>
        <form wire:submit="register" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Destination URL (HTTPS)') }}</flux:label>
                <flux:input wire:model="url" type="url" placeholder="https://erp.empresa.com/webhooks" />
                <flux:error name="url" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Events') }}</flux:label>
                @foreach ($availableEvents as $event)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="events" value="{{ $event }}" class="rounded">
                        <span class="font-mono">{{ $event }}</span>
                    </label>
                @endforeach
                <flux:error name="events" />
            </flux:field>

            <div>
                <flux:button type="submit" variant="primary">{{ __('Register webhook') }}</flux:button>
            </div>
        </form>
    </flux:card>

    <flux:card>
        <flux:heading size="sm">{{ __('Endpoints') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('URL') }}</flux:table.column>
                <flux:table.column>{{ __('Events') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($endpoints as $endpoint)
                    <flux:table.row>
                        <flux:table.cell class="break-all">{{ $endpoint->url }}</flux:table.cell>
                        <flux:table.cell>{{ implode(', ', $endpoint->events ?? []) }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ $endpoint->is_active ? __('Active') : __('Disabled') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button wire:click="revoke('{{ $endpoint->id }}')" wire:confirm="{{ __('Remove this endpoint?') }}" variant="ghost" size="sm">{{ __('Remove') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">{{ __('No webhook endpoints yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:card>
        <flux:heading size="sm">{{ __('Recent deliveries') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Event') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Attempts') }}</flux:table.column>
                <flux:table.column>{{ __('HTTP') }}</flux:table.column>
                <flux:table.column>{{ __('When') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($deliveries as $delivery)
                    <flux:table.row>
                        <flux:table.cell class="font-mono">{{ $delivery->event_type }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline">{{ $delivery->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>{{ $delivery->attempts }}</flux:table.cell>
                        <flux:table.cell>{{ $delivery->response_status ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $delivery->created_at?->diffForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">{{ __('No deliveries yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
