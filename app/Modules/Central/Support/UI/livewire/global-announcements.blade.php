<div>
    @foreach($activeBroadcasts as $broadcast)
        <div wire:key="banner-{{ $broadcast->id }}" class="w-full bg-indigo-600 text-white px-4 py-3 flex items-center justify-between shadow-md">
            <div class="flex items-center gap-3">
                <flux:icon icon="megaphone" variant="solid" class="shrink-0" />
                <div class="flex flex-col md:flex-row md:items-center gap-1 md:gap-4">
                    <span class="font-bold text-sm uppercase tracking-wide">{{ $broadcast->title }}</span>
                    <span class="text-sm opacity-90">{{ $broadcast->body }}</span>
                </div>
            </div>
            
            <button 
                wire:click="dismiss('{{ $broadcast->id }}')" 
                class="shrink-0 p-1 hover:bg-indigo-500 rounded-full transition-colors"
                aria-label="{{ __('Dismiss') }}"
            >
                <flux:icon icon="x-mark" size="sm" />
            </button>
        </div>
    @endforeach
</div>
