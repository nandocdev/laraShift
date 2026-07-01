@props([
    'route',
    'icon',
    'label',
    'href' => null,
    'target' => null,
])

@php
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
