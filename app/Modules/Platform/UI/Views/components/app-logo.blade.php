@props([
    'sidebar' => false,
])

@php
    $tenant = function_exists('tenant') ? tenant() : null;

    if ($tenant && app()->bound(\App\Modules\Platform\Contracts\TenantBrandResolverContract::class)) {
        $resolver = app(\App\Modules\Platform\Contracts\TenantBrandResolverContract::class);
        $brandName = $resolver->name();
        $logoUrl = $resolver->logoUrl();
    } elseif (app()->bound(\App\Modules\Platform\Contracts\PlatformBrandingContract::class)) {
        $resolver = app(\App\Modules\Platform\Contracts\PlatformBrandingContract::class);
        $brandName = $resolver->name();
        $logoUrl = $resolver->logoUrl();
    } else {
        $brandName = config('app.name');
        $logoUrl = null;
    }
@endphp

@if ($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-cover">
            @else
                <x-ui::components.app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-cover">
            @else
                <x-ui::components.app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
