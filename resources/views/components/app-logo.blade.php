@props([
    'sidebar' => false,
])

@php
    $tenant = function_exists('tenant') ? tenant() : null;
    $brandName = config('app.name');
    $logoUrl = null;

    if ($tenant) {
        $brandName = $tenant->name ?? $brandName;
        $settings = \App\Modules\Tenant\Settings\Models\TenantSetting::where('tenant_id', $tenant->getTenantKey())->first();
        if ($settings && $settings->logo_path) {
            $logoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($settings->logo_path);
        }
    } else {
        $brandName = \App\Modules\Central\Settings\Support\CentralBranding::platformName();
        $logoUrl = \App\Modules\Central\Settings\Support\CentralBranding::logoUrl();
    }
@endphp

@if ($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo"
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-accent-content text-accent-foreground overflow-hidden">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-cover">
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
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
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
