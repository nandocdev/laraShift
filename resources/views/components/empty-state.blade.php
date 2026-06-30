@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionRoute' => null,
    'actionWire' => null,
    'compact' => false,
])

<div @class(['flex flex-col items-center text-center', 'py-8' => $compact, 'py-16' => !$compact])>

    <div @class([
        'flex items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800',
        'mb-3 size-10' => $compact,
        'mb-4 size-14' => !$compact,
    ])>
        <flux:icon :name="$icon" @class(['text-zinc-400', 'size-5' => $compact, 'size-7' => !$compact]) />
    </div>

    <h3 @class(['font-semibold text-zinc-900 dark:text-zinc-100', 'text-sm' => $compact, 'text-base' => !$compact])>
        {{ $title }}
    </h3>

    @if($description)
        <p @class(['mt-1 text-zinc-500 dark:text-zinc-400', 'text-xs' => $compact, 'text-sm' => !$compact, 'max-w-sm' => !$compact])>
            {{ $description }}
        </p>
    @endif

    @if($actionLabel)
        <div class="mt-4">
            @if($actionRoute)
                <flux:button :href="route($actionRoute)" size="sm" variant="primary" icon="plus">
                    {{ $actionLabel }}
                </flux:button>
            @elseif($actionWire)
                <flux:button wire:click="{{ $actionWire }}" size="sm" variant="primary" icon="plus">
                    {{ $actionLabel }}
                </flux:button>
            @endif
        </div>
    @endif

    @if($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
