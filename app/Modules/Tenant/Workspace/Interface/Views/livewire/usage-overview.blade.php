<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
    @foreach($stats as $stat)
        <flux:card class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <flux:heading size="sm" class="text-zinc-500 uppercase tracking-wider text-[10px]">{{ $stat['label'] }}</flux:heading>
                @if($stat['is_unlimited'])
                    <flux:badge size="sm" variant="neutral">{{ __('UNLIMITED') }}</flux:badge>
                @elseif($stat['percentage'] >= 100)
                    <flux:badge size="sm" variant="danger">{{ __('FULL') }}</flux:badge>
                @elseif($stat['percentage'] >= 80)
                    <flux:badge size="sm" variant="warning">{{ __('NEAR LIMIT') }}</flux:badge>
                @endif
            </div>

            <div class="flex items-baseline gap-2">
                <span class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($stat['current']) }}</span>
                @if(! $stat['is_unlimited'])
                    <span class="text-sm text-zinc-400">/ {{ number_format($stat['limit']) }}</span>
                @endif
            </div>

            @if(! $stat['is_unlimited'])
                <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-1.5 rounded-full overflow-hidden">
                    <div 
                        class="h-full rounded-full transition-all duration-500 {{ $stat['percentage'] >= 100 ? 'bg-red-500' : ($stat['percentage'] >= 80 ? 'bg-amber-500' : 'bg-indigo-500') }}" 
                        style="width: {{ $stat['percentage'] }}%"
                    ></div>
                </div>
            @endif
        </flux:card>
    @endforeach
</div>
