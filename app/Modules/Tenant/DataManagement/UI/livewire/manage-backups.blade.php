<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Data Backups') }}</flux:heading>
            <flux:subheading>{{ __('On-demand backups of your tenant data. Available for 7 days.') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="cloud-arrow-up" wire:click="create" :loading="$creating">
            {{ __('Create Backup') }}
        </flux:button>
    </div>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$backups">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Size') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Expires') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($backups as $backup)
                    <flux:table.row :key="$backup->id">
                        <flux:table.cell class="text-xs text-zinc-400">{{ $backup->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $backup->size_bytes ? number_format($backup->size_bytes / 1024, 1) . ' KB' : '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$backup->status === 'completed' ? 'emerald' : ($backup->status === 'failed' ? 'red' : 'zinc')">
                                {{ ucfirst($backup->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-400">{{ $backup->expires_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($backup->status === 'completed' && $backup->file_path)
                                <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                                    :href="URL::temporarySignedRoute('tenant.data.download', now()->addHours(24), ['path' => $backup->file_path])" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">{{ __('No backups yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
