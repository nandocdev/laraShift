<div class="space-y-4">
    <flux:heading size="lg">{{ __('Notifications') }}</flux:heading>
    
    <div class="space-y-2">
        @forelse($notifications as $notification)
            <flux:card class="flex items-start justify-between p-4">
                <div class="flex-1">
                    <div class="font-medium {{ $notification->read_at ? 'text-zinc-500' : 'text-zinc-900 dark:text-white' }}">
                        {{ $notification->data['message'] ?? __('New notification') }}
                    </div>
                    <div class="text-xs text-zinc-400 mt-1">{{ $notification->created_at->diffForHumans() }}</div>
                </div>
                
                <div class="flex gap-2">
                    @if(! $notification->read_at)
                        <flux:button wire:click="markAsRead('{{ $notification->id }}')" variant="ghost" size="sm" icon="check" />
                    @endif
                    <flux:button wire:click="delete('{{ $notification->id }}')" variant="ghost" size="sm" icon="trash" />
                </div>
            </flux:card>
        @empty
            <flux:text class="text-center py-8">{{ __('No notifications.') }}</flux:text>
        @endforelse
    </div>
    
    <div class="mt-4">
        {{ $notifications->links() }}
    </div>
</div>
