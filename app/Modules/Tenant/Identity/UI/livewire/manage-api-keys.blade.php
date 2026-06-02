<div class="flex flex-col gap-6 py-12">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('API Keys') }}</flux:heading>
            <flux:subheading>{{ __('Generate secure keys to integrate your tenant data with external systems.') }}</flux:subheading>
        </div>
        <flux:modal.trigger name="generate-key">
            <flux:button variant="primary" icon="plus">{{ __('Generate Key') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if (session('status'))
        <flux:text color="emerald">{{ session('status') }}</flux:text>
    @endif

    <flux:card class="p-0 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Scopes') }}</flux:table.column>
                <flux:table.column>{{ __('Last Used') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($apiKeys as $key)
                    <flux:table.row :key="$key->id">
                        <flux:table.cell>
                            <div class="font-medium text-sm text-zinc-900 dark:text-white">{{ $key->name }}</div>
                            <div class="text-[10px] text-zinc-500 font-mono">ID: {{ $key->id }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex flex-wrap gap-1">
                                @foreach($key->scopes as $scope)
                                    <flux:badge size="sm" variant="outline" class="text-[10px]">{{ $scope }}</flux:badge>
                                @endforeach
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-xs text-zinc-500">
                            {{ $key->last_used_at?->diffForHumans() ?: __('Never') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($key->isActive())
                                <flux:badge size="sm" variant="success">{{ __('ACTIVE') }}</flux:badge>
                            @else
                                <flux:badge size="sm" variant="neutral">{{ __('REVOKED') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if($key->isActive())
                                <flux:button 
                                    icon="no-symbol" 
                                    size="sm" 
                                    variant="ghost" 
                                    wire:click="revoke('{{ $key->id }}')"
                                    wire:confirm="{{ __('Are you sure you want to revoke this key? This action is immediate and permanent.') }}"
                                />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-8 text-zinc-500">
                            {{ __('No API keys generated yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <!-- Generation Modal -->
    <flux:modal name="generate-key" class="min-w-[25rem]">
        <form wire:submit="generate" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Generate New API Key') }}</flux:heading>
                <flux:subheading>{{ __('Give your key a name and select its permissions.') }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Key Name')" placeholder="{{ __('External ERP Integration') }}" required />

            <div class="space-y-2">
                <flux:label>{{ __('Scopes') }}</flux:label>
                <div class="grid grid-cols-1 gap-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg max-h-[200px] overflow-y-auto">
                    @foreach($availableScopes as $key => $label)
                        <label class="flex items-center gap-3 p-2 hover:bg-zinc-50 dark:hover:bg-zinc-800 rounded cursor-pointer transition-colors">
                            <input type="checkbox" wire:model="selectedScopes" value="{{ $key }}" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-600">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold font-mono">{{ $key }}</span>
                                <span class="text-[10px] text-zinc-500">{{ $label }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Generate') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Key Display Modal (Only once) -->
    <flux:modal name="show-key" :open="$showingKey" class="min-w-[30rem]">
        <div class="space-y-6 text-center">
            <div>
                <flux:heading size="lg">{{ __('API Key Generated') }}</flux:heading>
                <flux:text class="text-red-500 font-bold">{{ __('IMPORTANT: Copy this key now. It will never be shown again.') }}</flux:text>
            </div>

            <div class="p-4 bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 break-all font-mono text-sm select-all">
                {{ $plainKey }}
            </div>

            <div class="flex justify-center">
                <flux:button wire:click="closeKeyModal" variant="primary">{{ __('I have saved the key') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
