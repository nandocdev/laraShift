<div class="space-y-8">
    <!-- Add Note -->
    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Support Bitacora') }}</flux:heading>
        <form wire:submit="addNote" class="space-y-4">
            <flux:textarea 
                wire:model="newNote" 
                :label="__('Internal Support Note')" 
                placeholder="{{ __('Describe recent interactions, issues resolved, or internal observations...') }}" 
                rows="3"
                required
            />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" size="sm" icon="plus">
                    {{ __('Add Note') }}
                </flux:button>
            </div>
        </form>
    </flux:card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Notes List -->
        <div class="space-y-4">
            <flux:heading size="sm" class="uppercase text-zinc-400 tracking-widest font-bold">{{ __('Recent Notes') }}</flux:heading>
            @forelse($notes as $note)
                <div class="p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $note->author->name }}</span>
                        <span class="text-[10px] text-zinc-500">{{ $note->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 italic">"{{ $note->content }}"</p>
                </div>
            @empty
                <flux:text class="text-center py-4">{{ __('No internal notes for this tenant.') }}</flux:text>
            @endforelse
        </div>

        <!-- Impersonation History -->
        <div class="space-y-4">
            <flux:heading size="sm" class="uppercase text-zinc-400 tracking-widest font-bold">{{ __('Impersonation Audit') }}</flux:heading>
            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Operator') }}</flux:table.column>
                        <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse($sessions as $session)
                            <flux:table.row :key="$session->id">
                                <flux:table.cell class="text-xs">{{ $session->operator->name }}</flux:table.cell>
                                <flux:table.cell class="text-xs max-w-[150px] truncate" title="{{ $session->reason }}">
                                    {{ $session->reason }}
                                </flux:table.cell>
                                <flux:table.cell class="text-[10px] text-zinc-500">
                                    {{ $session->started_at->format('Y-m-d H:i') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="3" class="text-center text-xs text-zinc-500 py-4">{{ __('No sessions recorded.') }}</flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    </div>
</div>
