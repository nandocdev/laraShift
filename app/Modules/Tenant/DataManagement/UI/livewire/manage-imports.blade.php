<div class="flex flex-col gap-6 py-12">
    <div>
        <flux:heading size="xl">{{ __('Data Import') }}</flux:heading>
        <flux:subheading>{{ __('Import users and settings from a JSON file. Max 1000 records per import.') }}</flux:subheading>
    </div>

    <flux:card class="space-y-6">
        <flux:select wire:model="importType" :label="__('Import Type')">
            <option value="users">{{ __('Users') }}</option>
            <option value="settings">{{ __('Settings') }}</option>
        </flux:select>

        <flux:textarea wire:model="importJson" rows="10" :label="__('JSON Data')"
            placeholder='[{"email":"user@example.com","name":"John Doe"}]' />

        <flux:checkbox wire:model="overwrite" :label="__('Overwrite existing records')" />

        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="import">{{ __('Start Import') }}</flux:button>
        </div>
    </flux:card>

    <flux:card class="overflow-hidden">
        <flux:table :paginate="$imports">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Imported') }}</flux:table.column>
                <flux:table.column>{{ __('Errors') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($imports as $import)
                    <flux:table.row :key="$import->id">
                        <flux:table.cell class="text-xs text-zinc-400">{{ $import->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $import->type }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $colors = ['pending' => 'zinc', 'processing' => 'blue', 'completed' => 'emerald', 'completed_with_errors' => 'amber', 'failed' => 'red'];
                            @endphp
                            <flux:badge size="sm" :color="$colors[$import->status] ?? 'zinc'">{{ $import->status }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $import->summary['imported'] ?? '-' }} / {{ $import->summary['total'] ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="text-xs">{{ $import->summary['errors'] ?? 0 }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-400">{{ __('No imports yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
