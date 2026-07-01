<flux:sidebar.item
    :icon="$icon"
    :href="$href ?? route($route)"
    :current="request()->routeIs($route . '*')"
    :target="$target ?? null"
    @if (!($target ?? false)) wire:navigate @endif
>{{ $label ?? '' }}</flux:sidebar.item>
