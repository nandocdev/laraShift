@props([
    'route',
    'icon',
    'label',
    'permission' => null,
    'href' => null,
    'target' => null,
])

@php
    if ($permission && !auth()->user()?->can($permission)) {
        return;
    }
    $active = request()->routeIs($route . '*');
    $url = $href ?? route($route);
@endphp

<flux:sidebar.item
    :icon="$icon"
    :href="$url"
    :current="$active"
    :target="$target"
    @if(!$target) wire:navigate @endif
>
    {{ $label }}
</flux:sidebar.item>
