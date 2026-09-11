<div class="max-w-2xl mx-auto py-12">
    <div class="mb-8">
        <flux:heading size="xl">{{ __('Active sessions') }}</flux:heading>
        <flux:subheading>{{ __('Devices currently signed in to your account.') }}</flux:subheading>
    </div>

    <flux:card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Device') }}</flux:table.column>
                <flux:table.column>{{ __('IP') }}</flux:table.column>
                <flux:table.column>{{ __('Last seen') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($sessions as $session)
                    <flux:table.row>
                        <flux:table.cell>
                            {{ \Illuminate\Support\Str::limit($session->user_agent ?? __('Unknown device'), 60) }}
                            @if ($session->session_id === $currentSessionId)
                                <flux:badge size="sm">{{ __('This device') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $session->ip ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $session->updated_at?->diffForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        {{ $sessions->links() }}

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button wire:click="revokeOthers" variant="danger">
                {{ __('Close all other sessions') }}
            </flux:button>
        </div>
    </flux:card>
</div>
