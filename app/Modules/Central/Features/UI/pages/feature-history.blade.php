<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Feature Change History') }}</flux:heading>
        <flux:subheading>{{ __('Audit trail of all changes made to feature flags and tenant overrides.') }}</flux:subheading>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$logs">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Event') }}</flux:table.column>
                <flux:table.column>{{ __('Details') }}</flux:table.column>
                <flux:table.column>{{ __('Actor') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($logs as $log)
                    <flux:table.row :key="$log->id">
                        <flux:table.cell class="text-xs whitespace-nowrap text-zinc-400">
                            {{ $log->created_at->format('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" variant="outline" class="font-mono text-xs uppercase">
                                {{ $log->description }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500 max-w-xs truncate">
                            @php
                                $props = $log->properties ?? collect();
                                $changes = $props->get('changes', []);
                            @endphp

                            @if ($log->description === 'feature_updated' && ! empty($changes))
                                @foreach ($changes as $field => $diff)
                                    <span class="block text-[10px]">
                                        <span class="font-medium">{{ $field }}</span>:
                                        <span class="line-through text-red-400">{{ is_array($diff['from'] ?? null) ? json_encode($diff['from']) : ($diff['from'] ?? '—') }}</span>
                                        →
                                        <span class="text-emerald-400">{{ is_array($diff['to'] ?? null) ? json_encode($diff['to']) : ($diff['to'] ?? '—') }}</span>
                                    </span>
                                @endforeach
                            @elseif ($log->description === 'feature_override_applied')
                                <span>{{ __('Override :type for :feature', [
                                    'type' => $props->get('type', '—'),
                                    'feature' => $props->get('feature', '—'),
                                ]) }}</span>
                                @if ($props->get('expires_at'))
                                    <span class="block opacity-60">{{ __('Expires: :date', ['date' => $props->get('expires_at')]) }}</span>
                                @endif
                            @else
                                <span class="text-zinc-400 italic">{{ __('—') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-400">
                            {{ $log->causer?->name ?? __('System') }}
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400">
                            {{ __('No changes recorded yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
